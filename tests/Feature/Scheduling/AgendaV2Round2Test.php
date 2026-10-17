<?php
namespace Tests\Feature\Scheduling;

use App\Models\Appointment;
use App\Services\Scheduling\OperationalProcedureService;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class AgendaV2Round2Test extends \Tests\Support\AgendaV2TestCase
{
    public static function procedureMatrix(): array
    {
        return [[0,0,false,false],[2,0,false,false],[2,1,false,false],[2,1,true,true],
            [2,2,false,true],[3,2,false,false],[3,3,false,true]];
    }

    /** @dataProvider procedureMatrix */
    public function test_confirmed_procedure_matrix_matches_detail_both_feeds_and_active_queue(int $requested,int $done,bool $manual,bool $ready): void
    {
        $keys=array_slice(['radiography','laboratory','mri'],0,$requested);
        $this->select($keys)->assertOk();$this->actingAs($this->admission);
        foreach(array_slice($keys,0,$done) as $key)$this->complete($key)->assertOk();
        if($manual)$this->change('send',['confirmed'=>true,'reason'=>'Decisión parcial ficticia explícita'])->assertOk();
        $this->actingAs($this->commercial);
        $detail=$this->detail()->assertOk();
        $detail->assertJsonPath('procedures.requested_count',$requested)->assertJsonPath('procedures.completed_count',$done)
            ->assertJsonPath('appointment.presentation.operational_reevaluation_ready',$ready);
        $this->postJson(route('scheduling.mvp.admission.feed'),['fecha'=>substr($this->a->fecha_cita,0,10)])->assertOk()->assertJsonPath('rows.0.presentation.operational_reevaluation_ready',$ready);
        $feed=$this->getJson(route('scheduling.mvp.agenda.feed',['vista'=>'dia','fecha'=>substr($this->a->fecha_cita,0,10),'doctor_id'=>[$this->a->doctor_id]]))->assertOk();
        $this->assertStringContainsString('"operational_reevaluation_ready":'.($ready?'true':'false'),$feed->getContent());
        $this->assertCount($ready?1:0,$this->queue()->assertOk()->json('groups.active'));
        $this->assertDatabaseCount('appointments',1);$this->assertDatabaseCount('payments',0);$this->assertDatabaseCount('vouchers',0);
        $this->assertSame('PROGRAMADO',$this->a->fresh()->estado_cita);
        if($manual)$detail->assertJsonPath('procedures.review.authorized_by.id',$this->admission->id);
    }

    public function test_stale_automatic_ready_header_cannot_color_a_partial_appointment_or_enter_active_queue(): void
    {
        $this->select()->assertOk();$this->complete()->assertOk();
        DB::table('appointment_operational_reviews')->where('appointment_id',$this->a->id)->update(['status'=>'READY','origin'=>'AUTOMATIC','ready_at'=>now()]);
        $this->detail()->assertOk()->assertJsonPath('appointment.presentation.operational_reevaluation_ready',false)
            ->assertJsonPath('procedures.review.status','NOT_READY');
        $this->queue()->assertOk()->assertJsonCount(0,'groups.active')->assertJsonCount(1,'groups.pending');
    }

    public function test_unchecking_automatic_completion_removes_readiness_but_keeps_history_and_manual_decisions_are_explicit(): void
    {
        $this->select()->assertOk();$this->complete()->assertOk();$this->complete('laboratory')->assertOk();
        $this->detail()->assertJsonPath('procedures.review.origin','AUTOMATIC')->assertJsonPath('appointment.presentation.operational_reevaluation_ready',true);
        $this->complete('laboratory',false)->assertOk();
        $this->detail()->assertJsonPath('appointment.presentation.operational_reevaluation_ready',false);
        $this->queue()->assertJsonCount(0,'groups.active')->assertJsonCount(1,'groups.pending');
        $this->actingAs($this->admission);$this->change('send',['confirmed'=>true,'reason'=>'Autorización parcial ficticia'])->assertOk();
        $this->complete('laboratory')->assertOk();$this->complete('laboratory',false)->assertOk();
        $this->detail()->assertJsonPath('procedures.review.origin','MANUAL')->assertJsonPath('procedures.review.authorized_by.id',$this->admission->id)
            ->assertJsonPath('appointment.presentation.operational_reevaluation_ready',true);
        $this->change('withdraw',['confirmed'=>true,'reason'=>'Retirada ficticia'])->assertOk();
        $this->queue()->assertJsonCount(0,'groups.active')->assertJsonCount(1,'groups.history');
        $this->assertDatabaseHas('appointment_procedure_events',['appointment_id'=>$this->a->id,'action'=>'SEND','actor_user_id'=>$this->admission->id]);
        $this->assertDatabaseHas('appointment_procedure_events',['appointment_id'=>$this->a->id,'action'=>'WITHDRAW']);
    }

    private function registration(array $extra=[]): array
    {
        return array_replace(['request_key'=>(string)Str::uuid(),'mode'=>'RESERVE','booking_type'=>'REGULAR',
            'patient_id'=>$this->a->patient_id,'doctor_id'=>$this->catalog['doctor']->id,'service_id'=>$this->catalog['service']->id,
            'fecha_cita'=>$this->catalog['schedule']->fecha_cita,'hora_cita'=>'08:00','duracion_cita'=>30,
            'motivo_consulta'=>'Motivo ficticio de consulta'], $extra);
    }

    public function test_reason_is_required_for_every_creation_entrypoint_and_all_booking_payment_modes(): void
    {
        $this->admin->givePermissionTo(Permission::findOrCreate(C::CREATE,'web'));
        $this->admin->givePermissionTo(Permission::findOrCreate(C::CREATE_ADDITIONAL,'web'));
        $this->admin->givePermissionTo(Permission::findOrCreate(C::SUBMIT_PAYMENT,'web'));
        foreach(['REGULAR','ADICIONAL','FUERA_HORARIO'] as $type)foreach(['','  ',"\u{00a0}\u{200b}",null] as $reason){
            foreach(['RESERVE','CONFIRM'] as $mode)$this->postJson(route('scheduling.mvp.agenda.registrations'),$this->registration([
                'booking_type'=>$type,'mode'=>$mode,'motivo_consulta'=>$reason,'payment'=>['amount'=>$mode==='CONFIRM'?'30.00':'0','method'=>'EFECTIVO']]))
                ->assertUnprocessable()->assertJsonValidationErrors('motivo_consulta');
        }
        foreach(['scheduling.mvp.agenda.appointments.store','scheduling.mvp.agenda.appointments.additional','scheduling.mvp.agenda.appointments.off-hours'] as $route){
            $this->postJson(route($route),$this->registration(['motivo_consulta'=>' ']))->assertUnprocessable()->assertJsonValidationErrors('motivo_consulta');
        }
        $this->assertDatabaseCount('appointments',1);$this->assertDatabaseCount('payments',0);$this->assertDatabaseCount('appointment_operations',0);
    }

    public function test_reason_is_trimmed_and_visible_only_in_authorized_admission_projections_without_historical_backfill(): void
    {
        $this->admin->givePermissionTo(Permission::findOrCreate(C::CREATE,'web'));
        $r=$this->postJson(route('scheduling.mvp.agenda.registrations'),$this->registration(['motivo_consulta'=>'  Dolor ficticio de rodilla  ']))->assertCreated();
        $id=$r->json('appointment.appointment_id');$this->assertDatabaseHas('appointments',['id'=>$id,'motivo_consulta'=>'Dolor ficticio de rodilla']);
        $this->getJson(route('scheduling.mvp.admission.detail',$id))->assertOk()->assertJsonPath('appointment.motivo_consulta','Dolor ficticio de rodilla');
        $this->a->update(['motivo_consulta'=>null]);$this->detail()->assertJsonPath('appointment.motivo_consulta',null);
        $this->assertNull($this->a->fresh()->motivo_consulta);
        $other=$this->agendaReader('COMERCIAL');$this->actingAs($other)->getJson(route('scheduling.mvp.admission.detail',$id))->assertNotFound();
    }

    public function test_one_character_observation_is_audited_and_empty_or_excessive_text_is_rejected(): void
    {
        $url=route('scheduling.mvp.admission.tasks.store',$this->a->id);
        $this->postJson($url,['text'=>' . ','request_key'=>(string)Str::uuid()])->assertCreated();
        $this->assertDatabaseHas('appointment_admission_tasks',['appointment_id'=>$this->a->id,'body'=>'.']);
        $this->assertDatabaseCount('appointment_admission_task_events',1);
        foreach([' ',"\n\t", "\u{00a0}\u{200b}",str_repeat('x',16001)] as $text)$this->postJson($url,['text'=>$text,'request_key'=>(string)Str::uuid()])->assertUnprocessable();
        $this->postJson($url,['text'=>str_repeat('x',1001),'request_key'=>(string)Str::uuid()])->assertCreated();
        $this->assertDatabaseCount('appointment_admission_tasks',2);
    }

    public function test_legacy_creation_is_denied_for_every_role_and_followup_remains_available(): void
    {
        foreach(['ADMINISTRADOR','ADMISION','COMERCIAL','RECEPCION'] as $role){
            $u=$this->agendaReader($role);$u->givePermissionTo(Permission::findOrCreate(C::CREATE,'web'));
            $this->actingAs($u)->postJson(route('admissionit.appointment.store'),$this->registration())->assertForbidden();
            $this->get(route('admissionit.appointment.index'))->assertOk()->assertSee('Seguimiento de citas')->assertDontSee('id="appointmentModalCreate"',false);
        }
        $this->assertDatabaseCount('appointments',1);$this->assertDatabaseCount('payments',0);
    }

    public function test_visual_and_reevaluation_filters_keep_search_kpis_and_economic_totals_on_the_same_set(): void
    {
        $this->a->update(['motivo_consulta'=>'Motivo ficticio resumido']);$this->select()->assertOk();$this->complete()->assertOk();
        $second=$this->lifecycleAppointment($this->admin,$this->catalog,['fecha_cita'=>now('America/Lima')->toDateString(),'hora_cita'=>'10:00']);
        $this->change('selection',['procedures'=>['mri']],0,null,$second->id)->assertOk();$this->change('completion',['procedure'=>'mri','completed'=>true],1,null,$second->id)->assertOk();
        $base=['fecha'=>now('America/Lima')->toDateString(),'doctor_id'=>$this->catalog['doctor']->id,'specialty_id'=>$this->catalog['specialty']->id];
        foreach([['visual'=>'LISTO_REEVALUACION'],['only_reevaluations'=>true]] as $filter){
            $this->postJson(route('scheduling.mvp.admission.feed'),$base+$filter)->assertOk()->assertJsonPath('total',1)
                ->assertJsonPath('kpis.reevaluaciones',1)->assertJsonPath('totals.precio','100.00')->assertJsonPath('rows.0.id',$second->id);
        }
        $this->postJson(route('scheduling.mvp.admission.feed'),$base+['patient'=>$this->a->patient->numero_identidad])->assertOk()
            ->assertJsonPath('total',1)->assertJsonPath('rows.0.motivo_resumen','Motivo ficticio resumido');
        $this->postJson(route('scheduling.mvp.admission.feed'),$base+['patient'=>'%'])->assertOk()->assertJsonPath('total',0);
        $this->postJson(route('scheduling.mvp.admission.feed'),$base+['visual'=>'NOT_A_COLOR'])->assertUnprocessable();
    }
}
