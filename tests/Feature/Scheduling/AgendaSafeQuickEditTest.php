<?php
namespace Tests\Feature\Scheduling;

use App\Models\{Appointment,AppointmentDocument,DoctorService,Service};
use App\Services\Scheduling\AppointmentManagement;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class AgendaSafeQuickEditTest extends \Tests\Support\AgendaV2TestCase
{
    private $other,$target;
    protected function setUp():void
    {
        parent::setUp();
        $this->admin->givePermissionTo(Permission::findOrCreate(C::UPDATE,'web'));
        $this->a->update(['fecha_cita'=>$this->catalog['schedule']->fecha_cita,'hora_cita'=>'08:00','estado_agenda'=>'PENDIENTE_CONFIRMACION',
            'tipo_agendamiento'=>'REGULAR','economic_source'=>'VOUCHER','motivo_consulta'=>'MOTIVO PRIVADO ANTERIOR','observaciones'=>'OBSERVACION ANTERIOR','is_postoperative'=>true,'has_diabetic_wound'=>true]);
        $this->other=Service::create(['nombre'=>'Alternativo ficticio','specialty_id'=>$this->catalog['specialty']->id,'estado'=>'ACTIVO']);
        DoctorService::create(['doctor_id'=>$this->a->doctor_id,'service_id'=>$this->other->id,'precio_primera_consulta'=>100,'estado'=>'ACTIVO']);
        $this->target=$this->createPatient($this->admin,['numero_identidad'=>'75555222','historia_clinica'=>'QA-NEW-IDENTITY']);
    }
    private function url():string{return route('scheduling.mvp.agenda.quick-edit.show',$this->a->id);}
    private function data(string $action='service'):array{return ['action'=>$action,'service_id'=>$this->other->id,'patient_id'=>$this->target->id,
        'motivo'=>'Corrección administrativa ficticia','motivo_consulta'=>'Motivo nuevo autorizado','confirmed'=>true,
        'expected_revision'=>AppointmentManagement::revision($this->a->fresh()),'request_key'=>(string)Str::uuid()];}
    public function test_same_price_service_is_previewed_and_changed_without_rewriting_economics_or_duration():void
    {
        $this->getJson($this->url().'?service_id='.$this->other->id)->assertOk()->assertJsonPath('proposal.available',true)->assertJsonPath('proposal.price','100.00')->assertJsonCount(0,'proposal.blockers');
        $before=$this->a->fresh()->only(['precio_programado','catalog_price','total_pagado','saldo_pendiente','duracion_cita','fecha_cita','hora_cita']);$data=$this->data();
        $this->patchJson($this->url(),$data)->assertOk();$this->patchJson($this->url(),$data)->assertOk();
        $this->assertSame($before,$this->a->fresh()->only(array_keys($before)));$this->assertSame($this->other->id,$this->a->fresh()->service_id);
        $this->assertSame(1,$this->a->events()->where('event_type','SERVICIO_CORREGIDO')->count());$this->assertDatabaseCount('payments',0);
    }
    public function test_price_difference_is_explained_and_contract_is_not_requoted():void
    {
        DoctorService::where('service_id',$this->other->id)->update(['precio_primera_consulta'=>120]);
        $this->getJson($this->url().'?service_id='.$this->other->id)->assertOk()->assertJsonPath('proposal.price','120.00')->assertJsonPath('proposal.blockers.0.code','PRICE_DIFFERENCE');
        $this->patchJson($this->url(),$this->data())->assertUnprocessable();$this->assertSame($this->catalog['service']->id,$this->a->fresh()->service_id);$this->assertEquals(100,$this->a->fresh()->precio_programado);
    }
    public function test_service_change_rechecks_occupancy_and_rejects_incompatible_specialty():void
    {
        $this->lifecycleAppointment($this->admin,$this->catalog,['fecha_cita'=>$this->catalog['schedule']->fecha_cita,'hora_cita'=>'08:00']);
        $this->getJson($this->url().'?service_id='.$this->other->id)->assertOk()->assertJsonPath('proposal.available',false)->assertJsonPath('proposal.blockers.0.code','AVAILABILITY');
        $this->patchJson($this->url(),$this->data())->assertUnprocessable();
        $foreign=$this->createAppointmentCatalog()['service'];$data=$this->data();$data['service_id']=$foreign->id;
        $this->patchJson($this->url(),$data)->assertUnprocessable();$this->assertSame($this->catalog['service']->id,$this->a->fresh()->service_id);
    }
    public function test_safe_reassignment_requires_new_reason_clears_old_flags_and_preserves_audit_without_leaking_old_text():void
    {
        $old=$this->a->patient_id;$data=$this->data('patient');$bad=$data;$bad['motivo_consulta']='   ';
        $this->patchJson($this->url(),$bad)->assertUnprocessable();$this->patchJson($this->url(),$data)->assertOk();$this->patchJson($this->url(),$data)->assertOk();
        $a=$this->a->fresh();$this->assertSame($this->target->id,$a->patient_id);$this->assertSame('Motivo nuevo autorizado',$a->motivo_consulta);$this->assertNull($a->observaciones);$this->assertFalse($a->is_postoperative);$this->assertFalse($a->has_diabetic_wound);
        $e=$a->events()->where('event_type','PACIENTE_REASIGNADO')->sole();$this->assertSame($old,$e->metadata['from']['patient_id']);$this->assertSame('MOTIVO PRIVADO ANTERIOR',$e->metadata['from']['motivo_consulta']);
        $r=$this->detail()->assertOk();$this->assertStringNotContainsString('MOTIVO PRIVADO ANTERIOR',$r->getContent());$this->assertDatabaseCount('patients',2);$this->assertDatabaseCount('payments',0);
    }
    public function test_stale_reassignment_and_forged_money_fields_are_rejected():void
    {
        $data=$this->data('patient');$this->a->update(['observaciones'=>'Otro cambio']);$this->patchJson($this->url(),$data)->assertConflict();
        $this->patchJson($this->url(),$this->data()+['precio_programado'=>0])->assertUnprocessable();$this->assertNotSame($this->target->id,$this->a->fresh()->patient_id);
    }
    public function test_real_or_snapshot_payment_blocks_reassignment_with_specific_reason():void
    {
        $this->a->update(['total_pagado'=>10]);$this->getJson($this->url())->assertOk()->assertJsonPath('blockers.0.code','PAYMENT');
        $this->patchJson($this->url(),$this->data('patient'))->assertUnprocessable()->assertJsonValidationErrors('appointment');$this->assertEquals(10,$this->a->fresh()->total_pagado);
    }
    public function test_deleted_document_and_procedure_history_remain_blockers():void
    {
        $d=AppointmentDocument::create(['appointment_id'=>$this->a->id,'type'=>'OTHER','label'=>'Privado','actor_user_id'=>$this->admin->id]);$d->delete();
        $this->getJson($this->url())->assertOk()->assertJsonPath('blockers.0.code','DOCUMENT');$this->patchJson($this->url(),$this->data('patient'))->assertUnprocessable();
        $this->select();$r=$this->getJson($this->url())->assertOk();$this->assertContains('PROCEDURE',array_column($r->json('blockers'),'code'));
    }
    public function test_care_history_blocks_even_after_returning_to_programmed():void
    {
        app(\App\Services\Scheduling\AppointmentHistory::class)->record($this->a,'ADMISION_ESTADO_REVERTIDO',$this->admin->id);
        $this->getJson($this->url())->assertOk()->assertJsonPath('blockers.0.code','HISTORY');$this->patchJson($this->url(),$this->data())->assertUnprocessable();
    }
    public function test_roles_need_effective_update_permission_and_hidden_reservations_stay_hidden():void
    {
        $this->actingAs($this->commercial)->getJson($this->url())->assertNotFound();$this->patchJson($this->url(),$this->data())->assertForbidden();
        $this->a->update(['estado_agenda'=>'CONFIRMADA']);$this->getJson($this->url())->assertOk()->assertJsonPath('blockers.0.code','PERMISSION');
        $this->actingAs($this->admission)->patchJson($this->url(),$this->data())->assertForbidden();
        $this->admission->givePermissionTo(Permission::findOrCreate(C::UPDATE,'web'));$this->patchJson($this->url(),$this->data())->assertForbidden();
        $this->a->update(['user_id'=>$this->admission->id]);$this->patchJson($this->url(),$this->data())->assertOk();
        $this->admin->forceFill(['is_active'=>false])->save();$this->actingAs($this->admin)->getJson($this->url())->assertUnauthorized();
    }
    public function test_unallocated_bank_reference_or_inconsistent_financial_snapshot_is_not_transferred():void
    {
        $this->a->update(['numero_operacion'=>'001245']);
        $this->getJson($this->url())->assertOk()->assertJsonPath('blockers.0.code','FINANCIAL_HISTORY');
        $this->patchJson($this->url(),$this->data('patient'))->assertUnprocessable();
        $this->a->update(['numero_operacion'=>null,'saldo_pendiente'=>50]);
        $this->getJson($this->url())->assertOk()->assertJsonPath('blockers.0.code','ECONOMIC_REVIEW');
        $this->patchJson($this->url(),$this->data())->assertUnprocessable();$this->assertEquals(50,$this->a->fresh()->saldo_pendiente);
    }

}
