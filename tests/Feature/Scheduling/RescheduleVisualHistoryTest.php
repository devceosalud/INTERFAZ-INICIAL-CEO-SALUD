<?php
namespace Tests\Feature\Scheduling;

use App\Models\{Appointment,AppointmentEvent,Doctor,Payment,Voucher,VoucherItem};
use App\Services\Scheduling\AppointmentManagement;
use App\Support\Scheduling\SchedulingCapability as C;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsRescheduleVisualData;
use Tests\TestCase;

class RescheduleVisualHistoryTest extends TestCase
{
    use RefreshDatabase, BuildsRescheduleVisualData;
    private $actor,$a,$catalog;
    protected function setUp():void
    {
        parent::setUp();
        foreach($this->rescheduleVisualFixture() as $key=>$value)$this->{$key}=$value;
        $this->actingAs($this->actor);
    }
    protected function tearDown():void {Carbon::setTestNow();parent::tearDown();}
    private function move(string $date='2026-10-24',string $time='12:00',array $extra=[])
    {
        $a=$this->a->fresh();
        return $this->patchJson(route('scheduling.mvp.agenda.appointments.reschedule',$a->id),$extra+[
            'fecha_cita'=>$date,'hora_cita'=>$time,'expected_fecha_cita'=>substr($a->fecha_cita,0,10),
            'expected_hora_cita'=>substr($a->hora_cita,0,5),'expected_revision'=>AppointmentManagement::revision($a),'motivo'=>'Solicitud QA FICTICIA']);
    }
    private function agenda(array $extra=[])
    {
        return $this->getJson(route('scheduling.mvp.agenda.feed',$extra+['vista'=>'dia','fecha'=>'2026-10-21','doctor_id'=>[$this->a->doctor_id]]));
    }
    private function admission(array $extra=[])
    {
        return $this->postJson(route('scheduling.mvp.admission.feed'),$extra+['fecha'=>'2026-10-21','site_id'=>null]);
    }
    private function financialRows():array
    {
        return collect(['payments','vouchers','voucher_items','cashier_shifts'])->mapWithKeys(fn($t)=>[$t=>DB::table($t)->orderBy('id')->get()->map(fn($r)=>(array)$r)->all()])->all();
    }
    public function test_original_1220_is_available_history_is_separate_and_new_reservation_coexists_without_financial_changes():void
    {
        $money=$this->financialRows();$patientCount=DB::table('patients')->count();
        $this->move()->assertOk();
        $feed=$this->agenda()->assertOk()->assertJsonCount(1,'reprogramaciones.items')
            ->assertJsonPath('reprogramaciones.items.0.from_time','12:20')->assertJsonPath('reprogramaciones.items.0.to_date','2026-10-24')
            ->assertJsonPath('reprogramaciones.items.0.to_time','12:00')->assertJsonPath('reprogramaciones.items.0.occupies_slot',false);
        $this->assertTrue(collect($feed->json('eventos'))->contains(fn($e)=>($e['extendedProps']['tipo_contexto']??null)==='slot_libre'&&($e['extendedProps']['hora_inicio']??null)==='12:20'));
        $this->assertSame(0,$feed->json('resumen.ocupadas'));
        $this->admission()->assertOk()->assertJsonPath('kpis.total',0)->assertJsonPath('totals.pagos_reales','0.00')->assertJsonCount(1,'reschedules.items')->assertJsonCount(0,'rows');
        $current=$this->admission(['fecha'=>'2026-10-24'])->assertOk()->assertJsonPath('kpis.total',1)->assertJsonPath('totals.pagos_reales','60.00')
            ->assertJsonPath('rows.0.reschedule_origin.from_time','12:20')->assertJsonCount(0,'reschedules.items');
        $this->assertSame($this->a->id,$current->json('rows.0.id'));
        $this->assertSame($money,$this->financialRows());
        $this->postJson(route('scheduling.mvp.agenda.registrations'),['request_key'=>(string)Str::uuid(),'mode'=>'CONFIRM','booking_type'=>'REGULAR','payment'=>['amount'=>'20','method'=>'EFECTIVO'],
            'patient_id'=>$this->a->patient_id,'doctor_id'=>$this->a->doctor_id,'service_id'=>$this->a->service_id,'fecha_cita'=>'2026-10-21',
            'hora_cita'=>'12:20','duracion_cita'=>20,'motivo_consulta'=>'Nueva reserva FICTICIA'])->assertCreated();
        $this->agenda()->assertOk()->assertJsonCount(1,'reprogramaciones.items')->assertJsonPath('resumen.ocupadas',1);
        $this->assertDatabaseCount('appointments',2);$this->assertSame($patientCount,DB::table('patients')->count());
        $newMoney=$this->financialRows();
        $this->admission(['fecha'=>'2026-10-21','hasta'=>'2026-10-24'])->assertOk()->assertJsonPath('kpis.total',2)->assertJsonPath('totals.pagos_reales','80.00');
        $this->assertSame($newMoney,$this->financialRows());$this->assertDatabaseCount('payments',2);
    }
    public function test_successive_moves_keep_all_origins_latest_destination_and_stale_writes_are_rejected():void
    {
        $stale=AppointmentManagement::revision($this->a->fresh());$this->move()->assertOk();
        $this->move('2026-10-26','12:40',['expected_revision'=>$stale])->assertConflict();
        $this->move('2026-10-26','12:40')->assertOk();$this->move('2026-10-26','12:40')->assertOk();
        $this->agenda(['vista'=>'mes'])->assertOk()->assertJsonCount(2,'reprogramaciones.items')->assertJsonPath('reprogramaciones.items.1.current_time','12:40');
        $this->admission()->assertOk()->assertJsonPath('reschedules.items.0.to_date','2026-10-24')->assertJsonPath('reschedules.items.0.current_date','2026-10-26');
        $this->admission(['fecha'=>'2026-10-24'])->assertOk()->assertJsonPath('kpis.total',0)->assertJsonPath('reschedules.items.0.to_date','2026-10-26');
        $this->assertSame(2,AppointmentEvent::where('event_type','REPROGRAMADA')->count());$this->assertDatabaseCount('appointments',1);
    }
    public function test_extended_source_retains_duration_even_when_current_schedule_changes():void
    {
        $this->a->update(['duracion_cita'=>40,'duration_slots'=>2]);$this->move()->assertOk();
        $this->catalog['schedule']->update(['hora_inicio'=>'13:00']);
        $this->agenda()->assertOk()->assertJsonPath('reprogramaciones.items.0.from_end','13:00')->assertJsonPath('reprogramaciones.items.0.duration_slots',2)
            ->assertJsonPath('reprogramaciones.items.0.duration_minutes',40);
        $this->assertSame(40,(int)$this->a->fresh()->duracion_cita);
    }
    public function test_history_filters_by_original_doctor_site_and_current_patient_without_exposing_identity():void
    {
        $site=$this->createSite(['codigo'=>'HISTORY']);$otherSite=$this->createSite(['codigo'=>'OTHER']);$this->a->update(['site_id'=>$site->id]);
        \App\Models\DoctorSchedule::where('doctor_id',$this->a->doctor_id)->update(['site_id'=>$site->id]);
        $this->move()->assertOk();
        $other=Doctor::create(['nombre'=>'OTRO FICTICIO','specialty_id'=>$this->catalog['specialty']->id,'estado'=>'ACTIVO']);
        $this->a->update(['doctor_id'=>$other->id,'site_id'=>$otherSite->id]);
        $this->agenda(['site_id'=>$site->id,'doctor_id'=>[$this->catalog['doctor']->id]])->assertOk()->assertJsonCount(1,'reprogramaciones.items');
        $this->agenda(['doctor_id'=>[$other->id]])->assertOk()->assertJsonCount(0,'reprogramaciones.items');
        $this->agenda(['site_id'=>$otherSite->id])->assertOk()->assertJsonCount(0,'reprogramaciones.items');
        $this->admission(['doctor_id'=>$this->catalog['doctor']->id,'patient'=>'Paciente'])->assertOk()->assertJsonCount(1,'reschedules.items');
        $this->admission(['patient'=>'%_!MISSING'])->assertOk()->assertJsonCount(0,'reschedules.items');
        $history=$this->admission()->json('reschedules.items.0');
        foreach(['patient_id','patient','motivo','motivo_consulta','payments','precio_programado'] as $key)$this->assertArrayNotHasKey($key,$history);
        $this->assertStringContainsString('doctor_id='.$other->id,$history['agenda_url']);
    }
    public function test_hidden_private_reservations_revoked_and_inactive_users_cannot_recover_history():void
    {
        $this->move()->assertOk();$this->a->update(['estado_agenda'=>'PENDIENTE_CONFIRMACION','responsible_user_id'=>$this->actor->id]);
        $other=$this->agendaReader('ADMISION');$this->actingAs($other);
        $this->agenda()->assertOk()->assertJsonCount(0,'reprogramaciones.items');$this->admission()->assertOk()->assertJsonCount(0,'reschedules.items');
        $url=route('scheduling.mvp.agenda.reschedule-history',['vista'=>'dia','fecha'=>'2026-10-21','doctor_id'=>[$this->a->doctor_id]]);
        $this->getJson($url)->assertOk()->assertJsonCount(0,'items');
        $other->revokePermissionTo(C::VIEW);$this->getJson($url)->assertForbidden();$this->admission()->assertForbidden();
        $this->actingAs($this->actor);$this->actor->forceFill(['is_active'=>false])->save();$this->getJson($url)->assertUnauthorized();
    }
    public function test_pagination_does_not_duplicate_references_or_change_queue_kpis_and_economics():void
    {
        $this->move()->assertOk();$event=AppointmentEvent::where('event_type','REPROGRAMADA')->firstOrFail();
        for($i=0;$i<205;$i++)$event->replicate()->save();
        $first=$this->admission()->assertOk()->assertJsonCount(30,'reschedules.items')->assertJsonPath('reschedules.has_more',true);
        $next=$this->admission(['history_before'=>$first->json('reschedules.next_cursor')])->assertOk()->assertJsonCount(30,'reschedules.items');
        $this->assertEmpty(array_intersect(array_column($first->json('reschedules.items'),'event_id'),array_column($next->json('reschedules.items'),'event_id')));
        foreach(['rows','kpis','totals','total'] as $key)$this->assertSame($first->json($key),$next->json($key));
        $main=$this->agenda()->assertOk()->assertJsonCount(200,'reprogramaciones.items')->assertJsonPath('reprogramaciones.has_more',true);
        $this->getJson(route('scheduling.mvp.agenda.reschedule-history',['vista'=>'dia','fecha'=>'2026-10-21','doctor_id'=>[$this->a->doctor_id],
            'history_before'=>$main->json('reprogramaciones.next_cursor')]))->assertOk()->assertJsonCount(6,'items')->assertJsonPath('has_more',false);
    }
    public function test_history_is_read_only_and_has_no_schema_dependency():void
    {
        $this->move()->assertOk();$schema=json_encode(DB::table('migrations')->orderBy('id')->get());$before=json_encode(DB::table('appointments')->get());
        $money=$this->financialRows();$events=AppointmentEvent::count();
        foreach(['dia','semana','mes'] as $view)$this->agenda(['vista'=>$view])->assertOk()->assertJsonCount(1,'reprogramaciones.items');
        $this->admission()->assertOk();
        $this->assertSame($schema,json_encode(DB::table('migrations')->orderBy('id')->get()));
        $this->assertSame($before,json_encode(DB::table('appointments')->get()));$this->assertSame($money,$this->financialRows());
        $this->assertSame($events,AppointmentEvent::count());
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('appointment_economic_corrections'));
    }

    public function test_presence_of_history_never_changes_calendar_capacity_colors_or_admission_totals():void
    {
        $this->move()->assertOk();$withHistory=[];
        foreach(['dia','semana','mes'] as $view)$withHistory[$view]=$this->agenda(['vista'=>$view])->assertOk()->json();
        $admission=$this->admission(['fecha'=>'2026-10-21','hasta'=>'2026-10-24'])->assertOk()->json();
        // Remove only this test fixture's event, never application/history records outside this transaction.
        AppointmentEvent::where('appointment_id',$this->a->id)->where('event_type','REPROGRAMADA')->delete();
        foreach(['dia','semana','mes'] as $view){
            $without=$this->agenda(['vista'=>$view])->assertOk();
            foreach(['eventos','profesionales','resumen','especiales'] as $key)$this->assertSame($withHistory[$view][$key],$without->json($key),$view.':'.$key);
        }
        $without=$this->admission(['fecha'=>'2026-10-21','hasta'=>'2026-10-24'])->assertOk();
        foreach(['kpis','totals','total'] as $key)$this->assertSame($admission[$key],$without->json($key));
    }

    public function test_computed_filters_bound_scan_and_offer_a_cursor_without_silently_truncating_history():void
    {
        $this->move()->assertOk();$event=AppointmentEvent::where('event_type','REPROGRAMADA')->firstOrFail();
        $row=$event->getAttributes();unset($row['id']);DB::table('appointment_events')->insert(array_fill(0,2050,$row));
        $first=$this->admission(['grupo'=>'atendidos'])->assertOk()->assertJsonCount(0,'reschedules.items')->assertJsonPath('reschedules.has_more',true);
        $this->assertGreaterThan(0,$first->json('reschedules.next_cursor'));
        $this->admission(['grupo'=>'atendidos','history_before'=>$first->json('reschedules.next_cursor')])->assertOk()
            ->assertJsonCount(0,'reschedules.items')->assertJsonPath('reschedules.has_more',false)->assertJsonPath('kpis.total',0);
    }
}
