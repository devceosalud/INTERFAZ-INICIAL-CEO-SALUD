<?php
namespace Tests\Feature\Scheduling;

use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Services\Scheduling\AppointmentHistory;
use App\Services\Scheduling\ManualReevaluationService as Manual;
use App\Support\Scheduling\AdmissionAppointmentPresentation as Presentation;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

class ManualReevaluationTest extends TestCase
{
    use RefreshDatabase, BuildsAgendaLifecycleData;
    private $actor, $catalog;
    protected function setUp(): void
    {
        parent::setUp(); config(['scheduling.enabled'=>true]);
        $this->actor=$this->agendaReader('ADMISION');
        $this->actor->givePermissionTo(Permission::findOrCreate(C::UPDATE,'web'));
        $this->catalog=$this->createAppointmentCatalog(); $this->actingAs($this->actor);
    }
    private function appointment(array $extra=[]): Appointment
    {
        return $this->lifecycleAppointment($this->actor,$this->catalog,array_replace(['estado_cita'=>'ATENDIDO'],$extra));
    }
    private function payload(array $extra=[]): array
    {
        return array_replace(['marked'=>true,'expected_marked'=>false,'expected_version'=>0,'request_key'=>(string) Str::uuid()],$extra);
    }
    private function toggle(Appointment $a,array $data=[])
    {
        return $this->patchJson(route('scheduling.mvp.admission.manual-reevaluation',$a->id),$this->payload($data));
    }
    private function feed(array $extra=[])
    {
        return $this->postJson(route('scheduling.mvp.admission.feed'),array_replace(['fecha'=>now()->toDateString(),'site_id'=>null],$extra));
    }
    private function snapshot(): array
    {
        $result=[];
        foreach(['appointments','payments','vouchers','voucher_items','appointment_documents','appointment_reevaluation_indications',
            'appointment_admission_tasks','appointment_admission_task_events','appointment_refund_requests','appointment_credit_applications','doctor_schedules'] as $t)
            $result[$t]=DB::table($t)->orderBy('id')->get()->toJson();
        return $result;
    }
    public function test_attended_toggle_without_exams_is_audited_and_changes_only_events_and_operations(): void
    {
        $a=$this->appointment(['total_pagado'=>30,'saldo_pendiente'=>70,'estado_pagado'=>'PARCIAL']);$before=$this->snapshot();
        $this->feed()->assertOk()->assertJsonPath('rows.0.presentation.fondo','#166534')->assertJsonPath('rows.0.presentation.color','#ffffff');
        $v=$this->toggle($a)->assertOk()->assertJsonPath('manual_reevaluation.marked',true)->json('manual_reevaluation.version');
        $this->feed()->assertOk()->assertJsonPath('rows.0.presentation.fondo','#fed7aa')->assertJsonPath('rows.0.estado_cita','ATENDIDO');
        $this->toggle($a,['marked'=>false,'expected_marked'=>true,'expected_version'=>$v])->assertOk()->assertJsonPath('manual_reevaluation.marked',false);
        $this->feed()->assertOk()->assertJsonPath('rows.0.presentation.fondo','#166534');
        $this->assertSame($before,$this->snapshot());$this->assertDatabaseCount('appointment_operations',2);
        $events=$a->events()->orderBy('id')->get();$this->assertCount(2,$events);
        $this->assertSame([Manual::MARKED,Manual::CLEARED],$events->pluck('event_type')->all());
        foreach($events as $event){$this->assertSame((int)$this->actor->id,(int)$event->actor_user_id);$this->assertNotNull($event->occurred_at);$this->assertSame('ADMISSION_MANUAL_V1',$event->metadata['source']);$this->assertNotEmpty($event->metadata['operation_id']);}
    }
    public function test_audit_failure_rolls_back_operation_and_flag_without_touching_appointment(): void
    {
        $a=$this->appointment();$before=$this->snapshot();
        $this->mock(AppointmentHistory::class)->shouldReceive('record')->once()->andThrow(new \RuntimeException('Synthetic audit failure'));
        $this->toggle($a)->assertStatus(500);
        $this->assertSame($before,$this->snapshot());
        $this->assertDatabaseCount('appointment_operations',0);$this->assertDatabaseCount('appointment_events',0);
    }
    public function test_exact_retries_do_not_duplicate_or_resurrect_a_later_cleared_mark(): void
    {
        $a=$this->appointment();$data=$this->payload();
        $v=$this->toggle($a,$data)->assertOk()->json('manual_reevaluation.version');
        $this->toggle($a,$data)->assertOk();
        $clear=$this->payload(['marked'=>false,'expected_marked'=>true,'expected_version'=>$v]);
        $this->toggle($a,$clear)->assertOk();$this->toggle($a,$clear)->assertOk();
        $this->toggle($a,$data)->assertOk()->assertJsonPath('manual_reevaluation.marked',false);
        $this->assertDatabaseCount('appointment_events',2);$this->assertDatabaseCount('appointment_operations',2);
    }
    public function test_key_reuse_for_other_payload_or_appointment_is_conflict(): void
    {
        $a=$this->appointment();$data=$this->payload();$this->toggle($a,$data)->assertOk();
        $this->toggle($a,array_replace($data,['marked'=>false]))->assertConflict();
        $this->toggle($this->appointment(),$data)->assertConflict();
        $this->assertDatabaseCount('appointment_events',1);$this->assertDatabaseCount('appointment_operations',1);
    }
    public function test_concurrent_and_aba_versions_are_rejected_without_partial_operations(): void
    {
        $a=$this->appointment();$v=$this->toggle($a)->assertOk()->json('manual_reevaluation.version');
        $this->toggle($a)->assertConflict();
        $this->toggle($a,['marked'=>false,'expected_marked'=>true,'expected_version'=>$v])->assertOk();
        $this->toggle($a)->assertConflict(); // False again, but an old browser still has version zero.
        $this->assertDatabaseCount('appointment_events',2);$this->assertDatabaseCount('appointment_operations',2);
    }
    public function test_only_admin_and_admission_with_effective_permissions_can_write(): void
    {
        $a=$this->appointment();
        foreach(['COMERCIAL','RECEPCION','CAJA','FACTURACION','SIN_ROL'] as $role){
            $user=$this->agendaReader($role);$user->givePermissionTo(Permission::findOrCreate(C::UPDATE,'web'));$this->actingAs($user);
            $this->toggle($a)->assertForbidden();$this->feed()->assertOk()->assertJsonPath('rows.0.manual_reevaluation.can_update',false);
        }
        foreach(['ADMINISTRADOR','ADMISION'] as $role){
            $user=$this->agendaReader($role);$this->actingAs($user);$this->toggle($a)->assertForbidden();
            $user->givePermissionTo(C::UPDATE);$target=$this->appointment();$this->toggle($target)->assertOk();
            $user->revokePermissionTo(C::VIEW);$this->toggle($target)->assertForbidden();
            $user->givePermissionTo(C::VIEW);$user->revokePermissionTo(C::MVP_ACCESS);$this->toggle($target)->assertForbidden();
        }
        $this->assertDatabaseCount('appointment_events',2);
    }
    public function test_inactive_actor_and_private_appointment_are_blocked_even_for_admin(): void
    {
        $other=$this->agendaReader('COMERCIAL');
        $hidden=$this->lifecycleAppointment($other,$this->catalog,['estado_cita'=>'ATENDIDO','estado_agenda'=>'PENDIENTE_CONFIRMACION','responsible_user_id'=>$other->id]);
        $admin=$this->agendaReader('ADMINISTRADOR');$admin->givePermissionTo(Permission::findOrCreate(C::UPDATE,'web'));$this->actingAs($admin);
        $this->toggle($hidden)->assertNotFound();$this->feed(['tab'=>'reevaluaciones'])->assertOk()->assertJsonPath('total',0);
        $admin->forceFill(['is_active'=>false])->save();$this->toggle($this->appointment())->assertUnauthorized();
        $this->assertDatabaseCount('appointment_events',0);$this->assertDatabaseCount('appointment_operations',0);
    }
    public function test_other_states_and_inconsistent_pending_attended_cannot_be_marked(): void
    {
        foreach(['PROGRAMADO','CONFIRMADO','PACIENTE_LLEGO','EN_ESPERA','LLAMANDO','EN_ATENCION','REEVALUACION','CANCELADO','NO_ASISTIO','RETIRO'] as $state)
            $this->toggle($this->appointment(['estado_cita'=>$state]))->assertUnprocessable();
        $this->toggle($this->appointment(['estado_agenda'=>'PENDIENTE_CONFIRMACION','responsible_user_id'=>$this->actor->id]))->assertUnprocessable();
        $this->assertDatabaseCount('appointment_events',0);$this->assertDatabaseCount('appointment_operations',0);
    }
    public function test_payload_cannot_mutate_care_money_schedule_or_price(): void
    {
        $a=$this->appointment();$before=$this->snapshot();
        foreach(['estado_cita'=>'REEVALUACION','total_pagado'=>999,'precio_programado'=>0,'hora_cita'=>'10:00','patient_id'=>999,'procedures'=>[]] as $key=>$value)
            $this->toggle($a,[$key=>$value])->assertUnprocessable();
        foreach([['request_key'=>'bad'],['marked'=>'yes'],['expected_version'=>-1],['expected_version'=>null],['expected_marked'=>null],['marked'=>false]] as $invalid)
            $this->toggle($a,$invalid)->assertUnprocessable();
        $this->assertSame($before,$this->snapshot());$this->assertDatabaseCount('appointment_operations',0);
    }
    public function test_reevaluations_tab_uses_same_filters_totals_and_pagination_without_private_rows(): void
    {
        for($i=0;$i<31;$i++){
            $a=$this->appointment();app(AppointmentHistory::class)->record($a,Manual::MARKED,$this->actor->id);
        }
        $this->appointment();$tomorrow=$this->appointment(['fecha_cita'=>now()->addDay()->toDateString()]);
        app(AppointmentHistory::class)->record($tomorrow,Manual::MARKED,$this->actor->id);
        $this->feed(['tab'=>'reevaluaciones'])->assertOk()->assertJsonPath('total',31)->assertJsonPath('kpis.total',31)->assertJsonPath('totals.precio','3100.00')->assertJsonCount(30,'rows');
        $this->feed(['tab'=>'reevaluaciones','page'=>2])->assertOk()->assertJsonPath('total',31)->assertJsonCount(1,'rows');
        foreach([['patient'=>'%'],['estado'=>'EN_ESPERA'],['grupo'=>'por_llegar']] as $filter)
            $this->feed($filter+['tab'=>'reevaluaciones'])->assertOk()->assertJsonPath('total',0);
        $this->feed(['tab'=>'reevaluaciones','doctor_id'=>$this->catalog['doctor']->id,'specialty_id'=>$this->catalog['specialty']->id,'patient'=>'Baseline','grupo'=>'atendidos'])->assertOk()->assertJsonPath('total',31);
        $site=$this->createSite();Appointment::query()->update(['site_id'=>$site->id]);
        $this->feed(['tab'=>'reevaluaciones','site_id'=>$site->id])->assertOk()->assertJsonPath('total',31);
        $other=$this->createSite(['codigo'=>'OTRA']);$this->feed(['tab'=>'reevaluaciones','site_id'=>$other->id])->assertOk()->assertJsonPath('total',0);
        $this->feed(['tab'=>'invalid'])->assertUnprocessable();
        $this->assertDatabaseCount('appointment_events',32);$this->assertDatabaseCount('payments',0);
    }
    public function test_critical_colors_and_financial_medical_badges_keep_priority_over_flag(): void
    {
        $a=$this->appointment(['is_postoperative'=>true,'has_diabetic_wound'=>true]);
        $base=['authority'=>'PAYMENTS','paid_cents'=>3000];
        foreach([false,true] as $marked){
            $p=Presentation::of($a,$base+['refund_reserved_cents'=>1000],$marked);$this->assertSame('REEMBOLSO_SOLICITADO',$p['clave']);
            $a->forceFill(['hora_salida'=>now()]);$p=Presentation::of($a,$base,$marked);$this->assertSame('SALIDA_REGISTRADA',$p['clave']);$this->assertTrue($p['strikethrough']);
            $a->forceFill(['hora_salida'=>null]);
        }
        foreach(['CANCELADO','RETIRO','NO_ASISTIO'] as $state){$a->forceFill(['estado_cita'=>$state]);$this->assertNotContains(Presentation::of($a,$base,true)['clave'],['REEVALUACION_MANUAL','ATENDIDO_ADMISION']);}
        $a->forceFill(['estado_cita'=>'ATENDIDO']);$p=Presentation::of($a,$base,true);
        $this->assertSame(['PAGO_REAL','POSTOPERATORIO','HERIDA'],array_column($p['badges'],'clave'));
        $this->assertSame('REEVALUACION_MANUAL',$p['clave']);
    }
    public function test_latest_manual_event_is_stable_with_equal_times_and_unrelated_newer_events(): void
    {
        $this->freezeTime();$a=$this->appointment();$v=$this->toggle($a)->assertOk()->json('manual_reevaluation.version');
        app(AppointmentHistory::class)->record($a,'DATOS_OPERATIVOS_ACTUALIZADOS',$this->actor->id);
        $this->feed()->assertOk()->assertJsonPath('rows.0.manual_reevaluation.marked',true)->assertJsonPath('rows.0.manual_reevaluation.version',$v);
        $this->toggle($a,['marked'=>false,'expected_marked'=>true,'expected_version'=>$v])->assertOk();
        $this->feed(['tab'=>'reevaluaciones'])->assertOk()->assertJsonPath('total',0);
    }
    public function test_revisions_refresh_on_flag_and_permission_changes_and_clear_clamps_tab_page(): void
    {
        $a=$this->appointment();$first=$this->feed()->assertOk()->json('revision');
        $detailUrl=route('scheduling.mvp.admission.detail',$a->id);$detail=$this->getJson($detailUrl)->assertOk()->json('revision');
        $v=$this->toggle($a)->assertOk()->json('manual_reevaluation.version');
        $this->feed(['revision'=>$first])->assertOk()->assertJsonMissingPath('unchanged');
        $this->getJson($detailUrl.'?revision='.$detail)->assertOk()->assertJsonMissingPath('unchanged')->assertJsonPath('appointment.manual_reevaluation.marked',true);
        $this->toggle($a,['marked'=>false,'expected_marked'=>true,'expected_version'=>$v])->assertOk();
        $this->feed(['tab'=>'reevaluaciones','page'=>2])->assertOk()->assertJsonPath('total',0)->assertJsonPath('page',1);
        $revision=$this->feed()->json('revision');$this->actor->revokePermissionTo(C::UPDATE);
        $this->feed(['revision'=>$revision])->assertOk()->assertJsonMissingPath('unchanged')->assertJsonPath('rows.0.manual_reevaluation.can_update',false);
    }
}
