<?php
namespace Tests\Integration\AgendaV2;

use App\Models\{AppointmentEvent,User,UserAccountEvent};
use App\Services\Scheduling\MedicalCareService;
use App\Support\Scheduling\SchedulingCapability as S;
use Illuminate\Support\Facades\{Artisan,DB};
use Illuminate\Support\Str;
use Tests\Concerns\BuildsRescheduleVisualData;
use Tests\TestCase;

/** Real independent sessions, strict owned-runtime guard before any migration/fixture. */
class MedicalIdentityConcurrencyTest extends TestCase
{
    use BuildsRescheduleVisualData;
    private $actor,$a,$catalog,$physician,$admin;
    protected function setUp():void
    {
        parent::setUp();
        if(getenv('AGENDA_V2_QA_RUNTIME')!=='agenda-v2-11.8.9')$this->markTestSkipped('Guarded disposable MariaDB only.');
        Artisan::call('migrate:fresh',['--force'=>true]);
        foreach($this->rescheduleVisualFixture() as $key=>$value)$this->{$key}=$value;
        $this->physician=$this->createUserWithRole('MEDICO');$this->physician->forceFill(['doctor_id'=>$this->a->doctor_id])->save();
        $this->admin=$this->createUserWithRole('ADMINISTRADOR');
        $this->a->forceFill(['estado_cita'=>'EN_ATENCION','hora_llegada'=>now(),'hora_atencion'=>now()])->save();
    }
    protected function tearDown():void{\Carbon\Carbon::setTestNow();parent::tearDown();}
    private function care(string $action='finish'):array
    {
        return ['action'=>'CARE','actor_id'=>$this->physician->id,'appointment_id'=>$this->a->id,'clinical_action'=>$action,
            'payload'=>['expected_state'=>$this->a->fresh()->estado_cita,'revision'=>MedicalCareService::revision($this->a->fresh()),
                'confirmed'=>true,'reason'=>'Actuación FICTICIA concurrente','request_key'=>(string)Str::uuid()]];
    }
    private function link(?int $next=null,?User $target=null):array
    {
        $target??=$this->physician;
        return ['action'=>'LINK','actor_id'=>$this->admin->id,'target_id'=>$target->id,'old_doctor_id'=>$this->a->doctor_id,
            'payload'=>['doctor_id'=>$next,'expected_doctor_id'=>$target->doctor_id,'expected_version'=>$target->auth_version,
                'confirmed'=>true,'identity_confirmed'=>true,'reason'=>'Vinculación FICTICIA concurrente','request_key'=>(string)Str::uuid()]];
    }
    private function race(array $first,array $second,bool $ordered):array
    {
        $dir=storage_path('app/qa-medical-race');if(!is_dir($dir))mkdir($dir,0700,true);
        $run=$dir.'/'.Str::uuid();$jobs=[];$workers=[];
        foreach([$first,$second] as $i=>$spec){
            $job=$run.'-'.$i.'.json';$jobs[]=$job;
            $spec+=['release'=>$run.'.release','locked'=>$ordered?$run.'.locked':null,
                'attempted'=>$run.'.attempted','leader'=>$ordered&&$i===0];
            file_put_contents($job,json_encode($spec));
            $env=getenv();$env['AGENDA_V2_QA_RUNTIME']='agenda-v2-11.8.9';$pipes=[];
            $process=proc_open([PHP_BINARY,base_path('tests/Support/medical-care-worker.php'),$job],
                [1=>['pipe','w'],2=>['pipe','w']],$pipes,base_path(),$env);
            $this->assertIsResource($process);$workers[]=[$process,$pipes];
        }
        $deadline=microtime(true)+25;
        while(!is_file($jobs[0].'.ready')||!is_file($jobs[1].'.ready')){if(microtime(true)>$deadline)$this->fail('Worker readiness timeout.');usleep(10000);}
        touch($run.'.release');$results=[];
        foreach($workers as [$process,$pipes]){
            $out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
            $this->assertSame(0,proc_close($process),$error);$result=json_decode($out,true,512,JSON_THROW_ON_ERROR);$this->assertNotSame(500,$result['status'],$out);
            $results[]=$result;
        }
        return $results;
    }
    public function test_revocation_commits_first_and_blocks_a_stale_medical_finish():void
    {
        $result=$this->race($this->link(),$this->care(),true);
        $this->assertSame([200,403],array_column($result,'status'));
        $this->assertNull($this->physician->fresh()->doctor_id);$this->assertSame(1,$this->physician->fresh()->auth_version);
        $this->assertSame('EN_ATENCION',$this->a->fresh()->estado_cita);$this->assertNull($this->a->fresh()->hora_atendido);
        $this->assertSame(0,AppointmentEvent::where('event_type','ATENCION_MEDICA_FINALIZADA')->count());
    }
    public function test_reassignment_commits_first_and_cannot_attribute_an_action_to_the_previous_doctor():void
    {
        $next=$this->createAppointmentCatalog()['doctor']->id;$original=$this->a->doctor_id;
        $result=$this->race($this->link($next),$this->care(),true);
        $this->assertSame([200,403],array_column($result,'status'));$this->assertSame($original,$this->a->fresh()->doctor_id);
        $this->assertSame($next,$this->physician->fresh()->doctor_id);$this->assertNull($this->a->fresh()->hora_atendido);
    }
    public function test_medical_finish_commits_first_then_revocation_preserves_its_verified_actor_and_time():void
    {
        $result=$this->race($this->care(),$this->link(),true);
        $this->assertSame([200,200],array_column($result,'status'));$this->assertSame('ATENDIDO',$this->a->fresh()->estado_cita);
        $this->assertNotNull($this->a->fresh()->hora_atendido);$this->assertNull($this->a->fresh()->hora_salida);
        $this->assertNull($this->physician->fresh()->doctor_id);
        $this->assertDatabaseHas('appointment_events',['event_type'=>'ATENCION_MEDICA_FINALIZADA','actor_user_id'=>$this->physician->id]);
        $this->assertSame(1,UserAccountEvent::where('event_type','DOCTOR_LINK_CHANGED')->count());
        $this->assertDatabaseCount('appointments',1);$this->assertDatabaseCount('payments',1);$this->assertEquals(60,DB::table('payments')->sum('monto'));
    }
    public function test_simultaneous_same_operation_is_applied_once():void
    {
        $payload=$this->care();$result=$this->race($payload,$payload,false);
        $this->assertSame([200,200],array_column($result,'status'));
        $this->assertSame(1,AppointmentEvent::where('event_type','ATENCION_MEDICA_FINALIZADA')->count());
    }
    public function test_distinct_concurrent_finishes_conflict_without_overwriting_the_first_timestamp():void
    {
        $first=$this->care();$second=$first;$second['payload']['request_key']=(string)Str::uuid();
        $result=$this->race($first,$second,false);$codes=array_column($result,'status');sort($codes);
        $this->assertSame([200,409],$codes);$this->assertSame(1,AppointmentEvent::where('event_type','ATENCION_MEDICA_FINALIZADA')->count());
    }
    public function test_two_accounts_cannot_be_bound_to_the_same_professional_concurrently():void
    {
        $this->physician->forceFill(['doctor_id'=>null])->save();$other=$this->createUserWithRole('MEDICO');
        $result=$this->race($this->link($this->a->doctor_id),$this->link($this->a->doctor_id,$other),false);
        $codes=array_column($result,'status');sort($codes);$this->assertSame([200,409],$codes);
        $this->assertSame(1,User::where('doctor_id',$this->a->doctor_id)->count());
        $this->assertSame(1,UserAccountEvent::where('event_type','DOCTOR_LINK_CHANGED')->count());
    }
}
