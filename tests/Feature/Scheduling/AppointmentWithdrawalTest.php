<?php
namespace Tests\Feature\Scheduling;
use App\Http\Livewire\Sales;
use App\Models\Appointment;
use App\Models\Cashier;
use App\Models\CashierShift;
use App\Models\Payment;
use App\Models\Voucher;
use App\Models\VoucherSerie;
use App\Services\Billing\AppointmentEconomicPosition;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

class AppointmentWithdrawalTest extends TestCase
{
    use RefreshDatabase, BuildsAgendaLifecycleData;
    private $actor; private $catalog; private $patient;
    protected function setUp(): void
    {
        parent::setUp(); config(['scheduling.enabled' => true, 'app.debug' => false]);
        $this->actor = $this->agendaReader('ADMISION');
        // Caja retains its own role boundary; only this isolated financial test actor has both.
        $this->actor->assignRole(\Spatie\Permission\Models\Role::findOrCreate('RECEPCION', 'web'));
        foreach ([C::CREATE, C::WITHDRAW, C::RESCHEDULE, C::SUBMIT_PAYMENT] as $cap) {
            $this->actor->givePermissionTo(Permission::findOrCreate($cap, 'web'));
        }
        $this->catalog = $this->createAppointmentCatalog(); $this->patient = $this->createPatient($this->actor);
        $this->catalog['rate']->update(['nombre' => 'TARIFA ESTANDAR', 'tipo_tarifa' => 'MONTO_FIJO']);
        $box = Cashier::create(['nombre' => 'Caja ficticia retiro', 'estado' => 'ACTIVO']);
        CashierShift::create(['cashier_id' => $box->id, 'user_id' => $this->actor->id, 'monto_apertura' => 0, 'abierto_en' => now(), 'estado' => 'ABIERTO']);
        VoucherSerie::create(['cashier_id' => $box->id, 'tipo_comprobante' => 'TICKET', 'serie' => 'TW01', 'correlativo_actual' => 0, 'estado' => 'ACTIVO']);
        $this->actingAs($this->actor);
    }
    private function paid(): Appointment
    {
        $id = $this->postJson(route('scheduling.mvp.agenda.registrations'), ['request_key' => (string) Str::uuid(), 'mode' => 'CONFIRM',
            'booking_type' => 'REGULAR', 'patient_id' => $this->patient->id, 'doctor_id' => $this->catalog['doctor']->id,
            'service_id' => $this->catalog['service']->id, 'fecha_cita' => $this->catalog['schedule']->fecha_cita, 'hora_cita' => '08:00',
            'duracion_cita' => 30, 'payment' => ['amount' => '50.00', 'method' => 'EFECTIVO']])->assertCreated()->json('appointment.appointment_id');
        return Appointment::findOrFail($id);
    }
    private function withdraw(Appointment $a, array $extra = [])
    {
        return $this->postJson(route('scheduling.mvp.agenda.withdraw', $a->id), array_replace(['request_key' => (string) Str::uuid(),
            'motivo' => 'Paciente ficticio debe retirarse', 'requested_action' => 'PENDIENTE', 'was_present' => true], $extra));
    }
    private function destination(array $extra = []): array
    {
        return array_replace(['request_key' => (string) Str::uuid(), 'fecha_cita' => $this->catalog['schedule']->fecha_cita,
            'hora_cita' => '08:30', 'duracion_cita' => 30], $extra);
    }
    public function test_withdrawal_retains_original_interval_and_money_but_releases_slot_with_event(): void
    {
        $a = $this->paid(); $before = $a->only(['patient_id', 'doctor_id', 'service_id', 'fecha_cita', 'hora_cita', 'precio_programado', 'total_pagado']);
        $this->withdraw($a, ['was_present' => false])->assertUnprocessable();
        $d = ['request_key' => (string) Str::uuid()]; $this->withdraw($a, $d)->assertOk(); $this->withdraw($a, $d)->assertOk();
        $this->assertSame('RETIRO', $a->fresh()->estado_cita); $this->assertEquals($before, $a->fresh()->only(array_keys($before)));
        $this->assertSame(0, Appointment::consumingRegularSlot()->count()); $this->assertSame(0, Appointment::occupyingInterval()->count());
        $this->assertSame(1, $a->events()->where('event_type', 'RETIRO')->count()); $this->assertEquals(50, Payment::sum('monto'));
        $this->getJson(route('scheduling.mvp.agenda.history', $a->id))->assertOk()->assertJsonPath('events.0.type', 'RETIRO');
    }
    public function test_rebooking_creates_b_and_applies_original_money_exactly_once_without_copying_payment(): void
    {
        $a = $this->paid(); $v = Voucher::first(); $this->withdraw($a)->assertOk();
        $data = $this->destination(['credits' => [['voucher_id' => $v->id, 'amount' => '50.00']]]);
        $response = $this->postJson(route('scheduling.mvp.agenda.rebook-withdrawal', $a->id), $data)->assertCreated()
            ->assertJsonPath('economy.credito_aplicado', '50.00')->assertJsonPath('economy.saldo', '50.00');
        $b = Appointment::findOrFail($response->json('appointment_id'));
        $this->postJson(route('scheduling.mvp.agenda.rebook-withdrawal', $a->id), $data)->assertCreated()->assertJsonPath('appointment_id', $b->id);
        $this->assertNotSame($a->id, $b->id); $this->assertSame('RETIRO', $a->fresh()->estado_cita);
        $this->assertSame('08:00', substr($a->fresh()->hora_cita, 0, 5)); $this->assertEquals(0, $b->total_pagado);
        $this->assertSame('CONFIRMADA', $b->estado_agenda); $this->assertSame($a->patient_id, $b->patient_id);
        $this->assertEquals(50, Payment::sum('monto')); $this->assertDatabaseCount('payments', 1);
        $this->assertSame($a->id, (int) $v->items()->first()->item_id); $this->assertDatabaseCount('appointment_credit_applications', 1);
        $this->assertTrue(app(AppointmentEconomicPosition::class)->forAppointment($b)['secured']);
        $this->postJson(route('scheduling.mvp.agenda.rebook-withdrawal', $a->id), $this->destination())->assertUnprocessable();
        $sale = Livewire::actingAs($this->actor)->test(Sales::class)->call('agregarCitaAlCarrito', $b->id, $b->patient_id, 999, $b->doctor_id)
            ->assertSet('montoACobrar', 50.0)->set('pagoEfectivo', 50)->set('tipoComprobante', 'TICKET')->call('guardarVenta');
        $this->assertEmpty($sale->instance()->getErrorBag()->all(), json_encode($sale->instance()->getErrorBag()->all()));
        $this->assertEquals(100, Payment::sum('monto')); $this->assertSame(0, app(AppointmentEconomicPosition::class)->forAppointment($b->fresh())['balance_cents']);
    }
    public function test_refund_is_only_a_request_and_reserves_money_against_reuse(): void
    {
        $a = $this->paid(); $v = Voucher::first(); $this->withdraw($a)->assertOk();
        $before = $v->getRawOriginal(); $cash = DB::table('cash_movements')->count();
        $d = ['request_key' => (string) Str::uuid(), 'motivo' => 'Solicitud ficticia', 'refunds' => [['voucher_id' => $v->id, 'amount' => '50.00']]];
        $this->postJson(route('scheduling.mvp.agenda.refund-requests', $a->id), $d)->assertCreated();
        $this->postJson(route('scheduling.mvp.agenda.refund-requests', $a->id), $d)->assertCreated();
        $this->assertDatabaseCount('appointment_refund_requests', 1); $this->assertDatabaseCount('payments', 1);
        $this->assertEquals($before, $v->fresh()->getRawOriginal()); $this->assertEquals(50, Payment::sum('monto'));
        $this->assertSame($cash, DB::table('cash_movements')->count());
        $this->postJson(route('scheduling.mvp.agenda.rebook-withdrawal', $a->id), $this->destination(['credits' => [['voucher_id' => $v->id, 'amount' => '1.00']]]))->assertUnprocessable();
        $this->assertDatabaseCount('appointments', 1);
        Livewire::actingAs($this->actor)->test(Sales::class)->call('agregarCitaAlCarrito', $a->id, $a->patient_id, 100, $a->doctor_id)->assertHasErrors('appointment');
    }
    public function test_private_workflow_and_related_private_b_do_not_leak_to_another_actor(): void
    {
        $a = $this->paid(); $this->withdraw($a)->assertOk();
        $id = $this->postJson(route('scheduling.mvp.agenda.rebook-withdrawal', $a->id), $this->destination())->assertCreated()->json('appointment_id');
        $other = $this->agendaReader('ADMISION'); $other->givePermissionTo(Permission::findOrCreate(C::VIEW_AUDIT, 'web'));
        $this->actingAs($other)->getJson(route('scheduling.mvp.agenda.history', $a->id))->assertOk()->assertJsonPath('events.0.related_appointment_id', null);
        foreach ([$id, 999999] as $hidden) { $this->getJson(route('scheduling.mvp.agenda.history', $hidden))->assertNotFound(); }
    }
    public function test_changed_schedule_keeps_reservation_and_creates_private_persistent_contingency(): void
    {
        $a = $this->paid(); $a->update(['estado_agenda' => 'PENDIENTE_CONFIRMACION', 'responsible_user_id' => $this->actor->id]);
        $this->catalog['schedule']->update(['estado' => 'INACTIVO']);
        $this->assertSame('PENDIENTE_CONFIRMACION', $a->fresh()->estado_agenda); $this->assertSame('PROGRAMADO', $a->fresh()->estado_cita);
        $this->getJson(route('scheduling.mvp.agenda.contingencies'))->assertOk()->assertJsonCount(1, 'contingencies');
        $other = $this->agendaReader(); $this->actingAs($other)->getJson(route('scheduling.mvp.agenda.contingencies'))->assertOk()->assertJsonCount(0, 'contingencies');
        $id = DB::table('appointment_contingencies')->value('id');
        $this->patchJson(route('scheduling.mvp.agenda.contingencies.update', $id), [])->assertNotFound();
        $this->actingAs($this->actor)->patchJson(route('scheduling.mvp.agenda.contingencies.update', $id), ['resolution' => 'Seguimiento humano ficticio'])->assertOk();
        $this->assertSame('PROGRAMADO', $a->fresh()->estado_cita);
    }
    public function test_commercial_with_operational_capability_can_withdraw_without_audit_and_cannot_read_hidden_reservation(): void
    {
        $a = $this->paid();
        $commercial = $this->agendaReader('COMERCIAL');
        $commercial->givePermissionTo(Permission::findOrCreate(C::WITHDRAW, 'web'));
        $this->assertFalse($commercial->can(C::VIEW_AUDIT));
        $this->actingAs($commercial)->getJson(route('scheduling.mvp.agenda.history', $a->id))->assertOk();
        $this->withdraw($a)->assertOk();
        $hidden = $this->lifecycleAppointment($this->actor, $this->catalog, ['estado_agenda' => 'PENDIENTE_CONFIRMACION']);
        $this->getJson(route('scheduling.mvp.agenda.history', $hidden->id))->assertNotFound();
        $this->withdraw($hidden)->assertNotFound();
    }

    public function test_schema_accepts_retiro_and_rollback_refuses_history_before_mutation(): void
    {
        $a = $this->paid(); $this->withdraw($a)->assertOk();
        $migration = require database_path('migrations/2026_10_06_160000_add_appointment_withdrawal_and_history.php');
        try { $migration->down(); $this->fail('History must be preserved'); } catch (\RuntimeException $e) { $this->assertStringContainsString('Rollback', $e->getMessage()); }
        $this->assertDatabaseHas('appointments', ['id' => $a->id, 'estado_cita' => 'RETIRO']);
    }
    public function test_credit_and_refund_share_one_budget_and_old_prices_never_change(): void
    {
        $a = $this->paid(); $v = Voucher::first(); $this->withdraw($a)->assertOk();
        $this->postJson(route('scheduling.mvp.agenda.refund-requests', $a->id), ['request_key' => (string) Str::uuid(), 'motivo' => 'Reserva de devolución',
            'refunds' => [['voucher_id' => $v->id, 'amount' => '30.00']]])->assertCreated();
        $this->postJson(route('scheduling.mvp.agenda.rebook-withdrawal', $a->id), $this->destination(['credits' => [['voucher_id' => $v->id, 'amount' => '21.00']]]))->assertUnprocessable();
        $this->catalog['doctorService']->update(['precio_primera_consulta' => 120]);
        $b = $this->postJson(route('scheduling.mvp.agenda.rebook-withdrawal', $a->id), $this->destination(['credits' => [['voucher_id' => $v->id, 'amount' => '20.00']]]))
            ->assertCreated()->assertJsonPath('economy.precio', '120.00')->assertJsonPath('economy.saldo', '100.00')->assertJsonPath('estado_agenda', 'PENDIENTE_CONFIRMACION')->json('appointment_id');
        $this->assertEquals(100, $a->fresh()->precio_programado); $this->assertEquals(0, Appointment::find($b)->total_pagado);
        $this->postJson(route('scheduling.mvp.agenda.refund-requests', $a->id), ['request_key' => (string) Str::uuid(), 'motivo' => 'No disponible',
            'refunds' => [['voucher_id' => $v->id, 'amount' => '0.01']]])->assertUnprocessable();
        $this->assertEquals(50, Payment::sum('monto'));
    }
    public function test_occupied_rebooking_destination_rolls_back_credit_and_b_without_automatic_additional(): void
    {
        $a = $this->paid(); $v = Voucher::first(); $this->withdraw($a)->assertOk();
        $this->lifecycleAppointment($this->actor, $this->catalog, ['fecha_cita' => $this->catalog['schedule']->fecha_cita, 'hora_cita' => '08:30'], $this->patient);
        $this->postJson(route('scheduling.mvp.agenda.rebook-withdrawal', $a->id), $this->destination(['credits' => [['voucher_id' => $v->id, 'amount' => '50.00']]]))->assertStatus(409);
        $this->assertDatabaseCount('appointments', 2); $this->assertDatabaseCount('appointment_credit_applications', 0); $this->assertEquals(50, Payment::sum('monto'));
        $this->assertSame(0, $a->events()->where('event_type', 'REPROGRAMACION_RETIRO')->count());
    }
    public function test_multiline_or_foreign_voucher_cannot_be_transferred_and_retired_fields_are_immutable(): void
    {
        $a = $this->paid(); $v = Voucher::first(); $this->withdraw($a)->assertOk();
        $this->postJson(route('scheduling.mvp.agenda.rebook-withdrawal', $a->id), $this->destination(['credits' => [['voucher_id' => 999999, 'amount' => '50.00']]]))->assertUnprocessable();
        $v->items()->create(['item_type' => 'item', 'item_id' => 999, 'descripcion' => 'Legacy sin distribución', 'cantidad' => 1, 'precio_unitario' => 20,
            'total' => 20, 'afectacion_igv' => '10', 'igv_monto' => 0, 'unidad_medida_sunat' => 'NIU', 'comision_porcentaje' => 0, 'comision_monto' => 0]);
        $this->postJson(route('scheduling.mvp.agenda.rebook-withdrawal', $a->id), $this->destination(['credits' => [['voucher_id' => $v->id, 'amount' => '50.00']]]))->assertUnprocessable();
        try { $a->update(['fecha_cita' => '2099-01-01', 'estado_cita' => 'PROGRAMADO']); $this->fail('RETIRO cannot move'); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertStringContainsString('RETIRO', $e->getMessage()); }
        $this->assertSame('RETIRO', $a->fresh()->estado_cita);
    }
    public function test_capacity_uses_real_payment_and_credit_and_excludes_withdrawal(): void
    {
        $a = $this->paid(); $a->update(['total_pagado' => 0]); $v = Voucher::first();
        $query = new \App\Support\Scheduling\AgendaQuery([$a->doctor_id], \Carbon\Carbon::parse($a->fecha_cita), \Carbon\Carbon::parse($a->fecha_cita));
        $service = app(\App\Services\Scheduling\RegularCapacityService::class);
        $this->assertSame(1, $service->forRange($query)[0]['ocupacion_segura']);
        $this->withdraw($a)->assertOk(); $this->assertSame(0, $service->forRange($query)[0]['ocupacion_segura']);
        $b = $this->postJson(route('scheduling.mvp.agenda.rebook-withdrawal', $a->id), $this->destination(['credits' => [['voucher_id' => $v->id, 'amount' => '50.00']]]))->assertCreated()->json('appointment_id');
        $this->assertEquals(0, Appointment::find($b)->total_pagado); $this->assertSame(1, $service->forRange($query)[0]['ocupacion_segura']);
        // A snapshot without supporting money is not authority for a new operational appointment.
        $untouched = $this->lifecycleAppointment($this->actor, $this->catalog, ['economic_source' => 'VOUCHER', 'total_pagado' => 100,
            'fecha_cita' => $a->fecha_cita, 'hora_cita' => '09:00']);
        $this->assertFalse(app(AppointmentEconomicPosition::class)->forAppointment($untouched)['secured']);
    }
    public function test_missing_withdraw_permission_and_changed_idempotency_payload_are_rejected(): void
    {
        $a = $this->paid(); $key = (string) Str::uuid();
        $this->withdraw($a, ['request_key' => $key])->assertOk();
        $this->withdraw($a, ['request_key' => $key, 'motivo' => 'Payload cambiado'])->assertStatus(409);
        $other = $this->agendaReader('ADMISION'); $this->actingAs($other);
        $this->withdraw($a)->assertForbidden();
    }
    public function test_sales_liquidates_only_real_balance_and_rejects_duplicate_child_and_negative_payment(): void
    {
        $a = $this->paid(); $ticket = Voucher::first(); $box = CashierShift::where('user_id', $this->actor->id)->first()->cashier_id;
        VoucherSerie::create(['cashier_id' => $box, 'tipo_comprobante' => 'BOLETA', 'serie' => 'BW01', 'correlativo_actual' => 0, 'estado' => 'ACTIVO']);
        $sale = Livewire::actingAs($this->actor)->test(Sales::class)->set('atiendeId', $a->patient_id)->call('liquidarTicket', $ticket->id)->assertSet('montoACobrar', 50.0);
        $sale->set('pagoEfectivo', -50)->set('pagoYape', 100)->call('guardarVenta')->assertHasErrors('amount');
        $this->assertDatabaseCount('vouchers', 1); $this->assertEquals(50, Payment::sum('monto'));
        $sale->set('pagoYape', 0)->set('pagoEfectivo', 50)->call('guardarVenta');
        $this->assertEmpty($sale->instance()->getErrorBag()->all(), json_encode($sale->instance()->getErrorBag()->all()));
        $this->assertDatabaseCount('vouchers', 2); $this->assertEquals(100, Payment::sum('monto'));
        $child = Voucher::where('parent_voucher_id', $ticket->id)->firstOrFail();
        $this->assertEquals($ticket->total, $child->total); $this->assertEquals($ticket->igv, $child->igv);
        $this->assertSame(0, app(AppointmentEconomicPosition::class)->forAppointment($a->fresh())['balance_cents']);
        // Retry source selection cannot create another child or another Payment.
        Livewire::actingAs($this->actor)->test(Sales::class)->set('atiendeId', $a->patient_id)->call('liquidarTicket', $ticket->id)->set('pagoEfectivo', 50)->call('guardarVenta')->assertHasErrors('ticket');
        $this->assertDatabaseCount('vouchers', 2); $this->assertDatabaseCount('payments', 2);
    }
}
