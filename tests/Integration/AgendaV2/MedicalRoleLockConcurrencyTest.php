<?php
namespace Tests\Integration\AgendaV2;

use App\Models\User;
use App\Support\Scheduling\ClinicalCapability as K;
use App\Http\Controllers\admin\master\role\RoleController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\{Role,Permission};
use Symfony\Component\Process\Process;

class MedicalRoleLockConcurrencyTest extends \Tests\TestCase
{
    use \Tests\Concerns\BuildsAgendaLifecycleData;
    private $admin,$doctor,$a,$role;
    protected function setUp():void {
        parent::setUp();if(DB::getDriverName()!=='mysql')$this->markTestSkipped('Requires owned MariaDB and real row locks.');
        $this->artisan('migrate:fresh',['--force'=>true])->assertExitCode(0);config(['scheduling.enabled'=>true]);
        $this->admin=$this->agendaReader('ADMINISTRADOR');$catalog=$this->createAppointmentCatalog();foreach(K::rolePermissions()as $p)Permission::findOrCreate($p,'web');$this->role=Role::findOrCreate('MEDICO 2');$this->role->syncPermissions(K::rolePermissions());
        $this->doctor=User::forceCreate(['id'=>13,'name'=>'MEDICO DOS FICTICIO CONCURRENCIA','email'=>'medico13@demo.ceosalud.invalid','password'=>'unused-fixture-hash','is_active'=>true,'doctor_id'=>$catalog['doctor']->id]);$this->doctor->assignRole($this->role);
        $this->a=$this->lifecycleAppointment($this->admin,$catalog,['fecha_cita'=>now('America/Lima')->toDateString(),'estado_agenda'=>'CONFIRMADA']);$this->a->forceFill(['estado_cita'=>'EN_ESPERA','hora_llegada'=>now()])->save();
    }
    private function race(array $requests):array {
        $dir=storage_path('app/qa-v2/races/'.Str::uuid());mkdir($dir,0777,true);$jobs=[];
        foreach($requests as $i=>$r){$out=$dir.'/'.$i.'.json';$in=$dir.'/'.$i.'.input.json';file_put_contents($in,json_encode($r+['release'=>$dir.'/release']));$p=new Process([PHP_BINARY,base_path('tests/Support/agenda-v2-request.php'),$in,$out],base_path(),null,null,40);$p->start();$jobs[]=[$p,$out];}
        try{$deadline=microtime(true)+20;do{$ready=true;foreach($jobs as[$p,$out])$ready=$ready&&file_exists($out.'.ready');if($ready)break;usleep(10000);}while(microtime(true)<$deadline);$this->assertTrue($ready,'Workers must reach the barrier');touch($dir.'/release');$statuses=[];foreach($jobs as[$p,$out]){$p->wait();$this->assertTrue($p->isSuccessful(),$p->getErrorOutput());$statuses[]=json_decode(file_get_contents($out),true)['status'];}sort($statuses);return $statuses;}
        finally{foreach($jobs as[$p])if($p->isRunning())$p->stop();}
    }
    public function test_medico_two_parallel_start_replay_has_one_medical_signature():void {
        $r=['actor'=>13,'path'=>'/scheduling-mvp/admission/appointments/'.$this->a->id.'/care','payload'=>['state'=>'EN_ATENCION','expected_state'=>'EN_ESPERA','request_key'=>(string)Str::uuid()]];
        $this->assertSame([200,200],$this->race([$r,$r]));$this->assertDatabaseCount('appointment_events',1);$this->assertDatabaseHas('appointment_events',['actor_user_id'=>13,'event_type'=>'ADMISION_ESTADO_ACTUALIZADO']);$this->assertNotNull($this->a->fresh()->hora_atencion);$this->assertDatabaseCount('payments',0);
    }
    public function test_role_revocation_and_selection_serialize_without_post_revocation_writes():void {
        $this->a->forceFill(['estado_cita'=>'EN_ATENCION','hora_atencion'=>now()])->save();$payload=['request_key'=>(string)Str::uuid(),'expected_version'=>0,'procedures'=>['laboratory']];
        $select=['actor'=>13,'path'=>'/scheduling-mvp/admission/appointments/'.$this->a->id.'/procedures/selection','payload'=>$payload];
        $revoke=['actor'=>$this->admin->id,'method'=>'PUT','path'=>route('admin.permissions.update',$this->role,false),'payload'=>['name'=>'MEDICO 2','permissions_present'=>1,'permission_revision'=>RoleController::revision($this->role),'permissions'=>$this->role->permissions()->where('name','<>',K::PROCEDURES)->pluck('id')->all()]];
        // The existing role editor answers with its normal redirect on success.
        $this->assertContains($this->race([$select,$revoke]),[[200,302],[302,403]]);$this->assertFalse($this->role->permissions()->where('name',K::PROCEDURES)->exists());$this->assertLessThanOrEqual(1,DB::table('appointment_procedure_events')->count());
        $payload['request_key']=(string)Str::uuid();$payload['expected_version']=(int)DB::table('appointment_operational_reviews')->where('appointment_id',$this->a->id)->value('version');
        $this->actingAs($this->doctor)->postJson($select['path'],$payload)->assertForbidden();$this->assertDatabaseCount('appointments',1);$this->assertDatabaseCount('payments',0);
    }
}
