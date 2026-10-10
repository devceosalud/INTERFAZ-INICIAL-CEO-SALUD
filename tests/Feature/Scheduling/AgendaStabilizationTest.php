<?php
namespace Tests\Feature\Scheduling;

use App\Models\Appointment;
use App\Models\Cashier;
use App\Models\CashierShift;
use App\Models\Payment;
use App\Models\VoucherSerie;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

class AgendaStabilizationTest extends TestCase
{
    use RefreshDatabase, BuildsAgendaLifecycleData;
    private $actor; private $patient; private $catalog;
    protected function setUp(): void
    {
        parent::setUp();
        config(['scheduling.enabled' => true, 'scheduling.pilot_payment_without_manual_cash_shift' => false, 'app.debug' => false]);
        $this->actor = $this->agendaReader('ADMISION');
        $this->grant(C::CREATE); $this->patient = $this->createPatient($this->actor);
        $this->catalog = $this->createAppointmentCatalog();
        $this->catalog['rate']->update(['nombre' => 'TARIFA ESTANDAR', 'tipo_tarifa' => 'MONTO_FIJO']);
        $this->actingAs($this->actor);
    }
    private function grant(string $permission): void { $this->actor->givePermissionTo(Permission::findOrCreate($permission, 'web')); }
    private function payload(array $changes = []): array
    {
        return array_replace(['request_key' => (string) Str::uuid(), 'mode' => 'RESERVE', 'booking_type' => 'REGULAR',
            'patient_id' => $this->patient->id, 'doctor_id' => $this->catalog['doctor']->id, 'service_id' => $this->catalog['service']->id,
            'fecha_cita' => $this->catalog['schedule']->fecha_cita, 'hora_cita' => '08:00', 'duracion_cita' => 30], $changes);
    }
    private function shift(?int $actor = null, int $series = 1): CashierShift
    {
        $box = Cashier::create(['nombre' => 'Caja QA ficticia', 'estado' => 'ACTIVO']);
        $shift = CashierShift::create(['cashier_id' => $box->id, 'user_id' => $actor ?? $this->actor->id,
            'monto_apertura' => 0, 'abierto_en' => now(), 'estado' => 'ABIERTO']);
        for ($i = 0; $i < $series; $i++) {
            VoucherSerie::create(['cashier_id' => $box->id, 'tipo_comprobante' => 'TICKET', 'serie' => 'Q'.str_pad($box->id * 10 + $i, 3, '0', STR_PAD_LEFT),
                'correlativo_actual' => 0, 'estado' => 'ACTIVO']);
        }
        return $shift;
    }
    private function sendAppointment(string $endpoint, array $data) { return $this->postJson(route($endpoint), $data); }
    public static function endpoints(): array
    {
        return ['compatibility' => ['scheduling.mvp.agenda.appointments.store'], 'operational' => ['scheduling.mvp.agenda.registrations']];
    }
    /** @dataProvider endpoints */
    public function test_create_permission_is_required_even_for_an_unpaid_reservation(string $endpoint): void
    {
        $this->actor->revokePermissionTo(C::CREATE);
        foreach (['RESERVE', 'CONFIRM'] as $mode) { $this->sendAppointment($endpoint, $this->payload(['mode' => $mode]))->assertForbidden(); }
        $this->assertDatabaseCount('appointments', 0); $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('appointment_operations', 0);
    }
    /** @dataProvider endpoints */
    public function test_create_alone_saves_a_reservation_but_never_accepts_positive_money(string $endpoint): void
    {
        $this->sendAppointment($endpoint, $this->payload(['payment' => ['amount' => '50.00', 'method' => 'EFECTIVO']]))->assertForbidden();
        $id = $this->sendAppointment($endpoint, $this->payload())->assertCreated()->assertJsonPath('appointment.estado_agenda', 'PENDIENTE_CONFIRMACION')->json('appointment.appointment_id');
        $this->postJson(route('scheduling.mvp.agenda.payments', $id), ['request_key' => (string) Str::uuid(), 'confirm' => false,
            'payment' => ['amount' => '50.00', 'method' => 'EFECTIVO']])->assertForbidden();
        $this->assertDatabaseCount('payments', 0); $this->assertDatabaseCount('vouchers', 0);
        $this->assertSame(0, Appointment::occupyingInterval()->count());
    }
    public function test_legacy_request_without_intent_saves_pending_and_preserves_historical_occupancy(): void
    {
        $old = $this->lifecycleAppointment($this->actor, $this->catalog, ['hora_cita' => '09:00', 'estado_agenda' => 'LEGADO',
            'tipo_agendamiento' => null, 'economic_source' => 'LEGACY'], $this->patient);
        $before = $old->fresh()->getAttributes();
        $data = $this->payload(['estado_agenda' => 'CONFIRMADA', 'tipo_agendamiento' => 'ADICIONAL', 'total_pagado' => '999.00']);
        unset($data['mode'], $data['booking_type'], $data['request_key']);
        $id = $this->sendAppointment('scheduling.mvp.agenda.appointments.store', $data)->assertCreated()
            ->assertJsonPath('appointment.estado_agenda', 'PENDIENTE_CONFIRMACION')->assertJsonPath('appointment.tipo_agendamiento', 'REGULAR')->json('appointment.appointment_id');
        $this->assertNotSame($old->id, $id); $this->assertSame($before, $old->fresh()->getAttributes());
        $this->assertSame([$old->id], Appointment::consumingRegularSlot()->pluck('id')->all());
        $this->assertEquals(0, Appointment::findOrFail($id)->total_pagado); $this->assertDatabaseCount('payments', 0);
    }
    public static function thresholds(): array
    {
        $rows = [];
        foreach (self::endpoints() as $label => [$endpoint]) {
            foreach (['0.00' => false, '49.99' => false, '50.00' => true] as $amount => $allowed) {
                $rows[$label.'-'.$amount] = [$endpoint, $amount, $allowed];
            }
        }
        return $rows;
    }
    /** @dataProvider thresholds */
    public function test_fifty_percent_is_enforced_on_direct_requests(string $endpoint, string $amount, bool $allowed): void
    {
        $this->grant(C::SUBMIT_PAYMENT); $this->shift();
        $response = $this->sendAppointment($endpoint, $this->payload(['mode' => 'CONFIRM', 'payment' => ['amount' => $amount, 'method' => 'EFECTIVO'],
            'total_pagado' => 100, 'estado_pagado' => 'PAGADO', 'precio_programado' => 1]));
        if ($allowed) {
            $response->assertCreated()->assertJsonPath('appointment.estado_agenda', 'CONFIRMADA');
            $this->assertEquals(50, Payment::sum('monto')); $this->assertSame(1, Appointment::consumingRegularSlot()->count());
        } else {
            $response->assertUnprocessable()->assertJsonValidationErrors('payment.amount');
            $this->assertDatabaseCount('appointments', 0); $this->assertDatabaseCount('payments', 0); $this->assertDatabaseCount('vouchers', 0);
        }
    }
    public function test_legacy_regular_endpoint_cannot_be_switched_into_a_financial_exception(): void
    {
        foreach (['ADICIONAL', 'FUERA_HORARIO'] as $type) {
            $this->sendAppointment('scheduling.mvp.agenda.appointments.store', $this->payload(['booking_type' => $type, 'mode' => 'CONFIRM']))
                ->assertUnprocessable()->assertJsonValidationErrors('booking_type');
        }
        $this->assertDatabaseCount('appointments', 0);
    }
    public function test_authorization_text_or_administrator_role_does_not_replace_exoneration_permission(): void
    {
        $this->actor->assignRole(\Spatie\Permission\Models\Role::findOrCreate('ADMINISTRADOR', 'web'));
        $data = $this->payload(['mode' => 'CONFIRM', 'es_exonerado' => true, 'autorizado_por' => 'QA responsable ficticio']);
        $this->sendAppointment('scheduling.mvp.agenda.appointments.store', $data)->assertForbidden();
        $this->grant(C::OVERRIDE_DOWN_PAYMENT);
        $this->sendAppointment('scheduling.mvp.agenda.appointments.store', $this->payload(['mode' => 'CONFIRM', 'autorizado_por' => 'QA responsable ficticio']))
            ->assertUnprocessable()->assertJsonValidationErrors('payment.amount');
        $this->grant(C::APPROVE_ZERO_COST);
        $this->sendAppointment('scheduling.mvp.agenda.appointments.store', $data)->assertCreated()->assertJsonPath('appointment.estado_agenda', 'CONFIRMADA');
        $this->assertDatabaseCount('payments', 0);
    }
    public function test_foreign_closed_or_multiple_shifts_are_rejected_without_creating_a_pilot_context(): void
    {
        $this->grant(C::SUBMIT_PAYMENT); $other = $this->agendaReader('COMERCIAL'); $this->shift($other->id);
        $data = $this->payload(['mode' => 'CONFIRM', 'payment' => ['amount' => '50.00', 'method' => 'EFECTIVO']]);
        $this->sendAppointment('scheduling.mvp.agenda.appointments.store', $data)->assertUnprocessable()->assertJsonValidationErrors('payment');
        $own = $this->shift(); $own->update(['estado' => 'CERRADO']);
        $this->sendAppointment('scheduling.mvp.agenda.appointments.store', $data)->assertUnprocessable();
        $own->update(['estado' => 'ABIERTO']); $this->shift();
        $this->sendAppointment('scheduling.mvp.agenda.appointments.store', $data)->assertUnprocessable();
        $this->assertDatabaseCount('appointments', 0); $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('appointment_pilot_cash_contexts', 0);
    }
    public function test_missing_or_ambiguous_ticket_series_rolls_back_all_money(): void
    {
        $this->grant(C::SUBMIT_PAYMENT); $shift = $this->shift(null, 0);
        $data = $this->payload(['mode' => 'CONFIRM', 'payment' => ['amount' => '50.00', 'method' => 'EFECTIVO']]);
        $this->sendAppointment('scheduling.mvp.agenda.appointments.store', $data)->assertUnprocessable()->assertJsonValidationErrors('payment');
        foreach (['QA01', 'QA02'] as $series) { VoucherSerie::create(['cashier_id' => $shift->cashier_id, 'tipo_comprobante' => 'TICKET',
            'serie' => $series, 'correlativo_actual' => 0, 'estado' => 'ACTIVO']); }
        $this->sendAppointment('scheduling.mvp.agenda.appointments.store', $data)->assertUnprocessable();
        $this->assertDatabaseCount('appointments', 0); $this->assertDatabaseCount('payments', 0); $this->assertDatabaseCount('vouchers', 0);
    }
    public function test_paid_retry_on_legacy_endpoint_is_idempotent_and_a_changed_payload_cannot_charge_again(): void
    {
        $this->grant(C::SUBMIT_PAYMENT); $this->shift();
        $data = $this->payload(['mode' => 'CONFIRM', 'payment' => ['amount' => '50.00', 'method' => 'EFECTIVO']]);
        $id = $this->sendAppointment('scheduling.mvp.agenda.appointments.store', $data)->assertCreated()->json('appointment.appointment_id');
        $this->sendAppointment('scheduling.mvp.agenda.appointments.store', $data)->assertCreated()->assertJsonPath('appointment.appointment_id', $id);
        $data['payment']['amount'] = '60.00'; $this->sendAppointment('scheduling.mvp.agenda.appointments.store', $data)->assertConflict();
        $this->assertDatabaseCount('appointments', 1); $this->assertDatabaseCount('payments', 1); $this->assertEquals(50, Payment::sum('monto'));
    }
    public function test_money_or_explicit_confirmation_requires_a_client_idempotency_key_on_compatibility_endpoint(): void
    {
        $this->grant(C::SUBMIT_PAYMENT); $this->shift();
        foreach (['RESERVE', 'CONFIRM'] as $mode) {
            $data = $this->payload(['mode' => $mode, 'payment' => ['amount' => '50.00', 'method' => 'EFECTIVO']]);
            unset($data['request_key']);
            $this->sendAppointment('scheduling.mvp.agenda.appointments.store', $data)
                ->assertUnprocessable()->assertJsonValidationErrors('request_key');
        }
        $this->assertDatabaseCount('appointments', 0); $this->assertDatabaseCount('payments', 0);
    }

    public function test_register_advance_and_confirm_are_distinct_and_confirmation_does_not_require_another_charge(): void
    {
        $this->grant(C::SUBMIT_PAYMENT); $this->shift();
        $id = $this->sendAppointment('scheduling.mvp.agenda.appointments.store', $this->payload())->assertCreated()->json('appointment.appointment_id');
        $url = route('scheduling.mvp.agenda.payments', $id);
        $this->postJson($url, ['request_key' => (string) Str::uuid(), 'confirm' => false, 'payment' => ['amount' => '49.99', 'method' => 'EFECTIVO']])
            ->assertOk()->assertJsonPath('appointment.estado_agenda', 'PENDIENTE_CONFIRMACION');
        $this->postJson($url, ['request_key' => (string) Str::uuid(), 'confirm' => true])->assertUnprocessable();
        $this->postJson($url, ['request_key' => (string) Str::uuid(), 'confirm' => false, 'payment' => ['amount' => '0.01', 'method' => 'EFECTIVO']])->assertOk();
        $this->actor->revokePermissionTo(C::SUBMIT_PAYMENT);
        $key = (string) Str::uuid();
        $this->postJson($url, ['request_key' => $key, 'confirm' => true])->assertOk()->assertJsonPath('appointment.estado_agenda', 'CONFIRMADA');
        $this->postJson($url, ['request_key' => $key, 'confirm' => true])->assertOk();
        $this->assertDatabaseCount('payments', 2); $this->assertEquals(50, Payment::sum('monto'));
    }

    public static function nonConfirmationIntents(): array
    {
        return ['omitted' => [[]], 'false' => [['confirm' => false]], 'zero' => [['confirm' => 0]], 'form-zero' => [['confirm' => '0']]];
    }
    /** @dataProvider nonConfirmationIntents */
    public function test_advance_at_fifty_percent_never_confirms_without_explicit_true(array $intent): void
    {
        $this->grant(C::SUBMIT_PAYMENT); $this->shift();
        $id = $this->sendAppointment('scheduling.mvp.agenda.registrations', $this->payload())->assertCreated()->json('appointment.appointment_id');
        $url = route('scheduling.mvp.agenda.payments', $id);
        $data = $intent + ['request_key' => (string) Str::uuid(), 'payment' => ['amount' => '50.00', 'method' => 'EFECTIVO']];
        for ($i = 0; $i < 2; $i++) {
            $this->postJson($url, $data)->assertOk()->assertJsonPath('appointment.estado_agenda', 'PENDIENTE_CONFIRMACION')
                ->assertJsonPath('appointment.tipo_agendamiento', 'REGULAR')->assertJsonPath('economy.pago_real', '50.00');
        }
        $this->assertDatabaseCount('payments', 1); $this->assertDatabaseCount('vouchers', 1);
        $this->assertDatabaseCount('appointment_operations', 2); $this->assertEquals(50, Payment::sum('monto'));
        $this->assertSame(0, Appointment::consumingRegularSlot()->count());
        $this->postJson($url, ['request_key' => (string) Str::uuid(), 'confirm' => 'true'])->assertUnprocessable()->assertJsonValidationErrors('confirm');
        $this->assertDatabaseCount('payments', 1); $this->assertDatabaseCount('appointment_operations', 2);
    }
    public static function explicitConfirmationIntents(): array { return [[true], [1], ['1']]; }
    /** @dataProvider explicitConfirmationIntents */
    public function test_validated_explicit_boolean_confirmation_remains_supported($intent): void
    {
        $this->grant(C::SUBMIT_PAYMENT); $this->shift();
        $id = $this->sendAppointment('scheduling.mvp.agenda.registrations', $this->payload())->assertCreated()->json('appointment.appointment_id');
        $this->postJson(route('scheduling.mvp.agenda.payments', $id), ['request_key' => (string) Str::uuid(), 'confirm' => $intent,
            'payment' => ['amount' => '50.00', 'method' => 'EFECTIVO']])->assertOk()->assertJsonPath('appointment.estado_agenda', 'CONFIRMADA');
        $this->assertDatabaseCount('payments', 1); $this->assertSame(1, Appointment::consumingRegularSlot()->count());
    }
    public static function occupiedReservationCases(): array
    {
        return [[false, '0.00'], [false, '25.00'], [true, '0.00'], [true, '25.00']];
    }
    /** @dataProvider occupiedReservationCases */
    public function test_occupied_regular_confirmation_rolls_back_all_new_money_and_never_converts(bool $additionalPermission, string $paid): void
    {
        $this->grant(C::SUBMIT_PAYMENT); $this->shift();
        if ($additionalPermission) { $this->grant(C::CREATE_ADDITIONAL); }
        $this->assertSame($additionalPermission, $this->actor->can(C::CREATE_ADDITIONAL));
        $id = $this->sendAppointment('scheduling.mvp.agenda.registrations', $this->payload())->assertCreated()->json('appointment.appointment_id');
        $url = route('scheduling.mvp.agenda.payments', $id);
        if ($paid !== '0.00') {
            $this->postJson($url, ['request_key' => (string) Str::uuid(), 'payment' => ['amount' => $paid, 'method' => 'EFECTIVO']])->assertOk();
        }
        $other = $this->createPatient($this->actor, ['numero_identidad' => '70000988', 'historia_clinica' => 'QA-COLLISION']);
        $occupied = $this->sendAppointment('scheduling.mvp.agenda.registrations', $this->payload(['patient_id' => $other->id, 'mode' => 'CONFIRM',
            'payment' => ['amount' => '50.00', 'method' => 'EFECTIVO']]))->assertCreated()->json('appointment.appointment_id');
        $tables = ['appointments', 'payments', 'vouchers', 'voucher_items', 'voucher_series', 'appointment_operations', 'appointment_events', 'appointment_documents', 'cashier_shifts'];
        $before = [];
        foreach ($tables as $table) { $before[$table] = \Illuminate\Support\Facades\DB::table($table)->orderBy('id')->get()->toJson(); }
        $amount = $paid === '0.00' ? '50.00' : '25.00';
        $data = ['request_key' => (string) Str::uuid(), 'confirm' => true, 'payment' => ['amount' => $amount, 'method' => 'YAPE', 'operation' => 'QA-CONFLICT', 'origin' => 'YAPE']];
        for ($i = 0; $i < 2; $i++) {
            $this->postJson($url, $data)->assertConflict()->assertJsonPath('message', 'El horario seleccionado ya no se encuentra disponible. Actualiza la agenda y selecciona otro horario.');
            foreach ($tables as $table) { $this->assertSame($before[$table], \Illuminate\Support\Facades\DB::table($table)->orderBy('id')->get()->toJson(), $table.' changed after rejected confirmation'); }
        }
        $this->assertSame('PENDIENTE_CONFIRMACION', Appointment::findOrFail($id)->estado_agenda);
        $this->assertSame('REGULAR', Appointment::findOrFail($id)->tipo_agendamiento);
        $this->assertSame([$occupied], Appointment::consumingRegularSlot()->pluck('id')->all());
        // A deliberate payment-only retry remains valid even though the regular interval is occupied.
        $data['request_key'] = (string) Str::uuid(); unset($data['confirm']);
        $this->postJson($url, $data)->assertOk()->assertJsonPath('appointment.estado_agenda', 'PENDIENTE_CONFIRMACION');
        $this->postJson($url, $data)->assertOk();
        $this->assertEquals(100, Payment::sum('monto')); $this->assertDatabaseCount('payments', $paid === '0.00' ? 2 : 3);
        $this->assertSame([$occupied], Appointment::consumingRegularSlot()->pluck('id')->all());
    }

    public function test_deferred_multiple_proofs_are_rejected_with_laravel_nine_validation(): void
    {
        $this->sendAppointment('scheduling.mvp.agenda.appointments.store', $this->payload(['proofs' => ['unsupported']]))
            ->assertUnprocessable()->assertJsonValidationErrors('proofs');
        $this->sendAppointment('scheduling.mvp.agenda.registrations', $this->payload(['proofs' => ['unsupported']]))
            ->assertUnprocessable()->assertJsonValidationErrors('proofs');
        $this->assertDatabaseCount('appointments', 0);
    }
}
