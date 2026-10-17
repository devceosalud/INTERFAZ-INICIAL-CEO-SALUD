<?php
namespace Tests\Feature\Scheduling;

use App\Models\Appointment;
use App\Services\Scheduling\OperationalProcedureService as Procedures;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

class AgendaV2ProceduresTest extends \Tests\Support\AgendaV2TestCase
{
    public function test_selected_catalog_is_explicit_stable_and_only_admin_changes_it(): void
    {
        $this->select()->assertOk();
        $this->assertSame(['radiography','laboratory'], DB::table('appointment_requested_procedures')->orderBy('id')->pluck('procedure_key')->all());
        $this->detail()->assertOk()->assertJsonCount(2,'procedures.items')->assertJsonPath('procedures.items.0.label','Radiografía')
            ->assertJsonPath('procedures.catalog.hyaluronic_acid','Ácido hialurónico');
        foreach([$this->admission,$this->commercial] as $u){
            $u->givePermissionTo(Permission::findOrCreate(\App\Services\Scheduling\AdmissionTaskService::MANAGE,'web'));
            $this->actingAs($u);$this->select(['mri'])->assertForbidden();
            $this->detail()->assertOk()->assertJsonPath('procedures.can_select',false)->assertJsonCount(0,'procedures.catalog');
        }
        $this->assertDatabaseCount('appointment_procedure_events',1);
    }
    public function test_admission_completes_only_requested_procedures_and_commercial_is_read_only(): void
    {
        $this->select()->assertOk();$this->actingAs($this->admission);
        $this->complete()->assertOk();
        $this->detail()->assertOk()->assertJsonPath('procedures.items.0.completed',true)->assertJsonPath('procedures.items.1.completed',false);
        $this->complete('mri')->assertNotFound();
        $this->actingAs($this->commercial);$this->complete('laboratory')->assertForbidden();
        $this->change('send',['confirmed'=>true,'reason'=>'Pendiente ficticio'])->assertForbidden();
        $this->assertDatabaseCount('appointment_procedure_events',2);
    }
    public function test_all_done_auto_queue_is_orange_in_both_agendas_without_financial_or_clinical_writes(): void
    {
        $before=$this->a->fresh()->getAttributes();
        $this->select()->assertOk();$this->actingAs($this->admission);$this->complete()->assertOk();
        $this->queue()->assertOk()->assertJsonCount(0,'rows');
        $this->complete('laboratory')->assertOk();
        $this->queue()->assertOk()->assertJsonCount(1,'rows')->assertJsonPath('rows.0.review.origin','AUTOMATIC')->assertJsonPath('rows.0.review.status','READY');
        $this->detail()->assertOk()->assertJsonPath('appointment.presentation.clave','LISTO_REEVALUACION');
        $this->postJson(route('scheduling.mvp.admission.feed'),['fecha'=>substr($this->a->fecha_cita,0,10)])
            ->assertOk()->assertJsonPath('rows.0.presentation.clave','LISTO_REEVALUACION');
        $feed=$this->getJson(route('scheduling.mvp.agenda.feed',['vista'=>'dia','fecha'=>substr($this->a->fecha_cita,0,10),'doctor_id'=>[$this->a->doctor_id]]))->assertOk();
        $this->assertStringContainsString('LISTO_REEVALUACION',$feed->getContent());
        $this->assertSame($before,$this->a->fresh()->getAttributes());
        $this->assertDatabaseCount('appointments',1);$this->assertDatabaseCount('payments',0);$this->assertDatabaseCount('vouchers',0);
    }
    public function test_manual_with_pending_requires_admission_confirmation_and_reason_not_admin_by_default(): void
    {
        $this->select()->assertOk();$this->complete()->assertOk();
        $this->change('send',['confirmed'=>true,'reason'=>'Revisión parcial ficticia'])->assertForbidden();
        $this->actingAs($this->admission);
        $this->change('send',['confirmed'=>false,'reason'=>'Ficticio'])->assertUnprocessable();
        $this->change('send',['confirmed'=>true])->assertUnprocessable();
        $this->change('send',['confirmed'=>true,'reason'=>'Revisión parcial ficticia'])->assertOk();
        $this->queue()->assertOk()->assertJsonPath('rows.0.review.origin','MANUAL')->assertJsonPath('rows.0.procedures.1.completed',false);
        $this->admin->givePermissionTo(Permission::findOrCreate(Procedures::SUPERVISE,'web'));
        $this->actingAs($this->admin)->change('withdraw',['confirmed'=>true,'reason'=>'Corrección supervisada'])->assertOk();
    }
    public function test_none_requested_or_none_done_never_enters_queue(): void
    {
        $this->select([])->assertOk();$this->actingAs($this->admission);
        $this->change('send',['confirmed'=>true,'reason'=>'Prueba'])->assertUnprocessable();$this->queue()->assertOk()->assertJsonCount(0,'rows');
        $this->actingAs($this->admin);$this->select()->assertOk();$this->actingAs($this->admission);
        $this->change('send',['confirmed'=>true,'reason'=>'Prueba'])->assertUnprocessable();
        $this->detail()->assertOk()->assertJsonPath('procedures.can_send',false);
        $this->queue()->assertOk()->assertJsonCount(0,'rows');
    }
    public function test_stale_versions_retries_and_changed_uuid_payloads_never_lose_updates(): void
    {
        $key=(string)Str::uuid();
        $this->change('selection',['procedures'=>['radiography','laboratory']],0,$key)->assertOk();
        $this->change('selection',['procedures'=>['radiography','laboratory']],0,$key)->assertOk();
        $this->change('selection',['procedures'=>['mri']],0,$key)->assertConflict();
        $this->change('completion',['procedure'=>'radiography','completed'=>true],1)->assertOk();
        $this->change('completion',['procedure'=>'laboratory','completed'=>true],1)->assertConflict();
        $this->assertDatabaseCount('appointment_procedure_events',2);
        $this->assertDatabaseHas('appointment_requested_procedures',['procedure_key'=>'laboratory','completed_at'=>null]);
    }
    public function test_request_edit_and_unchecking_recalculate_automatic_queue_and_preserve_audit(): void
    {
        $this->select()->assertOk();$this->complete()->assertOk();$this->complete('laboratory')->assertOk();
        $this->complete('laboratory',false)->assertOk();$this->queue()->assertOk()->assertJsonCount(0,'rows')->assertJsonPath('groups.pending.0.review.status','NOT_READY');
        $this->select(['radiography'])->assertOk();$this->queue()->assertOk()->assertJsonPath('rows.0.review.status','READY');
        $this->select(['radiography','mri'])->assertOk();$this->queue()->assertOk()->assertJsonCount(0,'rows')->assertJsonPath('groups.pending.0.review.status','NOT_READY');
        $event=DB::table('appointment_procedure_events')->orderByDesc('id')->first();
        $this->assertStringContainsString('AUTOMATIC',$event->before);
        $this->assertDatabaseCount('appointment_requested_procedures',3);
        $this->assertDatabaseHas('appointment_requested_procedures',['procedure_key'=>'laboratory','selected'=>false]);
    }
    public function test_manual_decision_is_preserved_suspended_and_withdrawn_explicitly(): void
    {
        $this->select()->assertOk();$this->complete()->assertOk();$this->actingAs($this->admission);
        $key=(string)Str::uuid();$body=['confirmed'=>true,'reason'=>'Revisión parcial ficticia'];
        $this->change('send',$body,2,$key)->assertOk();$this->change('send',$body,2,$key)->assertOk();
        $this->change('send',$body)->assertOk();$this->assertDatabaseCount('appointment_operational_reviews',1);
        $ready=DB::table('appointment_operational_reviews')->value('ready_at');
        $this->actingAs($this->admin);$this->select(['radiography','mri'])->assertOk();
        $this->queue()->assertOk()->assertJsonPath('rows.0.review.status','READY')->assertJsonPath('rows.0.review.origin','MANUAL');
        $this->assertSame($ready,DB::table('appointment_operational_reviews')->value('ready_at'));
        $this->actingAs($this->admission);$this->complete('radiography',false)->assertOk();
        $this->queue()->assertOk()->assertJsonCount(0,'rows')->assertJsonPath('groups.history.0.review.status','SUSPENDED');
        $this->complete()->assertOk();$this->change('withdraw',['confirmed'=>true,'reason'=>'Corrección ficticia'])->assertOk();
        $this->complete('mri')->assertOk();$this->queue()->assertOk()->assertJsonCount(0,'rows')->assertJsonPath('groups.history.0.review.status','WITHDRAWN');
        $this->change('send',['confirmed'=>true])->assertOk();$this->queue()->assertOk()->assertJsonPath('rows.0.review.status','READY');
    }
    public function test_visibility_and_no_patient_or_financial_details_in_queue(): void
    {
        $private=$this->lifecycleAppointment($this->commercial,$this->catalog,['estado_agenda'=>'PENDIENTE_CONFIRMACION']);
        $this->change('selection',['procedures'=>['radiography']],0,null,$private->id)->assertNotFound();
        $this->select(['radiography'])->assertOk();$this->complete()->assertOk();
        $r=$this->queue()->assertOk();
        foreach(['diagnostico','telefono','numero_identidad','precio_programado','payment','voucher','historia_clinica','private_path'] as $secret)$this->assertStringNotContainsString($secret,$r->getContent());
        $this->actingAs($this->commercial);$this->detail()->assertOk()->assertJsonPath('procedures.can_complete',false);
        $this->commercial->revokePermissionTo(C::VIEW);$this->queue()->assertForbidden();
        $this->change('selection',['procedures'=>['radiography']],0,null,999999)->assertForbidden();
    }
    public function test_queue_order_is_ready_time_then_id_today_lima_and_inactive_rows_are_labeled(): void
    {
        $this->select(['radiography'])->assertOk();$this->complete()->assertOk();
        $second=$this->lifecycleAppointment($this->admin,$this->catalog,['fecha_cita'=>now('America/Lima')->toDateString(),'hora_cita'=>'08:00']);
        $this->change('selection',['procedures'=>['radiography']],0,null,$second->id)->assertOk();
        $this->change('completion',['procedure'=>'radiography','completed'=>true],1,null,$second->id)->assertOk();
        DB::table('appointment_operational_reviews')->where('appointment_id',$second->id)->update(['ready_at'=>now()->subHour()]);
        $this->queue()->assertOk()->assertJsonPath('rows.0.id',$second->id)->assertJsonPath('rows.1.id',$this->a->id);
        $this->a->update(['estado_cita'=>'CANCELADO']);$this->queue()->assertOk()->assertJsonCount(1,'rows')->assertJsonPath('groups.history.0.review.status','INACTIVE');
        $this->detail()->assertOk()->assertJsonPath('appointment.presentation.clave','DESACTIVADA');
        $second->update(['fecha_cita'=>now('America/Lima')->subDay()->toDateString()]);$this->queue()->assertOk()->assertJsonCount(0,'rows')->assertJsonCount(1,'groups.history');
    }
    public function test_invalid_catalog_and_injected_fields_are_rejected(): void
    {
        $this->select(['unknown'])->assertUnprocessable();$this->select(['radiography','radiography'])->assertUnprocessable();
        $this->change('selection',['procedures'=>['radiography'],'estado_cita'=>'ATENDIDO'])->assertUnprocessable();
        $this->assertDatabaseCount('appointment_requested_procedures',0);$this->assertDatabaseCount('appointment_procedure_events',0);
    }
}
