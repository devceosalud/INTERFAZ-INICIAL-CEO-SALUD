<?php
namespace Tests\Feature\Scheduling;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Services\Billing\AppointmentEconomicPosition;
use App\Services\Scheduling\AppointmentManagement;
use App\Services\Scheduling\AgendaProfessionalPreferences;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class AgendaV2Round4Test extends \Tests\Support\AgendaV2TestCase
{
    private function grant($user,array $caps=[C::UPDATE]): void {foreach($caps as $cap)$user->givePermissionTo(Permission::findOrCreate($cap,'web'));}
    private function edit(array $data=[],?string $key=null,?string $revision=null) {
        return $this->patchJson(route('scheduling.mvp.agenda.notes',$this->a->id),$data+[
            'request_key'=>$key??(string)Str::uuid(),'expected_revision'=>$revision??AppointmentManagement::revision($this->a->fresh())]);
    }
    public function test_custom_procedure_keeps_stable_key_name_actors_and_withdrawal_history():void {
        $id=(string)Str::uuid();$key='c:'.$id;$body=['procedures'=>['radiography'],'custom_procedures'=>[['id'=>$id,'label'=>'EMG <ficticio> & evaluación']]];
        $this->change('selection',$body)->assertOk();$this->detail()->assertOk()->assertJsonPath('procedures.items.1.key',$key)->assertJsonPath('procedures.items.1.label','EMG <ficticio> & evaluación')->assertJsonPath('procedures.items.1.requested_by',$this->admin->name);
        $this->actingAs($this->admission);$this->complete($key)->assertOk();$this->queue()->assertOk()->assertJsonCount(0,'rows');
        $this->complete('radiography')->assertOk();$this->queue()->assertOk()->assertJsonPath('rows.0.review.origin','AUTOMATIC');
        $this->detail()->assertOk()->assertJsonPath('procedures.items.1.completed_by',$this->admission->name);
        $this->complete($key,false)->assertOk();$this->queue()->assertOk()->assertJsonCount(0,'rows');
        $this->actingAs($this->admin);$this->change('selection',['procedures'=>['radiography'],'custom_procedures'=>[]])->assertOk();
        $this->assertDatabaseHas('appointment_requested_procedures',['procedure_key'=>$key,'selected'=>false,'custom_label'=>'EMG <ficticio> & evaluación']);
        $this->assertDatabaseCount('appointment_requested_procedures',2);$this->assertDatabaseCount('appointment_procedure_events',5);
        $this->assertDatabaseCount('payments',0);$this->assertSame('PROGRAMADO',$this->a->fresh()->estado_cita);
    }
    /** @dataProvider validCustomNames */
    public function test_custom_names_accept_significant_one_character_and_technical_max(string $name):void {
        $this->change('selection',['procedures'=>[],'custom_procedures'=>[['id'=>(string)Str::uuid(),'label'=>$name]]])->assertOk();
        $this->detail()->assertOk()->assertJsonPath('procedures.items.0.label',$name);
    }
    public static function validCustomNames():array{return [['X'],[str_repeat('á',500)],['Control de herida / evaluación & EMG']];}
    /** @dataProvider invalidCustomNames */
    public function test_invalid_custom_names_never_create_request_or_audit(string $name):void {
        $this->change('selection',['procedures'=>[],'custom_procedures'=>[['id'=>(string)Str::uuid(),'label'=>$name]]])->assertUnprocessable();
        $this->assertDatabaseCount('appointment_requested_procedures',0);$this->assertDatabaseCount('appointment_procedure_events',0);
    }
    public static function invalidCustomNames():array{return [[''],['    '],["\u{200B}\u{FEFF}"],[str_repeat('x',501)]];}
    public function test_custom_authorization_immutable_label_and_old_client_preservation():void {
        $id=(string)Str::uuid();$custom=[['id'=>$id,'label'=>'X']];
        $this->actingAs($this->commercial);$this->change('selection',['procedures'=>[],'custom_procedures'=>$custom])->assertForbidden();
        $this->actingAs($this->admin);$this->change('selection',['procedures'=>[],'custom_procedures'=>$custom])->assertOk();
        $this->change('selection',['procedures'=>[],'custom_procedures'=>[['id'=>$id,'label'=>'Different']]])->assertConflict();
        $this->select(['mri'])->assertOk();$this->detail()->assertOk()->assertJsonPath('procedures.requested_count',2);
        $this->complete('c:'.Str::uuid())->assertNotFound();
    }
    public function test_management_edit_mode_and_replay_preserve_finances_and_have_audited_concurrency_guard():void {
        $this->grant($this->admin);$this->a->update(['total_pagado'=>30,'saldo_pendiente'=>70]);
        $before=$this->a->fresh()->only(['precio_programado','total_pagado','saldo_pendiente','estado_pagado','fecha_cita','hora_cita','estado_agenda']);
        $revision=AppointmentManagement::revision($this->a->fresh());$key=(string)Str::uuid();$body=['attendance_mode'=>'TELECONSULTA','confirmed_attendance_change'=>true,'motivo_consulta'=>'Motivo ficticio actualizado'];
        $this->edit($body,$key,$revision)->assertOk();$this->edit($body,$key,$revision)->assertOk();
        $this->assertSame($before,$this->a->fresh()->only(array_keys($before)));$this->assertDatabaseCount('appointment_operations',1);
        $this->assertSame(1,$this->a->events()->where('event_type','MODALIDAD_ATENCION_ACTUALIZADA')->count());
        $this->edit(['motivo_consulta'=>'Otra sesión'],null,$revision)->assertConflict();
        $this->assertSame('Motivo ficticio actualizado',$this->a->fresh()->motivo_consulta);
        $this->assertNull($this->a->fresh()->hora_llegada);$this->assertDatabaseCount('payments',0);
    }
    public function test_mode_change_requires_confirmation_specific_role_and_before_care():void {
        $this->grant($this->admin);$this->edit(['attendance_mode'=>'TELECONSULTA'])->assertUnprocessable();
        $this->grant($this->commercial);$this->actingAs($this->commercial);$this->edit(['attendance_mode'=>'TELECONSULTA','confirmed_attendance_change'=>true])->assertForbidden();
        $this->grant($this->admission);$this->actingAs($this->admission);$this->edit(['attendance_mode'=>'TELECONSULTA','confirmed_attendance_change'=>true])->assertForbidden();
        $this->a->update(['user_id'=>$this->admission->id]);$this->edit(['attendance_mode'=>'TELECONSULTA','confirmed_attendance_change'=>true])->assertOk();
        $this->a->update(['hora_atencion'=>now(),'estado_cita'=>'EN_ATENCION']);$this->edit(['attendance_mode'=>'PRESENCIAL','confirmed_attendance_change'=>true])->assertForbidden();
        $this->assertSame('TELECONSULTA',$this->a->fresh()->attendance_mode);
    }
    public function test_no_edit_permission_no_private_reservation_write_and_no_money_injection():void {
        $this->edit(['motivo_consulta'=>'Sin permiso'])->assertForbidden();$this->grant($this->admin);
        foreach(['precio_programado','total_pagado','estado_pagado','patient_id','service_id','doctor_id','fecha_cita','es_exonerado'] as $field)$this->edit([$field=>0])->assertUnprocessable();
        $this->a->update(['estado_agenda'=>'PENDIENTE_CONFIRMACION','responsible_user_id'=>$this->commercial->id,'user_id'=>$this->commercial->id]);
        $this->getJson(route('scheduling.mvp.agenda.economy',$this->a->id))->assertOk()->assertJsonPath('management.can_edit',false);
        $this->edit(['motivo_consulta'=>'Reserva ajena'])->assertNotFound();
    }
    public function test_teleconsultation_has_explicit_badges_no_physical_actions_and_same_site_and_economy():void {
        $this->grant($this->admin,[C::UPDATE,C::WITHDRAW,C::MARK_NO_SHOW]);$this->a->update(['attendance_mode'=>'TELECONSULTA','tipo_agendamiento'=>'ADICIONAL','is_postoperative'=>true,'has_diabetic_wound'=>true]);
        $r=$this->detail()->assertOk()->assertJsonCount(0,'care_actions');$keys=array_column($r->json('appointment.presentation.badges'),'clave');
        foreach(['TELECONSULTA','ADICIONAL','POSTOPERATORIO','HERIDA'] as $key)$this->assertContains($key,$keys);
        $this->postJson(route('scheduling.mvp.admission.care',$this->a->id),['state'=>'EN_ESPERA','expected_state'=>'PROGRAMADO','request_key'=>(string)Str::uuid()])->assertUnprocessable();
        $this->postJson(route('scheduling.mvp.agenda.withdraw',$this->a->id),['request_key'=>(string)Str::uuid(),'motivo'=>'Ficticio','requested_action'=>'PENDIENTE','was_present'=>true])->assertUnprocessable();
        $this->assertNull($this->a->fresh()->hora_llegada);$this->assertDatabaseCount('payments',0);
    }
    public function test_reprogramming_preserves_modality_and_rejects_stale_management_revision():void {
        $this->grant($this->admin,[C::UPDATE,C::RESCHEDULE]);$this->a->update(['attendance_mode'=>'TELECONSULTA','fecha_cita'=>$this->catalog['schedule']->fecha_cita,'hora_cita'=>'08:00']);
        $token=AppointmentManagement::revision($this->a->fresh());$this->edit(['observaciones'=>'Cambio de segunda sesión'])->assertOk();
        $data=['fecha_cita'=>$this->catalog['schedule']->fecha_cita,'hora_cita'=>'10:00','expected_fecha_cita'=>$this->catalog['schedule']->fecha_cita,'expected_hora_cita'=>'08:00','expected_revision'=>$token];
        $url=route('scheduling.mvp.agenda.appointments.reschedule',$this->a->id);$this->patchJson($url,$data)->assertConflict();
        $data['expected_revision']=AppointmentManagement::revision($this->a->fresh());$this->patchJson($url,$data)->assertOk();
        $this->assertSame('TELECONSULTA',$this->a->fresh()->attendance_mode);$this->assertSame('10:00',substr($this->a->fresh()->hora_cita,0,5));$this->assertSame(100.0,(float)$this->a->fresh()->precio_programado);
    }
    public function test_preference_is_per_user_persists_filters_inactive_doctors_and_requires_active_agenda_access():void {
        $url=route('scheduling.mvp.agenda.professional-preferences');$id=$this->a->doctor_id;
        $this->postJson($url,['doctor_id'=>$id,'action'=>'favorite'])->assertOk()->assertJsonPath('favorites.0',$id);
        $this->postJson($url,['doctor_id'=>$id,'action'=>'select'])->assertOk()->assertJsonPath('last',$id)->assertJsonPath('frequent.0',$id);
        $this->assertSame([$id],app(AgendaProfessionalPreferences::class)->read($this->admin->fresh())['favorites']);
        $this->assertSame([],app(AgendaProfessionalPreferences::class)->read($this->commercial)['favorites']);
        $this->postJson($url,['doctor_id'=>$id,'action'=>'unfavorite'])->assertOk()->assertJsonCount(0,'favorites');
        Doctor::whereKey($id)->update(['estado'=>'INACTIVO']);$this->assertSame([],app(AgendaProfessionalPreferences::class)->read($this->admin)['frequent']);
        $this->postJson($url,['doctor_id'=>$id,'action'=>'favorite'])->assertUnprocessable();
        $this->admin->forceFill(['is_active'=>false])->save();$this->postJson($url,['doctor_id'=>$id,'action'=>'select'])->assertUnauthorized();
    }
    public function test_favorite_limit_serialized_without_clinical_data():void {
        $url=route('scheduling.mvp.agenda.professional-preferences');
        for($i=0;$i<6;$i++){$doctor=Doctor::create(['nombre'=>'Ficticio '.$i,'specialty_id'=>$this->catalog['specialty']->id,'estado'=>'ACTIVO']);$r=$this->postJson($url,['doctor_id'=>$doctor->id,'action'=>'favorite']);if($i<5)$r->assertOk();else $r->assertUnprocessable();}
        $this->assertDatabaseCount('agenda_professional_preferences',5);$this->assertDatabaseCount('payments',0);
    }
    /** @dataProvider registrationModes */
    public function test_new_teleconsultation_keeps_normal_price_payment_and_confirmation_rules(string $amount):void {
        $this->grant($this->admin,[C::CREATE,C::SUBMIT_PAYMENT]);
        if($amount!=='0'){$c=\App\Models\Cashier::create(['nombre'=>'Caja teleconsulta ficticia','estado'=>'ACTIVO']);
            \App\Models\CashierShift::create(['cashier_id'=>$c->id,'user_id'=>$this->admin->id,'monto_apertura'=>0,'abierto_en'=>now(),'estado'=>'ABIERTO']);
            \App\Models\VoucherSerie::create(['cashier_id'=>$c->id,'tipo_comprobante'=>'TICKET','serie'=>'RTEL','correlativo_actual'=>0,'estado'=>'ACTIVO']);}
        $data=['request_key'=>(string)Str::uuid(),'mode'=>'RESERVE','booking_type'=>'REGULAR','attendance_mode'=>'TELECONSULTA',
            'patient_id'=>$this->a->patient_id,'doctor_id'=>$this->a->doctor_id,'service_id'=>$this->a->service_id,'fecha_cita'=>$this->catalog['schedule']->fecha_cita,
            'hora_cita'=>'10:00','duracion_cita'=>30,'motivo_consulta'=>'Consulta remota ficticia','payment'=>['amount'=>$amount,'method'=>'EFECTIVO']];
        $r=$this->postJson(route('scheduling.mvp.agenda.registrations'),$data)->assertCreated();$a=Appointment::findOrFail($r->json('appointment.appointment_id'));
        $this->assertSame('TELECONSULTA',$a->attendance_mode);$this->assertSame('REGULAR',$a->tipo_agendamiento);
        $this->assertSame($amount==='0'?'PENDIENTE_CONFIRMACION':'CONFIRMADA',$a->estado_agenda);$this->assertSame(100.0,(float)$a->precio_programado);
        $this->assertNull($a->hora_llegada);$this->assertNull($a->hora_salida);$this->assertDatabaseCount('payments',$amount==='0'?0:1);
    }
    public static function registrationModes():array{return [['0'],['30'],['100']];}

    public function test_commercial_cannot_submit_even_an_unchanged_attendance_mode():void {
        $this->grant($this->commercial);$this->actingAs($this->commercial);
        $this->edit(['attendance_mode'=>'PRESENCIAL','confirmed_attendance_change'=>true])->assertForbidden();
        $this->assertDatabaseCount('appointment_operations',0);$this->assertDatabaseCount('appointment_events',0);
    }

}
