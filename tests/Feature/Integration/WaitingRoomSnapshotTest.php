<?php
namespace Tests\Feature\Integration;

use Tests\TestCase;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use App\Services\Scheduling\AdmissionCareService;
use App\Support\Scheduling\{SchedulingCapability as C,ClinicalCapability};

class WaitingRoomSnapshotTest extends TestCase
{
    use RefreshDatabase, BuildsAgendaLifecycleData;
    private $actor,$catalog,$site,$a,$configuration,$path;
    private string $secret='fixture-only-service-secret-not-for-production-000000';
    private string $endpoint='/api/integration/v1/waiting-room/snapshot';
    protected function setUp():void
    {
        parent::setUp();
        config(['waiting_room.enabled'=>true,'waiting_room.allow_loopback_http'=>true,
            'waiting_room.reference_key'=>str_repeat('r',64),'scheduling.enabled'=>true]);
        $this->actor=$this->agendaReader('ADMISION');
        $this->actor->givePermissionTo(Permission::findOrCreate(C::UPDATE,'web'));
        $this->catalog=$this->createAppointmentCatalog();$this->site=$this->createSite(['nombre'=>'Sede ficticia']);
        $patient=$this->createPatient($this->actor,['nombre'=>'María','apellido_paterno'=>'López','apellido_materno'=>'Torres',
            'numero_identidad'=>'93123456','historia_clinica'=>'HISTORIA_PRIVADA_E2E','email'=>'contacto-privado@example.invalid']);
        $this->a=$this->lifecycleAppointment($this->actor,$this->catalog,['site_id'=>$this->site->id,'attendance_mode'=>'PRESENCIAL',
            'observaciones'=>'OBSERVACION_PRIVADA_E2E','motivo_consulta'=>'MOTIVO_PRIVADO_E2E'], $patient);
        $this->configuration=['installations'=>['fixture-a'=>['active'=>true,'secret'=>$this->secret,'site_id'=>$this->site->id,'screen_ref'=>'fixture-screen-a']],
            'privacy'=>['mode'=>'fixtures-only','display_mode'=>'initial_two_surnames','speech_mode'=>'full_given_names','fixture_patient_ids'=>[$patient->id]],
            'assignments'=>[['site_id'=>$this->site->id,'day'=>now('America/Lima')->toDateString(),'verified'=>true,
                'room_ref'=>'physical-room-demo-a','room_label'=>'01','doctor_id'=>$this->catalog['doctor']->id,
                'professional_label'=>'Dra. Valeria Torres','professional_speech_label'=>'la doctora Valeria Torres']]];
        $this->path=storage_path('framework/wr-'.Str::uuid().'.json');config(['waiting_room.configuration_file'=>$this->path]);$this->saveConfiguration();
    }
    protected function tearDown():void {if($this->path&&is_file($this->path))unlink($this->path);parent::tearDown();}
    private function saveConfiguration():void {file_put_contents($this->path,json_encode($this->configuration));}
    private function headers(?string $nonce=null,?int $timestamp=null,string $uri=''):array
    {
        $uri=$uri?:$this->endpoint;$nonce=$nonce??bin2hex(random_bytes(16));$timestamp=$timestamp??time();
        $canonical=implode(chr(10),['GET',$uri,$timestamp,$nonce,hash('sha256','[]')]);
        return ['X-WR-Key'=>'fixture-a','X-WR-Timestamp'=>(string)$timestamp,'X-WR-Nonce'=>$nonce,
            'X-WR-Signature'=>hash_hmac('sha256',$canonical,$this->secret)];
    }
    private function snapshot() {return $this->getJson($this->endpoint,$this->headers());}
    private function arrive():void
    {
        $this->a=app(AdmissionCareService::class)->registerArrival($this->a->id,$this->actor,
            ['expected_state'=>$this->a->estado_cita,'request_key'=>(string)Str::uuid(),'confirmed'=>true]);
    }
    private function callPatient():void
    {
        $this->a=app(AdmissionCareService::class)->transition($this->a->id,$this->actor,
            ['state'=>'LLAMANDO','expected_state'=>$this->a->estado_cita,'request_key'=>(string)Str::uuid()]);
    }
    public function test_official_arrival_call_and_clinical_start_end_are_projected_without_private_data():void
    {
        $this->snapshot()->assertOk()->assertJsonCount(0,'calls')->assertJsonCount(0,'rooms');
        $this->arrive();$this->snapshot()->assertOk()->assertJsonPath('rooms.0.patients.0.state','waiting')->assertJsonCount(0,'calls');
        $this->callPatient();$r=$this->snapshot()->assertOk()->assertJsonPath('calls.0.display_label','M. López Torres')
            ->assertJsonPath('calls.0.speech_label','María López Torres')->assertJsonPath('calls.0.room_label','01');
        $reference=$r->json('calls.0.event_ref');$this->assertMatchesRegularExpression('/^event_[a-f0-9]{64}$/D',$reference);
        foreach(['93123456','HISTORIA_PRIVADA_E2E','contacto-privado','OBSERVACION_PRIVADA_E2E','MOTIVO_PRIVADO_E2E','patient_id','appointment_id','metadata','saldo','precio','secret'] as $private)$this->assertStringNotContainsString($private,$r->getContent());
        DB::table('appointments')->where('id',$this->a->id)->update(['updated_at'=>now()->addSecond()]);
        $this->snapshot()->assertOk()->assertJsonPath('calls.0.event_ref',$reference);
        $doctor=$this->createUserWithRole('MEDICO',['doctor_id'=>$this->catalog['doctor']->id]);
        foreach(ClinicalCapability::rolePermissions() as $cap){Permission::findOrCreate($cap,'web');$doctor->givePermissionTo($cap);}
        foreach(['EN_ATENCION','ATENDIDO'] as $state){
            $this->a=app(AdmissionCareService::class)->transition($this->a->id,$doctor,['state'=>$state,'expected_state'=>$this->a->estado_cita,'request_key'=>(string)Str::uuid()]);
            $result=$this->snapshot()->assertOk()->assertJsonCount(0,'calls');
            if($state==='EN_ATENCION')$result->assertJsonPath('rooms.0.patients.0.state','attention');else $result->assertJsonCount(0,'rooms');
        }
        $this->assertNull($this->a->hora_salida);
    }
    public function test_signature_timestamp_replay_revocation_and_disabled_gate():void
    {
        $this->getJson($this->endpoint)->assertUnauthorized();
        $this->getJson($this->endpoint,$this->headers(null,time()-100))->assertUnauthorized();
        $headers=$this->headers();$this->getJson($this->endpoint,$headers)->assertOk();$this->getJson($this->endpoint,$headers)->assertUnauthorized();
        $headers=$this->headers();$headers['X-WR-Signature']=str_repeat('0',64);$this->getJson($this->endpoint,$headers)->assertUnauthorized();
        $headers=$this->headers();$headers['Origin']='https://untrusted.invalid';$this->getJson($this->endpoint,$headers)->assertUnauthorized();
        $this->configuration['installations']['fixture-a']['active']=false;$this->saveConfiguration();$this->snapshot()->assertUnauthorized();
        config(['waiting_room.enabled'=>false]);$this->snapshot()->assertNotFound();
    }
    public function test_site_scope_private_reservations_and_teleconsultations_fail_closed():void
    {
        $this->arrive();$this->callPatient();
        $this->getJson($this->endpoint.'?site_id=999',$this->headers(null,null,$this->endpoint.'?site_id=999'))->assertStatus(422);
        foreach([['attendance_mode'=>'TELECONSULTA'],['site_id'=>$this->createSite(['codigo'=>'OTHER'])->id],['estado_agenda'=>'PENDIENTE_CONFIRMACION'],['fecha_cita'=>now()->subDay()->toDateString()],['estado_cita'=>'CANCELADO'],['estado_cita'=>'NO_ASISTIO']] as $change){
            $before=$this->a->fresh()->getAttributes();DB::table('appointments')->where('id',$this->a->id)->update($change);
            $this->snapshot()->assertOk()->assertJsonCount(0,'calls')->assertJsonCount(0,'rooms');
            DB::table('appointments')->where('id',$this->a->id)->update($before);
        }
    }
    public function test_a_timestamp_without_an_audited_arrival_is_not_waiting():void
    {
        DB::table('appointments')->where('id',$this->a->id)->update(['estado_cita'=>'EN_ESPERA','hora_llegada'=>now()]);
        $this->snapshot()->assertOk()->assertJsonCount(0,'rooms');
    }
    public function test_reversal_keeps_audit_and_does_not_resurrect_the_old_call():void
    {
        $this->arrive();$this->callPatient();$first=$this->snapshot()->json('calls.0.event_ref');
        $last=$this->a->events()->latest('id')->first();$count=$this->a->events()->count();
        $this->a=app(AdmissionCareService::class)->reverse($this->a->id,$this->actor,['expected_state'=>'LLAMANDO',
            'expected_event_id'=>$last->id,'request_key'=>(string)Str::uuid(),'motivo'=>'Corrección ficticia']);
        $this->snapshot()->assertOk()->assertJsonCount(0,'calls')->assertJsonPath('rooms.0.patients.0.state','waiting');
        $this->assertSame($count+1,$this->a->events()->count());$this->callPatient();$this->assertNotSame($first,$this->snapshot()->json('calls.0.event_ref'));
    }
    public function test_ambiguous_homonyms_are_not_disambiguated_with_private_identifiers():void
    {
        $this->arrive();$patient=$this->createPatient($this->actor,['nombre'=>'Marta','apellido_paterno'=>'López','apellido_materno'=>'Torres','numero_identidad'=>'93123457','historia_clinica'=>'HOMO-2']);
        $other=$this->lifecycleAppointment($this->actor,$this->catalog,['site_id'=>$this->site->id,'attendance_mode'=>'PRESENCIAL','hora_cita'=>'10:30:00'],$patient);
        $this->configuration['privacy']['fixture_patient_ids'][]=$patient->id;$this->saveConfiguration();
        app(AdmissionCareService::class)->registerArrival($other->id,$this->actor,['expected_state'=>'PROGRAMADO','request_key'=>(string)Str::uuid()]);
        $this->snapshot()->assertOk()->assertJsonCount(0,'rooms')->assertJsonCount(0,'calls');
    }
    public function test_missing_destination_or_privacy_approval_never_exposes_names():void
    {
        $this->arrive();$this->configuration['assignments']=[];$this->saveConfiguration();$this->snapshot()->assertStatus(503);
        $this->configuration['privacy']=[];$this->saveConfiguration();$this->snapshot()->assertStatus(503);
    }
    public function test_reevaluation_indication_is_not_physical_presence_or_a_new_call():void
    {
        $this->arrive();$this->callPatient();$doctor=$this->createUserWithRole('MEDICO',['doctor_id'=>$this->catalog['doctor']->id]);
        foreach(ClinicalCapability::rolePermissions() as $cap)$doctor->givePermissionTo(Permission::findOrCreate($cap,'web'));
        $this->a=app(AdmissionCareService::class)->transition($this->a->id,$doctor,['state'=>'EN_ATENCION','expected_state'=>'LLAMANDO','request_key'=>(string)Str::uuid()]);
        $this->snapshot()->assertOk()->assertJsonPath('rooms.0.patients.0.state','attention')->assertJsonCount(0,'calls');
        $service=app(\App\Services\Scheduling\AppointmentReevaluationService::class);
        $service->physicianDecision($this->a->id,$doctor,['action'=>'indicate','expected_state'=>'EN_ATENCION','confirmed'=>true,'request_key'=>(string)Str::uuid()]);
        $this->snapshot()->assertOk()->assertJsonCount(0,'rooms')->assertJsonCount(0,'calls');
        $service->physicianDecision($this->a->id,$doctor,['action'=>'withdraw','expected_state'=>'EN_ATENCION','confirmed'=>true,'reason'=>'Corrección ficticia','request_key'=>(string)Str::uuid()]);
        $this->snapshot()->assertOk()->assertJsonPath('rooms.0.patients.0.state','attention')->assertJsonCount(0,'calls');
        $this->assertSame('EN_ATENCION',$this->a->fresh()->estado_cita);$this->assertDatabaseCount('appointments',1);
    }
    public function test_simultaneous_calls_keep_audited_event_order_instead_of_room_or_hash_order():void
    {
        $this->freezeTime();$this->arrive();$catalog=$this->createAppointmentCatalog();$p=$this->createPatient($this->actor,['nombre'=>'Carlos','apellido_paterno'=>'Ramírez','apellido_materno'=>'Díaz','numero_identidad'=>'93123002','historia_clinica'=>'PRIVATE-2']);
        $other=$this->lifecycleAppointment($this->actor,$catalog,['site_id'=>$this->site->id,'attendance_mode'=>'PRESENCIAL'],$p);
        $this->configuration['privacy']['fixture_patient_ids'][]=$p->id;$assignment=$this->configuration['assignments'][0];$assignment['doctor_id']=$catalog['doctor']->id;$assignment['room_ref']='fixture-room-2';$assignment['room_label']='02';$this->configuration['assignments'][]=$assignment;$this->saveConfiguration();
        $service=app(AdmissionCareService::class);$service->registerArrival($other->id,$this->actor,['expected_state'=>'PROGRAMADO','request_key'=>(string)Str::uuid()]);
        $service->transition($other->id,$this->actor,['state'=>'LLAMANDO','expected_state'=>'EN_ESPERA','request_key'=>(string)Str::uuid()]);$this->callPatient();
        $this->snapshot()->assertOk()->assertJsonCount(2,'calls')->assertJsonPath('calls.0.display_label','C. Ramírez Díaz')->assertJsonPath('calls.1.display_label','M. López Torres');
    }

}
