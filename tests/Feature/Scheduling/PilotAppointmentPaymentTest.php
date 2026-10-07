<?php

namespace Tests\Feature\Scheduling;

use App\Models\{Appointment, Cashier, CashierShift, Payment, Voucher, VoucherSerie};
use App\Services\Billing\AppointmentEconomicPosition;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

class PilotAppointmentPaymentTest extends TestCase
{
    use RefreshDatabase, BuildsAgendaLifecycleData;

    /** @dataProvider operators */
    public function test_authorized_operator_records_real_idempotent_payment_without_manual_shift(string $role): void
    {
        config(['scheduling.enabled' => true, 'scheduling.pilot_payment_without_manual_cash_shift' => true]);
        $actor = $this->agendaReader($role);
        foreach ([C::CREATE, C::SUBMIT_PAYMENT] as $cap) { $actor->givePermissionTo(Permission::findOrCreate($cap, 'web')); }
        $catalog = $this->createAppointmentCatalog();
        $catalog['rate']->update(['nombre' => 'TARIFA ESTANDAR', 'tipo_tarifa' => 'MONTO_FIJO']);
        $patient = $this->createPatient($actor);
        $payload = ['request_key' => (string) Str::uuid(), 'mode' => 'CONFIRM', 'booking_type' => 'REGULAR',
            'patient_id' => $patient->id, 'doctor_id' => $catalog['doctor']->id, 'service_id' => $catalog['service']->id,
            'fecha_cita' => $catalog['schedule']->fecha_cita, 'hora_cita' => '08:00', 'duracion_cita' => 30,
            'payment' => ['amount' => '50.00', 'method' => 'YAPE', 'operation' => 'QA-PILOT-123', 'origin' => 'Yape']];
        $response = $this->actingAs($actor)->postJson(route('scheduling.mvp.agenda.registrations'), $payload)->assertCreated()
            ->assertJsonPath('economy.pago_real', '50.00')->assertJsonPath('economy.saldo', '50.00')->assertJsonPath('economy.asegurada', true);
        $this->postJson(route('scheduling.mvp.agenda.registrations'), $payload)->assertCreated();
        $this->assertDatabaseCount('payments', 1); $this->assertDatabaseCount('vouchers', 1);
        $context = DB::table('appointment_pilot_cash_contexts')->sole();
        $this->assertSame('AGENDA_PILOT_AUTO', $context->origin);
        $this->assertSame($actor->id, (int) $context->actor_user_id);
        $payment = Payment::firstOrFail(); $voucher = Voucher::firstOrFail();
        $this->assertSame($actor->id, (int) $payment->user_id);
        $this->assertSame((int) $context->cashier_shift_id, (int) $payment->cashier_shift_id);
        $this->assertSame('QA-PILOT-123', $payment->numero_operacion);
        $this->assertFalse((bool) $voucher->requiere_sunat); $this->assertSame('NO_APLICA', $voucher->estado_sunat);
        $this->assertDatabaseCount('cash_movements', 0);
        $this->assertSame(0, CashierShift::manual()->count()); $this->assertSame(0, Cashier::manual()->count());
        $id = $response->json('appointment.appointment_id');
        $pay = ['request_key' => (string) Str::uuid(), 'confirm' => false, 'payment' => ['amount' => '25.00', 'method' => 'EFECTIVO']];
        $this->postJson(route('scheduling.mvp.agenda.payments', $id), $pay)->assertOk()->assertJsonPath('economy.saldo', '25.00');
        $this->postJson(route('scheduling.mvp.agenda.payments', $id), $pay)->assertOk();
        $this->assertDatabaseCount('appointment_pilot_cash_contexts', 1);
        $this->assertDatabaseCount('payments', 2); $this->assertEquals(75, Payment::sum('monto'));
        config(['scheduling.pilot_payment_without_manual_cash_shift' => false]);
        $this->postJson(route('scheduling.mvp.agenda.payments', $id), ['request_key' => (string) Str::uuid(), 'confirm' => false,
            'payment' => ['amount' => '25.00', 'method' => 'EFECTIVO']])->assertUnprocessable();
        $this->assertEquals(75, Payment::sum('monto'));
        $this->assertTrue(app(AppointmentEconomicPosition::class)->forAppointment(Appointment::findOrFail($id))['secured']);
    }

    public static function operators(): array { return [['COMERCIAL'], ['ADMISION'], ['ADMINISTRADOR']]; }

    public function test_pilot_requires_capability_role_and_transaction_and_never_reuses_a_foreign_series(): void
    {
        config(['scheduling.pilot_payment_without_manual_cash_shift' => true]);
        $actor = $this->agendaReader('ADMISION');
        try { DB::transaction(fn () => app(\App\Services\Billing\PilotAppointmentCashContext::class)->resolve($actor->id)); $this->fail(); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        $actor->givePermissionTo(Permission::findOrCreate(C::SUBMIT_PAYMENT, 'web'));
        $code = 'P'.str_pad(strtoupper(base_convert((string) $actor->id, 10, 36)), 3, '0', STR_PAD_LEFT);
        VoucherSerie::create(['tipo_comprobante' => 'TICKET', 'serie' => $code, 'estado' => 'ACTIVO']);
        try { DB::transaction(fn () => app(\App\Services\Billing\PilotAppointmentCashContext::class)->resolve($actor->id)); $this->fail(); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertStringContainsString('ya está en uso', $e->getMessage()); }
        $this->assertDatabaseCount('cashier_shifts', 0); $this->assertDatabaseCount('appointment_pilot_cash_contexts', 0);
        $reception = $this->agendaReader('RECEPCION'); $reception->givePermissionTo(C::SUBMIT_PAYMENT);
        try { DB::transaction(fn () => app(\App\Services\Billing\PilotAppointmentCashContext::class)->resolve($reception->id)); $this->fail(); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
    }

    public function test_daily_context_preserves_actor_series_and_rollback_does_not_erase_provenance(): void
    {
        config(['scheduling.pilot_payment_without_manual_cash_shift' => true]);
        $actor = $this->agendaReader('COMERCIAL'); $actor->givePermissionTo(Permission::findOrCreate(C::SUBMIT_PAYMENT, 'web'));
        $cash = app(\App\Services\Billing\PilotAppointmentCashContext::class);
        $first = DB::transaction(fn () => $cash->resolve($actor->id));
        $this->travel(1)->days();
        $second = DB::transaction(fn () => $cash->resolve($actor->id));
        $this->assertNotSame($first->id, $second->id); $this->assertSame($first->cashier_id, $second->cashier_id);
        $this->assertDatabaseCount('voucher_series', 1); $this->assertDatabaseCount('appointment_pilot_cash_contexts', 2);
        $migration = require database_path('migrations/2026_10_07_120000_create_appointment_pilot_cash_contexts.php');
        try { $migration->down(); $this->fail(); } catch (\RuntimeException $e) { $this->assertStringContainsString('Rollback', $e->getMessage()); }
        $this->assertDatabaseCount('appointment_pilot_cash_contexts', 2);
    }
}
