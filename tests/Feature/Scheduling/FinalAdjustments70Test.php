<?php
namespace Tests\Feature\Scheduling;

use App\Models\{Appointment,AppointmentEvent,Patient};
use App\Services\Scheduling\AppointmentReevaluationService as R;
use App\Support\Scheduling\SchedulingCapability as C;
use App\Support\Patients\PatientWriteAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsRescheduleVisualData;
use Tests\TestCase;

class FinalAdjustments70Test extends TestCase
{
    use RefreshDatabase,BuildsRescheduleVisualData;
    private $actor,$a,$catalog;
    protected function setUp():void {parent::setUp();foreach($this->rescheduleVisualFixture() as $k=>$v)$this->{$k}=$v;\Spatie\Permission\Models\Permission::findOrCreate(C::UPDATE,'web');$this->actingAs($this->actor);}
    protected function tearDown():void {\Carbon\Carbon::setTestNow();parent::tearDown();}
    private function feed(array $extra=[]){return $this->postJson(route('scheduling.mvp.admission.feed'),$extra+['fecha'=>'2026-10-21','site_id'=>null]);}
    private function money(){return collect(['payments','vouchers','voucher_items'])->mapWithKeys(fn($t)=>[$t=>DB::table($t)->get()->toJson()])->all();}
    private function decision($actor,string $action){return $this->actingAs($actor)->postJson(route('scheduling.mvp.admission.physician-reevaluation',[$this->a->id,$action]),[
        'request_key'=>(string)Str::uuid(),'confirmed'=>true,'expected_state'=>'EN_ATENCION']);}
    private function patientData(Patient $p):array{return $p->only(['tipo_identificacion','numero_identidad','nombre','apellido_paterno','apellido_materno','genero'])+['telefono'=>'999000001'];}

    public function test_focus_locates_the_real_appointment_on_its_timeline_page_without_counting_history():void
    {
        $this->catalog['schedule']->update(['hora_fin'=>'16:00']);
        $this->patchJson(route('scheduling.mvp.agenda.appointments.reschedule',$this->a->id),[
            'fecha_cita'=>'2026-10-21','hora_cita'=>'14:00','expected_fecha_cita'=>'2026-10-21','expected_hora_cita'=>'12:20','motivo'=>'Cambio ficticio QA'])->assertOk();
        $event=$this->a->events()->where('event_type','REPROGRAMADA')->sole();
        for($i=0;$i<30;$i++)$event->replicate()->save();
        $before=$this->money();
        $this->feed(['focus_appointment_id'=>$this->a->id])->assertOk()->assertJsonPath('focus_found',true)->assertJsonPath('page',2)
            ->assertJsonPath('total',1)->assertJsonPath('kpis.total',1)->assertJsonCount(1,'rows')->assertJsonPath('rows.0.id',$this->a->id);
        $this->assertDatabaseCount('appointments',1);$this->assertSame($before,$this->money());
    }
    public function test_focus_never_reveals_another_workers_private_reservation():void
    {
        $other=$this->agendaReader('COMERCIAL');$private=$this->lifecycleAppointment($other,$this->catalog,['estado_agenda'=>'PENDIENTE_CONFIRMACION','fecha_cita'=>'2026-10-21']);
        $this->feed(['focus_appointment_id'=>$private->id])->assertOk()->assertJsonPath('focus_found',false)->assertJsonPath('total',1)->assertJsonCount(1,'rows');
        $this->getJson(route('scheduling.mvp.admission.detail',$private->id))->assertNotFound();
    }
    public function test_all_exams_make_an_indicated_case_ready_before_confirmation_without_signing_a_return():void
    {
        $before=$this->money();$this->a->forceFill(['estado_cita'=>'EN_ATENCION','hora_llegada'=>now(),'hora_atencion'=>now()])->save();
        $doctor=$this->createUserWithRole('MEDICO');$doctor->forceFill(['doctor_id'=>$this->a->doctor_id])->save();
        $this->decision($doctor,'indicate')->assertOk();
        $this->postJson(route('scheduling.mvp.admission.procedures',[$this->a->id,'selection']),['request_key'=>(string)Str::uuid(),'expected_version'=>0,
            'procedures'=>['radiography','laboratory','wound_care']])->assertOk();
        $this->actingAs($this->actor);
        foreach(['radiography','laboratory','wound_care'] as $i=>$key){
            $p=['request_key'=>(string)Str::uuid(),'expected_version'=>$i+1,'procedure'=>$key,'completed'=>true];
            $this->postJson(route('scheduling.mvp.admission.procedures',[$this->a->id,'completion']),$p)->assertOk();
            $this->postJson(route('scheduling.mvp.admission.procedures',[$this->a->id,'completion']),$p)->assertOk();
            $this->feed()->assertJsonPath('kpis.reevaluaciones',$i===2?1:0)->assertJsonPath('kpis.reevaluaciones_pendientes',$i===2?0:1);
        }
        $this->getJson(route('scheduling.mvp.admission.detail',$this->a->id))->assertOk()->assertJsonPath('can_confirm_operational_reevaluation',true)
            ->assertJsonPath('medical_reevaluation.ready',true)->assertJsonPath('medical_reevaluation.confirmed',false)
            ->assertJsonPath('appointment.presentation.clave','LISTO_REEVALUACION');
        $this->feed(['only_pending_reevaluations'=>true])->assertJsonPath('total',0)->assertJsonPath('kpis.reevaluaciones',1);
        $this->feed(['only_reevaluations'=>true])->assertJsonPath('total',1)->assertJsonPath('rows.0.reevaluation.awaiting_confirmation',true);
        $this->getJson(route('scheduling.mvp.admission.reevaluations'))->assertJsonCount(1,'groups.active')->assertJsonCount(0,'groups.pre_pending')->assertJsonCount(0,'groups.pending');
        $this->decision($doctor,'resume')->assertUnprocessable();
        $this->assertSame(0,AppointmentEvent::where('event_type',R::MEDICAL_RESUMED)->count());
        $this->actingAs($this->actor)->postJson(route('scheduling.mvp.admission.procedures',[$this->a->id,'completion']),[
            'request_key'=>(string)Str::uuid(),'expected_version'=>4,'procedure'=>'wound_care','completed'=>false])->assertOk();
        $this->feed()->assertJsonPath('kpis.reevaluaciones',0)->assertJsonPath('kpis.reevaluaciones_pendientes',1);
        $this->postJson(route('scheduling.mvp.admission.procedures',[$this->a->id,'completion']),[
            'request_key'=>(string)Str::uuid(),'expected_version'=>5,'procedure'=>'wound_care','completed'=>true])->assertOk();
        $this->actingAs($this->actor)->postJson(route('scheduling.mvp.admission.confirm-reevaluation',$this->a->id),[
            'request_key'=>(string)Str::uuid(),'confirmed'=>true,'expected_state'=>'EN_ATENCION'])->assertOk();
        $this->decision($doctor,'resume')->assertOk();
        $this->postJson(route('scheduling.mvp.admission.care',$this->a->id),['state'=>'ATENDIDO','expected_state'=>'EN_ATENCION','request_key'=>(string)Str::uuid()])->assertOk();
        $this->assertSame($before,$this->money());$this->assertDatabaseCount('appointments',1);
    }
    public function test_complete_exams_remain_ready_when_a_prior_procedural_authorization_keeps_its_audited_origin():void
    {
        $this->a->forceFill(['estado_cita'=>'EN_ATENCION','hora_llegada'=>now(),'hora_atencion'=>now()])->save();
        $doctor=$this->createUserWithRole('MEDICO');$doctor->forceFill(['doctor_id'=>$this->a->doctor_id])->save();
        $this->actingAs($doctor);
        $this->postJson(route('scheduling.mvp.admission.procedures',[$this->a->id,'selection']),['request_key'=>(string)Str::uuid(),'expected_version'=>0,
            'procedures'=>['radiography','laboratory']])->assertOk();
        $this->actingAs($this->actor);
        $this->postJson(route('scheduling.mvp.admission.procedures',[$this->a->id,'completion']),[
            'request_key'=>(string)Str::uuid(),'expected_version'=>1,'procedure'=>'radiography','completed'=>true])->assertOk();
        $this->postJson(route('scheduling.mvp.admission.procedures',[$this->a->id,'send']),[
            'request_key'=>(string)Str::uuid(),'expected_version'=>2,'confirmed'=>true,'reason'=>'Retorno con examen aún pendiente'])->assertOk();
        $this->decision($doctor,'indicate')->assertOk();
        $this->actingAs($this->actor);
        $this->feed()->assertJsonPath('kpis.reevaluaciones',0)->assertJsonPath('kpis.reevaluaciones_pendientes',1);
        $this->postJson(route('scheduling.mvp.admission.procedures',[$this->a->id,'completion']),[
            'request_key'=>(string)Str::uuid(),'expected_version'=>3,'procedure'=>'laboratory','completed'=>true])->assertOk();
        $this->getJson(route('scheduling.mvp.admission.detail',$this->a->id))->assertJsonPath('medical_reevaluation.ready',true)
            ->assertJsonPath('can_confirm_operational_reevaluation',true)->assertJsonPath('procedures.review.origin','MANUAL');
        $this->feed()->assertJsonPath('kpis.reevaluaciones',1)->assertJsonPath('kpis.reevaluaciones_pendientes',0);
    }
    public function test_non_creators_cannot_change_patient_or_appointment_details_even_with_update_permission():void
    {
        $before=$this->money();$p=$this->a->patient;$original=$p->getAttributes();$a=$this->a->getAttributes();
        foreach(['ADMISION','COMERCIAL'] as $role){$other=$this->agendaReader($role);$other->givePermissionTo([C::CREATE,C::UPDATE]);$this->actingAs($other);
            $this->patchJson(route('scheduling.mvp.agenda.notes',$this->a->id),['motivo_consulta'=>'Intento ajeno'])->assertForbidden();
            $this->putJson(route('patients.operational.update',$p->id),$this->patientData($p)+['observaciones'=>'Intento'])->assertForbidden();
            $this->patchJson(route('patients.operational.agenda-contact',$p->id),['telefono'=>'999123456','appointment_id'=>$this->a->id])->assertForbidden();
            $this->getJson(route('patients.operational.editor',$p->id).'?appointment_id='.$this->a->id)->assertOk()->assertJsonPath('can_write',false)
                ->assertJsonPath('write_restriction',PatientWriteAccess::OWNERSHIP_MESSAGE);
            $this->getJson(route('patients.operational.show',$p->id))->assertOk()->assertJsonPath('patient.can_write',false);
            $this->putJson(route('admissionit.patient.update'),['id'=>$p->id,'nombre_paciente_edit'=>$p->nombre,'apellido_paterno_edit'=>$p->apellido_paterno,
                'apellido_materno_edit'=>$p->apellido_materno,'genero_paciente_edit'=>$p->genero,'tipo_identificacion_edit'=>$p->tipo_identificacion,
                'numero_identidad_edit'=>$p->numero_identidad])->assertForbidden();
        }
        $this->assertSame($original,$p->fresh()->getAttributes());$this->assertSame($a,$this->a->fresh()->getAttributes());$this->assertSame($before,$this->money());
    }
    public function test_creator_can_edit_own_notes_without_granting_commercial_general_update():void
    {
        $commercial=$this->agendaReader('COMERCIAL');$commercial->givePermissionTo(C::CREATE);
        $this->a->update(['user_id'=>$commercial->id]);$this->a->patient->update(['user_id'=>$commercial->id]);$this->actingAs($commercial);
        $this->assertFalse($commercial->can(C::UPDATE));
        $this->patchJson(route('scheduling.mvp.agenda.notes',$this->a->id),['motivo_consulta'=>'Motivo ficticio del creador','observaciones'=>'Observación ficticia'])->assertOk();
        $this->putJson(route('patients.operational.update',$this->a->patient_id),$this->patientData($this->a->patient))->assertOk();
        $this->assertDatabaseHas('appointment_events',['appointment_id'=>$this->a->id,'event_type'=>'DATOS_OPERATIVOS_ACTUALIZADOS','actor_user_id'=>$commercial->id]);
        $this->assertFalse($commercial->fresh()->can(C::UPDATE));
    }
    public function test_commercial_assignment_does_not_transfer_creation_ownership():void
    {
        $other=$this->agendaReader('ADMISION');$other->givePermissionTo(C::UPDATE);$this->a->update(['responsible_user_id'=>$other->id]);
        $this->actingAs($other)->patchJson(route('scheduling.mvp.agenda.notes',$this->a->id),['observaciones'=>'No es el creador'])->assertForbidden();
        $this->actingAs($this->actor)->patchJson(route('scheduling.mvp.agenda.notes',$this->a->id),['observaciones'=>'Soy el creador'])->assertOk();
    }
    public function test_patient_creator_cannot_edit_through_an_appointment_created_by_someone_else():void
    {
        $other=$this->agendaReader('COMERCIAL');$this->a->update(['user_id'=>$other->id]);$p=$this->a->patient;
        $this->putJson(route('patients.operational.update',$p->id),$this->patientData($p)+['appointment_id'=>$this->a->id,
            'expected_revision'=>\App\Services\Patients\PatientRecordRevision::for($p)])->assertForbidden();
        $this->getJson(route('patients.operational.show',$p->id).'?appointment_id='.$this->a->id)->assertOk()->assertJsonPath('patient.can_write',false);
        $this->putJson(route('patients.operational.update',$p->id),$this->patientData($p))->assertOk();
    }
    public function test_existing_administrator_access_remains_explicit_without_changing_ownership():void
    {
        $admin=$this->agendaReader('ADMINISTRADOR');$admin->givePermissionTo(C::UPDATE);$this->actingAs($admin);
        $this->patchJson(route('scheduling.mvp.agenda.notes',$this->a->id),['observaciones'=>'Corrección administrativa ficticia'])->assertOk();
        $this->putJson(route('patients.operational.update',$this->a->patient_id),$this->patientData($this->a->patient))->assertOk();
        $this->assertSame($this->actor->id,$this->a->fresh()->user_id);$this->assertSame($this->actor->id,$this->a->patient->fresh()->user_id);
    }
    public function test_legacy_appointment_pages_redirect_and_menu_exposes_only_operational_agenda():void
    {
        foreach(['ADMISION'=>'admissionit','COMERCIAL'=>'admissionit','ADMINISTRADOR'=>'admin','RECEPCION'=>'receptionist'] as $role=>$prefix){
            $user=$this->agendaReader($role);$this->actingAs($user)->get(route($prefix.'.appointment.index'))->assertRedirect(route('scheduling.mvp.agenda'));
            $this->get(route('scheduling.mvp.agenda'))->assertOk()->assertDontSee('Seguimiento de citas')->assertSee('Agenda operativa');
        }
    }
    public function test_registration_contact_capture_cannot_bypass_patient_ownership():void
    {
        $other=$this->agendaReader('ADMISION');$other->givePermissionTo([C::CREATE,C::SUBMIT_PAYMENT]);$before=$this->money();$p=$this->a->patient;
        $this->actingAs($other)->postJson(route('scheduling.mvp.agenda.registrations'),['request_key'=>(string)Str::uuid(),'mode'=>'RESERVE','booking_type'=>'REGULAR',
            'patient_id'=>$p->id,'patient_capture'=>['telefono'=>'999222333'],'doctor_id'=>$this->a->doctor_id,'service_id'=>$this->a->service_id,
            'fecha_cita'=>'2026-10-21','hora_cita'=>'12:40','duracion_cita'=>20,'motivo_consulta'=>'Consulta ficticia','payment'=>['amount'=>'0']])->assertForbidden();
        $this->assertDatabaseCount('appointments',1);$this->assertSame($p->telefono,$p->fresh()->telefono);$this->assertSame($before,$this->money());
    }
}
