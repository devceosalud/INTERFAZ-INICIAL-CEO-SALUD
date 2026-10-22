<?php
namespace Tests\Integration\MvpEconomyDirect;
use App\Models\{Appointment,AppointmentEvent};
use App\Services\Scheduling\{AdmissionCareService,AppointmentReevaluationService as R,ManualReevaluationService};
use Illuminate\Support\Facades\{Artisan,DB};
use Illuminate\Support\Str;
use Tests\Concerns\BuildsRescheduleVisualData;
use Tests\TestCase;

class AdmissionEpisodeConcurrencyTest extends TestCase
{
    use BuildsRescheduleVisualData;
    private $actor,$a,$medical;
    protected function setUp():void{
        parent::setUp();if(getenv('AGENDA_V2_QA_RUNTIME')!=='agenda-v2-11.8.9')$this->markTestSkipped('Guarded disposable MariaDB only.');
        Artisan::call('migrate:fresh',['--force'=>true]);$f=$this->rescheduleVisualFixture();$this->actor=$f['actor'];$this->a=$f['a'];
        $this->medical=$this->createUserWithRole('MEDICO');$this->medical->forceFill(['doctor_id'=>$this->a->doctor_id])->save();
        app(AdmissionCareService::class)->registerArrival($this->a->id,$this->actor,$this->payload(['expected_state'=>'PROGRAMADO']));
        app(AdmissionCareService::class)->transition($this->a->id,$this->medical,$this->payload(['state'=>'EN_ATENCION','expected_state'=>'EN_ESPERA']));
        app(R::class)->physicianDecision($this->a->id,$this->medical,$this->payload(['action'=>'indicate']));
    }
    protected function tearDown():void{\Carbon\Carbon::setTestNow();parent::tearDown();}
    private function payload(array $extra=[]):array{return $extra+['expected_state'=>'EN_ATENCION','confirmed'=>true,'request_key'=>(string)Str::uuid()];}
    private function ready():void{
        app(R::class)->confirmOperational($this->a->id,$this->actor,$this->payload());
        $commercial=$this->agendaReader('COMERCIAL');app(ManualReevaluationService::class)->set($this->a->id,$commercial,
            ['marked'=>true,'expected_marked'=>false,'expected_version'=>0,'request_key'=>(string)Str::uuid()]);
    }
    private function race(string $first,string $second,bool $snapshot=false,bool $sameKey=false):array{
        $dir=storage_path('app/qa-episode-race');if(!is_dir($dir))mkdir($dir,0700,true);$run=$dir.'/'.Str::uuid();$payload=$this->payload(['reason'=>'Decisión ficticia del paciente']);$workers=[];$jobs=[];
        foreach([$first,$second] as $i=>$action){
            $job=$run.'-'.$i.'.json';$jobs[]=$job;$d=['id'=>$this->a->id,'actor'=>$action==='resume'?$this->medical->id:$this->actor->id,
                'action'=>$action,'payload'=>$sameKey?$payload:$this->payload(['reason'=>'Decisión ficticia del paciente']),'release'=>$run.'.release'];
            if($snapshot)$d+=['snapshot'=>$run.'.snapshot','committed'=>$run.'.committed','stale_snapshot'=>$i===1];
            file_put_contents($job,json_encode($d));$pipes=[];$env=getenv();$env['AGENDA_V2_QA_RUNTIME']='agenda-v2-11.8.9';
            $proc=proc_open([PHP_BINARY,base_path('tests/Support/admission-episode-worker.php'),$job],[1=>['pipe','w'],2=>['pipe','w']],$pipes,base_path(),$env);$this->assertIsResource($proc);$workers[]=[$proc,$pipes];
        }
        $deadline=microtime(true)+20;while(!file_exists($jobs[0].'.ready')||!file_exists($jobs[1].'.ready')){if(microtime(true)>$deadline)$this->fail('Worker timeout');usleep(10000);}touch($run.'.release');
        $results=[];foreach($workers as $i=>[$proc,$pipes]){$stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);foreach($pipes as $p)fclose($p);file_put_contents($jobs[$i].'.output',$stdout.$stderr);$this->assertSame(0,proc_close($proc),$stderr);$results[]=json_decode($stdout,true,512,JSON_THROW_ON_ERROR);}
        return array_column($results,'status');
    }
    public function test_stale_repeatable_read_cannot_decline_after_committed_second_attention():void{
        $this->ready();$money=DB::table('payments')->get()->toJson();$this->assertSame([200,422],$this->race('resume','decline',true));
        $this->assertSame('EN_ATENCION',$this->a->fresh()->estado_cita);$this->assertSame(1,AppointmentEvent::where('event_type',R::MEDICAL_RESUMED)->count());
        $this->assertSame(0,AppointmentEvent::where('event_type',R::PATIENT_DECLINED)->count());$this->assertSame($money,DB::table('payments')->get()->toJson());
    }
    public function test_simultaneous_resume_and_decline_choose_one_outcome():void{
        $this->ready();$statuses=$this->race('resume','decline');$this->assertSame(1,count(array_filter($statuses,fn($s)=>$s===200)));
        foreach($statuses as $s)$this->assertContains($s,[200,409,422]);
        $this->assertSame(1,AppointmentEvent::whereIn('event_type',[R::MEDICAL_RESUMED,R::PATIENT_DECLINED])->count());$this->assertDatabaseCount('appointments',1);$this->assertDatabaseCount('payments',1);
    }
    public function test_double_confirmation_has_one_audit_event():void{
        $this->assertSame([200,200],$this->race('confirm','confirm',false,true));$this->assertSame(1,AppointmentEvent::where('event_type',R::OPERATIONAL_CONFIRMED)->count());
    }
    public function test_double_decline_is_idempotent_without_second_review():void{
        $this->assertSame([200,200],$this->race('decline','decline',false,true));$this->assertSame(1,AppointmentEvent::where('event_type',R::PATIENT_DECLINED)->count());
        $this->assertSame(0,AppointmentEvent::where('event_type',R::MEDICAL_RESUMED)->count());$this->assertDatabaseCount('payments',1);
    }
}
