<?php

namespace Tests\Feature\Scheduling;

use App\Models\User;
use App\Services\Billing\AppointmentEconomicPosition;
use App\Support\Scheduling\{AgendaAppointmentPresentation, ClinicalCapability, ClinicalOrderSpecification};
use Illuminate\Support\Facades\{Crypt, DB};
use Spatie\Permission\Models\{Permission, Role};

class ClinicalEnterpriseRefinementTest extends \Tests\Support\AgendaV2TestCase
{
    private function asPhysician(): User
    {
        foreach(ClinicalCapability::all()as $p)Permission::findOrCreate($p,'web');Role::findOrCreate('MEDICO','web')->syncPermissions(ClinicalCapability::all());
        $u=$this->createUserWithRole('MEDICO');$u->forceFill(['doctor_id'=>$this->catalog['doctor']->id,'is_active'=>true])->save();
        $this->a->update(['estado_agenda'=>'CONFIRMADA','estado_cita'=>'EN_ATENCION','hora_llegada'=>now(),'hora_atencion'=>now()]);$this->actingAs($u);return $u;
    }

    private function order(array $extra=[]): array
    {
        return $extra+['procedures'=>['rx_hand','mri'],'custom_procedures'=>[], 'expected_patient_id'=>$this->a->patient_id,
            'clinical_order_revision'=>ClinicalOrderSpecification::revision(),
            'clinical_order'=>['diagnosis'=>'Diagnóstico exclusivamente ficticio de prueba','observations'=>'Observación clínica ficticia, no una realización.'],
            'procedure_details'=>['rx_hand'=>['sides'=>['D'],'projections'=>['AP','LAT'],'finger'=>2]]];
    }

    public function test_authorized_exemption_is_yellow_without_recording_money_or_confirming_a_reservation(): void
    {
        $this->a->update(['es_exonerado'=>true,'autorizado_por'=>'QA FICTICIO','motivo_exoneracion'=>'Autorización ficticia verificable.']);
        $before=$this->a->fresh()->getAttributes();
        $p=AgendaAppointmentPresentation::of($this->a->fresh(),app(AppointmentEconomicPosition::class)->forAppointment($this->a->fresh()));
        $this->assertSame('EXONERADA',$p['clave']);$this->assertSame('#fff2a6',$p['fringe']['color']);$this->assertFalse($p['paid_real']);
        $this->detail()->assertOk()->assertJsonPath('appointment.presentation.clave','EXONERADA');
        $this->assertSame($before,$this->a->fresh()->getAttributes());$this->assertDatabaseCount('payments',0);$this->assertDatabaseCount('vouchers',0);
        $this->a->update(['autorizado_por'=>'']);$p=AgendaAppointmentPresentation::of($this->a->fresh(),['authority'=>'PAYMENTS','paid_cents'=>0]);$this->assertNotSame('EXONERADA',$p['clave']);
    }

    public function test_exemption_preserves_cancellation_presence_attendance_and_refund_priorities(): void
    {
        $a=$this->a->fresh();$a->es_exonerado=true;$a->autorizado_por='QA FICTICIO';
        foreach(['CANCELADO'=>'DESACTIVADA','RETIRO'=>'DESACTIVADA','EN_ESPERA'=>'PRESENTE','EN_ATENCION'=>'PRESENTE','ATENDIDO'=>'ATENDIDO']as $state=>$expected){$a->estado_cita=$state;$this->assertSame($expected,AgendaAppointmentPresentation::of($a,[])['clave']);}
        $a->estado_cita='PROGRAMADO';$this->assertSame('REEMBOLSO_SOLICITADO',AgendaAppointmentPresentation::of($a,['refund_reserved_cents'=>100])['clave']);
    }

    public function test_order_specifics_and_private_header_are_saved_audited_and_reopened_without_new_appointments_or_payments(): void
    {
        $physician=$this->asPhysician();
        $before=$this->a->fresh()->getAttributes();$body=$this->order();$this->change('selection',$body)->assertOk();
        $this->detail()->assertOk()->assertJsonPath('procedures.clinical_order.diagnosis',$body['clinical_order']['diagnosis'])->assertJsonPath('procedures.items.0.details.finger',2)->assertJsonPath('procedures.items.0.specification','Derecho · AP · Lateral · Dedo 2');
        $cipher=DB::table('appointment_operational_reviews')->value('clinical_order_ciphertext');$this->assertStringNotContainsString($body['clinical_order']['diagnosis'],$cipher);$this->assertSame($body['clinical_order']['diagnosis'],json_decode(Crypt::decryptString($cipher),true)['diagnosis']);
        $audit=DB::table('appointment_procedure_events')->get()->toJson();$this->assertStringNotContainsString($body['clinical_order']['diagnosis'],$audit);$this->assertStringNotContainsString($body['clinical_order']['observations'],$audit);
        $this->actingAs($this->admission)->detail()->assertOk()->assertJsonPath('procedures.clinical_order',null)->assertJsonPath('procedures.items.0.details.sides.0','D');
        $this->assertDatabaseCount('appointments',1);$this->assertDatabaseCount('payments',0);$this->assertSame($before,$this->a->fresh()->getAttributes());
    }

    public function test_old_client_omission_preserves_private_header_and_specifics(): void
    {
        $physician=$this->asPhysician();
        $this->change('selection',$this->order())->assertOk();$cipher=DB::table('appointment_operational_reviews')->value('clinical_order_ciphertext');
        $this->change('selection',['procedures'=>['rx_hand','mri']])->assertOk();
        $this->assertSame($cipher,DB::table('appointment_operational_reviews')->value('clinical_order_ciphertext'));
        $this->detail()->assertOk()->assertJsonPath('procedures.items.0.details.finger',2);
    }

    public function test_stale_patient_or_order_schema_rejects_the_entire_write(): void
    {
        $physician=$this->asPhysician();
        foreach([['expected_patient_id'=>$this->a->patient_id+999],['clinical_order_revision'=>str_repeat('0',64)]]as $bad)$this->change('selection',$this->order($bad))->assertConflict();
        $this->assertDatabaseCount('appointment_procedure_events',0);$this->assertDatabaseCount('appointment_requested_procedures',0);$this->assertDatabaseCount('appointment_operational_reviews',0);
    }

    public function test_specifics_validate_the_actual_option_and_header_limits(): void
    {
        $physician=$this->asPhysician();
        foreach([
            ['procedure_details'=>['rx_hand'=>['with_load'=>true]]],
            ['procedure_details'=>['mri'=>['sides'=>['D']]]],
            ['procedure_details'=>['rx_hand'=>['finger'=>6]]],
            ['procedure_details'=>['rx_knee'=>['with_load'=>true]]],
            ['clinical_order'=>['diagnosis'=>str_repeat('a',501)]],
            ['clinical_order'=>['observations'=>str_repeat('a',601)]],
        ]as $bad)$this->change('selection',$this->order($bad))->assertUnprocessable();
        $this->assertDatabaseCount('appointment_requested_procedures',0);
        $this->change('selection',$this->order(['procedures'=>['rx_knee'],'procedure_details'=>['rx_knee'=>['with_load'=>true,'sides'=>['I'],'projections'=>['OBLIQUE']]]]))->assertOk();
        $this->detail()->assertOk()->assertJsonPath('procedures.items.0.specification','Izquierdo · Con carga · Oblicua');
    }

    public function test_completed_exam_cannot_be_relabelled_as_a_different_exam_and_retries_are_idempotent(): void
    {
        $physician=$this->asPhysician();
        $body=$this->order();$uuid=(string)\Illuminate\Support\Str::uuid();$this->change('selection',$body,0,$uuid)->assertOk();$this->change('selection',$body,0,$uuid)->assertOk();$this->assertDatabaseCount('appointment_procedure_events',1);
        $this->actingAs($this->admission)->complete('rx_hand')->assertOk();$this->actingAs($physician);$this->change('selection',$this->order(['procedure_details'=>['rx_hand'=>['sides'=>['I']]]]))->assertConflict();
        $this->assertDatabaseCount('appointment_procedure_events',2);$this->detail()->assertOk()->assertJsonPath('procedures.items.0.completed',true)->assertJsonPath('procedures.items.0.details.sides.0','D');
    }

    public function test_personal_followup_gate_is_enforced_on_page_feed_detail_and_write(): void
    {
        foreach(ClinicalCapability::all()as $p)Permission::findOrCreate($p,'web');Role::findOrCreate('MEDICO','web')->syncPermissions(ClinicalCapability::all());
        $u=$this->createUserWithRole('MEDICO');$u->forceFill(['doctor_id'=>$this->catalog['doctor']->id,'is_active'=>true])->save();
        $this->assertSame(2,(int)config('medical_followups.authorized_user_id'));$this->assertNotSame(2,$u->id);
        $this->actingAs($u)->get('/scheduling-mvp/medical-followups')->assertForbidden();$this->postJson('/scheduling-mvp/medical-followups/feed',[])->assertForbidden();$this->getJson('/scheduling-mvp/medical-followups/999')->assertForbidden();$this->patchJson('/scheduling-mvp/medical-followups/999',[])->assertForbidden();
        config(['medical_followups.authorized_user_id'=>$u->id]);$this->get('/scheduling-mvp/medical-followups')->assertOk();$this->postJson('/scheduling-mvp/medical-followups/feed',[])->assertOk();
        $this->actingAs($this->admin)->get('/scheduling-mvp/medical-followups')->assertForbidden();
    }

    public function test_additive_order_migration_refuses_to_remove_saved_clinical_information(): void
    {
        $physician=$this->asPhysician();
        $this->change('selection',$this->order())->assertOk();$m=require database_path('migrations/2026_10_23_040000_preserve_clinical_order_details.php');
        try{$m->down();$this->fail('Saved clinical fields must survive rollback.');}catch(\RuntimeException $e){$this->assertStringContainsString('Preserve',$e->getMessage());}
        $this->detail()->assertOk()->assertJsonPath('procedures.items.0.details.finger',2);
    }

    public function test_administrative_selection_does_not_grant_new_clinical_header_access(): void
    {
        $this->change('selection',$this->order())->assertForbidden();
        $this->change('selection',['procedures'=>['rx_hand']])->assertOk();
        $this->detail()->assertOk()->assertJsonPath('procedures.can_order_clinical',false)->assertJsonPath('procedures.clinical_order',null);
        $this->assertNull(DB::table('appointment_operational_reviews')->value('clinical_order_ciphertext'));
    }
}
