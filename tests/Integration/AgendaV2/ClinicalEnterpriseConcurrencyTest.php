<?php
namespace Tests\Integration\AgendaV2;
use App\Models\{Appointment,User};
use App\Support\Scheduling\ClinicalCapability;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\{Role,Permission};
use Symfony\Component\Process\Process;
use Tests\Concerns\BuildsAgendaLifecycleData;

class ClinicalEnterpriseConcurrencyTest extends \Tests\TestCase
{
    use BuildsAgendaLifecycleData;
    private $doctor,$operator,$a;
    protected function setUp(): void {
        parent::setUp();if(DB::getDriverName()!=='mysql')$this->markTestSkipped('Real locking requires the owned MariaDB runtime.');
        $this->artisan('migrate:fresh',['--force'=>true])->assertExitCode(0);config(['scheduling.enabled'=>true]);
        $this->operator=$this->agendaReader('COMERCIAL');$c=$this->createAppointmentCatalog();
        foreach(ClinicalCapability::all()as $p)Permission::findOrCreate($p,'web');Role::findOrCreate('MEDICO','web')->syncPermissions(ClinicalCapability::all());
        $this->doctor=$this->createUserWithRole('MEDICO');$this->doctor->forceFill(['doctor_id'=>$c['doctor']->id])->save();
        $this->a=$this->lifecycleAppointment($this->operator,$c,['fecha_cita'=>now('America/Lima')->toDateString(),'estado_agenda'=>'CONFIRMADA','estado_cita'=>'EN_ATENCION','hora_llegada'=>now(),'hora_atencion'=>now()]);
    }
    private function race(array $requests): array {
        $dir=storage_path('app/qa-v2/races/'.Str::uuid());mkdir($dir,0777,true);$jobs=[];
        foreach($requests as $i=>$r){$out=$dir.'/'.$i.'.json';$in=$dir.'/'.$i.'.input.json';file_put_contents($in,json_encode($r+['release'=>$dir.'/release']));$p=new Process([PHP_BINARY,base_path('tests/Support/agenda-v2-request.php'),$in,$out],base_path(),null,null,40);$p->start();$jobs[]=[$p,$out];}
        try{$deadline=microtime(true)+20;do{$ready=true;foreach($jobs as[$p,$out])$ready=$ready&&file_exists($out.'.ready');if($ready)break;usleep(10000);}while(microtime(true)<$deadline);$this->assertTrue($ready,'Workers must reach the barrier.');touch($dir.'/release');$statuses=[];foreach($jobs as[$p,$out]){$p->wait();$this->assertTrue($p->isSuccessful(),$p->getErrorOutput());$statuses[]=json_decode(file_get_contents($out),true)['status'];}sort($statuses);return $statuses;}
        finally{foreach($jobs as[$p])if($p->isRunning())$p->stop();}
    }
    private function selection(?string $uuid=null): array {return ['actor'=>$this->doctor->id,'path'=>'/scheduling-mvp/admission/appointments/'.$this->a->id.'/procedures/selection','payload'=>['request_key'=>$uuid??(string)Str::uuid(),'expected_version'=>0,'procedures'=>['mri','surgery'],'custom_procedures'=>[]]];}
    public function test_parallel_selection_replay_creates_only_one_followup_for_each_source(): void {
        $r=$this->selection();$this->assertSame([200,200],$this->race([$r,$r]));$this->assertDatabaseCount('medical_procedure_followups',2);$this->assertDatabaseCount('medical_procedure_followup_events',2);$this->assertDatabaseCount('appointment_procedure_events',1);$this->assertDatabaseCount('appointments',1);$this->assertDatabaseCount('payments',0);
    }
    public function test_parallel_different_selection_requests_reject_stale_version(): void {
        $this->assertSame([200,409],$this->race([$this->selection(),$this->selection()]));$this->assertDatabaseCount('appointment_procedure_events',1);$this->assertDatabaseCount('medical_procedure_followups',2);
    }
    public function test_parallel_alert_replay_and_version_conflicts_never_duplicate_a_patient_alert(): void {
        $r=['actor'=>$this->operator->id,'path'=>'/scheduling-mvp/admission/appointments/'.$this->a->id.'/patient-alert','payload'=>['request_key'=>(string)Str::uuid(),'expected_version'=>0,'expected_patient_id'=>$this->a->patient_id,'action'=>'save','confirmed'=>true,'facts_confirmed'=>true,'category'=>'communication','incident_on'=>now('America/Lima')->toDateString(),'reason'=>'Hechos ficticios observables durante la prueba concurrente.']];
        $this->assertSame([200,200],$this->race([$r,$r]));$this->assertDatabaseCount('patient_operational_alerts',1);$this->assertDatabaseCount('patient_operational_alert_events',1);
        $r['payload']['expected_version']=1;$r['payload']['request_key']=(string)Str::uuid();$s=$r;$s['payload']['request_key']=(string)Str::uuid();$s['payload']['reason']='Segundo hecho ficticio verificable con una versión concurrente.';
        $this->assertSame([200,409],$this->race([$r,$s]));$this->assertDatabaseCount('patient_operational_alert_events',2);
    }
    public function test_followup_update_racing_source_withdrawal_preserves_source_and_history(): void {
        $r=$this->selection();$this->actingAs($this->doctor)->postJson($r['path'],$r['payload'])->assertOk();$id=DB::table('medical_procedure_followups')->where('procedure_family','mri')->value('id');
        $withdraw=$this->selection();$withdraw['payload']['expected_version']=1;$withdraw['payload']['procedures']=[];
        $update=['actor'=>$this->doctor->id,'method'=>'PATCH','path'=>'/scheduling-mvp/medical-followups/'.$id,'payload'=>['request_key'=>(string)Str::uuid(),'expected_version'=>1,'status'=>'COORDINATING','reason'=>'Coordinación ficticia verificada concurrentemente.','confirmed'=>true]];
        $statuses=$this->race([$withdraw,$update]);$this->assertContains($statuses,[[200,200],[200,409]]);$this->assertDatabaseHas('medical_procedure_followups',['id'=>$id,'source_active'=>false]);$this->assertDatabaseCount('appointments',1);$this->assertDatabaseCount('payments',0);$this->assertDatabaseCount('medical_procedure_followups',2);
    }
    public function test_parallel_personal_requests_share_one_private_reference_and_keep_independent_orders(): void {
        $b=$this->lifecycleAppointment($this->operator,$this->createAppointmentCatalog(),['doctor_id'=>$this->doctor->doctor_id,'estado_agenda'=>'CONFIRMADA','estado_cita'=>'EN_ATENCION','hora_llegada'=>now(),'hora_atencion'=>now()]);
        $r=$this->selection();$r['payload']['procedures']=[];$r['payload']['custom_procedures']=[['id'=>(string)Str::uuid(),'label'=>'Radiografía ficticia de clavícula izquierda','family'=>'radiography']];
        $s=$r;$s['path']='/scheduling-mvp/admission/appointments/'.$b->id.'/procedures/selection';$s['payload']['request_key']=(string)Str::uuid();$s['payload']['custom_procedures'][0]['id']=(string)Str::uuid();$s['payload']['custom_procedures'][0]['label']='RADIOGRAFIA  FICTICIA DE CLAVICULA IZQUIERDA';
        $this->assertSame([200,200],$this->race([$r,$s]));$this->assertDatabaseCount('doctor_procedure_suggestions',1);$this->assertDatabaseCount('appointment_requested_procedures',2);$this->assertSame(1,DB::table('appointment_requested_procedures')->distinct()->count('reusable_procedure_id'));$this->assertDatabaseCount('payments',0);
    }
    private function generalReferral(): array {return ['actor'=>$this->doctor->id,'path'=>'/scheduling-mvp/admission/appointments/'.$this->a->id.'/medical-followups','payload'=>['request_key'=>(string)Str::uuid(),'expected_patient_id'=>$this->a->patient_id,'expected_procedure_version'=>0,'expected_followup_versions'=>['general'=>0],'procedures'=>[],'general'=>true,'reason'=>'Seguimiento general ficticio, sin procedimiento ni cobro.','confirmed'=>true]];}
    public function test_parallel_manual_replay_has_only_one_general_followup_and_event():void {
        $r=$this->generalReferral();$this->assertSame([200,200],$this->race([$r,$r]));$this->assertDatabaseCount('medical_procedure_followups',1);$this->assertDatabaseCount('medical_procedure_followup_events',1);$this->assertDatabaseCount('appointment_requested_procedures',0);$this->assertDatabaseCount('payments',0);
    }
    public function test_parallel_manual_referrals_with_stale_versions_conflict_without_duplicates():void {
        $this->assertSame([200,409],$this->race([$this->generalReferral(),$this->generalReferral()]));$this->assertDatabaseCount('medical_procedure_followups',1);$this->assertDatabaseCount('medical_procedure_followup_events',1);
    }
}
