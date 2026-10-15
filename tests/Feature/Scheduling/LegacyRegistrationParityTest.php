<?php
namespace Tests\Feature\Scheduling;

use App\Models\AdditionalRate;
use App\Models\Appointment;
use App\Models\Cashier;
use App\Models\CashierShift;
use App\Models\DoctorService;
use App\Models\Payment;
use App\Models\VoucherSerie;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

class LegacyRegistrationParityTest extends TestCase
{
    use RefreshDatabase, BuildsAgendaLifecycleData;
    private $actor; private $patient; private array $catalog;

    protected function setUp(): void
    {
        parent::setUp(); config(['scheduling.enabled' => true, 'app.debug' => false]);
        $this->actor = $this->agendaReader('ADMISION');
        foreach ([C::CREATE, C::SUBMIT_PAYMENT, C::UPDATE, C::RESCHEDULE] as $p) {
            $this->actor->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }
        $this->patient = $this->createPatient($this->actor);
        $this->catalog = $this->createAppointmentCatalog();
        $this->catalog['rate']->update(['nombre' => 'TARIFA ESTANDAR', 'tipo_tarifa' => 'MONTO_FIJO']);
        $box = Cashier::create(['nombre' => 'Caja ficticia', 'estado' => 'ACTIVO']);
        CashierShift::create(['cashier_id' => $box->id, 'user_id' => $this->actor->id,
            'monto_apertura' => 0, 'abierto_en' => now(), 'estado' => 'ABIERTO']);
        VoucherSerie::create(['cashier_id' => $box->id, 'tipo_comprobante' => 'TICKET',
            'serie' => 'QA01', 'correlativo_actual' => 0, 'estado' => 'ACTIVO']);
        $this->actingAs($this->actor);
    }

    public function entries(): array { return [['legacy'], ['operational']]; }
    private function payload(string $entry, array $changes = []): array
    {
        $base = ['request_key' => (string) Str::uuid(), 'patient_id' => $this->patient->id,
            'doctor_id' => $this->catalog['doctor']->id, 'service_id' => $entry === 'legacy'
                ? $this->catalog['doctorService']->id : $this->catalog['service']->id,
            'additional_rate_id' => $this->catalog['rate']->id, 'fecha_cita' => $this->catalog['schedule']->fecha_cita,
            'hora_cita' => '09:00', 'mode' => 'RESERVE'];
        $base += $entry === 'legacy' ? ['total_pagado' => '0', 'metodo_pago' => 'EFECTIVO',
            'precio_programado' => '100.00']
            : ['booking_type' => 'REGULAR', 'duracion_cita' => 30, 'payment' => ['amount' => '0', 'method' => 'EFECTIVO']];
        return array_replace($base, $changes);
    }
    private function url(string $entry): string { return $entry === 'legacy' ? '/admissionist/appointment/store' : '/scheduling-mvp/agenda/registrations'; }
    private function pay(string $entry, string $amount, array $extra = []): array
    {
        return $entry === 'legacy' ? ['total_pagado' => $amount] + $extra
            : ['payment' => ['amount' => $amount, 'method' => 'EFECTIVO'] + $extra];
    }

    /** @dataProvider entries */
    public function test_unpaid_reservation_does_not_occupy_and_browser_totals_are_not_authority(string $entry): void
    {
        $this->postJson($this->url($entry), $this->payload($entry, ['estado_agenda' => 'CONFIRMADA', 'estado_pagado' => 'PAGADO']))->assertSuccessful();
        $a = Appointment::firstOrFail();
        $this->assertEquals(100, $a->precio_programado); $this->assertEquals(100, $a->saldo_pendiente);
        $this->assertEquals(0, $a->total_pagado); $this->assertSame('PENDIENTE_CONFIRMACION', $a->estado_agenda);
        $this->assertSame(0, Appointment::occupyingInterval()->count()); $this->assertDatabaseCount('payments', 0);
    }

    /** @dataProvider entries */
    public function test_manipulated_price_balance_and_invalid_payment_are_rejected_atomically(string $entry): void
    {
        foreach ([['precio_programado' => '0.01'], ['saldo_pendiente' => '0'], ['saldo_pendiente' => '-999'],
            $this->pay($entry, '101'), $this->pay($entry, '1e3')] as $changes) {
            $this->postJson($this->url($entry), $this->payload($entry, $changes))->assertUnprocessable();
            $this->assertDatabaseCount('appointments', 0); $this->assertDatabaseCount('payments', 0);
            $this->assertDatabaseCount('vouchers', 0); $this->assertDatabaseCount('appointment_operations', 0);
        }
    }

    public function test_confirming_a_paid_legacy_appointment_requires_create_permission(): void
    {
        $a = $this->lifecycleAppointment($this->actor, $this->catalog, ['fecha_cita' => $this->catalog['schedule']->fecha_cita,
            'total_pagado' => 50, 'saldo_pendiente' => 50]);
        $before = $a->fresh()->getAttributes(); $this->actor->revokePermissionTo(C::CREATE);
        $this->postJson('/admissionist/appointment/update', ['appointment_id' => $a->id, 'estado_cita' => 'CONFIRMADO'])->assertForbidden();
        $this->assertSame($before, $a->fresh()->getAttributes()); $this->assertDatabaseCount('payments', 0);
    }

    /** @dataProvider entries */
    public function test_private_duplicate_errors_do_not_disclose_the_reservation(string $entry): void
    {
        $other = $this->agendaReader('COMERCIAL');
        $hidden = $this->lifecycleAppointment($other, $this->catalog, ['estado_agenda' => 'PENDIENTE_CONFIRMACION',
            'fecha_cita' => $this->catalog['schedule']->fecha_cita, 'hora_cita' => '09:00:00'], $this->patient);
        $this->assertSame(0, Appointment::visibleToAgendaUser($this->actor->id)->whereKey($hidden->id)->count());
        $response = $this->postJson($this->url($entry), $this->payload($entry))->assertUnprocessable();
        $response->assertJsonPath('errors.patient_id.0', 'No se pudo registrar en este intervalo. Revisa la disponibilidad o solicita revisión.')
            ->assertDontSee($hidden->numero_cita)->assertDontSee($other->name);
        $this->assertDatabaseCount('appointments', 1); $this->assertDatabaseCount('payments', 0);
    }

    public function test_legacy_calendar_uses_assignment_ids_and_rejects_stale_edits(): void
    {
        $this->catalog['doctorService']->update(['estado' => 'INACTIVO']);
        $active = DoctorService::create(['doctor_id' => $this->catalog['doctor']->id,
            'service_id' => $this->catalog['service']->id, 'precio_primera_consulta' => 100, 'estado' => 'ACTIVO']);
        $a = $this->lifecycleAppointment($this->actor, $this->catalog, ['fecha_cita' => $this->catalog['schedule']->fecha_cita]);
        $response = $this->getJson('/admissionist/reservation/list-calendar?'.http_build_query([
            'start' => $a->fecha_cita, 'end' => $a->fecha_cita]))->assertOk();
        $row = collect($response->json())->firstWhere('id', $a->id);
        $this->assertSame($active->id, $row['doctor_service_id']);
        $p = ['appointment_id' => $a->id, 'estado_cita' => 'PROGRAMADO', 'doctor_id_edit' => $a->doctor_id,
            'service_id_edit' => $row['doctor_service_id'], 'fecha_cita_edit' => substr($a->fecha_cita, 0, 10),
            'hora_cita_edit' => '09:00', 'expected_fecha_cita' => substr($a->fecha_cita, 0, 10), 'expected_hora_cita' => '09:00'];
        $economic = $a->fresh()->only(['precio_programado', 'total_pagado', 'saldo_pendiente', 'estado_cita', 'estado_agenda']);
        $this->postJson('/admissionist/schedule/update', $p)->assertOk();
        $this->assertSame($economic, $a->fresh()->only(array_keys($economic)));
        $before = $a->fresh()->getAttributes();
        $this->postJson('/admissionist/schedule/update', array_replace($p,
            ['hora_cita_edit' => '09:30', 'expected_hora_cita' => '08:30']))->assertStatus(409);
        $this->assertSame($before, $a->fresh()->getAttributes());
    }

    /** @dataProvider entries */
    public function test_confirmation_below_half_rolls_back_all_money_and_rows(string $entry): void
    {
        foreach (['0', '49.99'] as $amount) {
            $this->postJson($this->url($entry), $this->payload($entry, ['mode' => 'CONFIRM'] + $this->pay($entry, $amount)))
                ->assertUnprocessable();
            $this->assertDatabaseCount('appointments', 0); $this->assertDatabaseCount('payments', 0);
            $this->assertDatabaseCount('vouchers', 0); $this->assertDatabaseCount('appointment_operations', 0);
        }
    }

    /** @dataProvider entries */
    public function test_paid_reservation_stays_pending_and_replay_cannot_charge_again(string $entry): void
    {
        $payload = $this->payload($entry, $this->pay($entry, '50.00'));
        $this->postJson($this->url($entry), $payload)->assertSuccessful();
        $this->catalog['doctorService']->update(['precio_primera_consulta' => 999]);
        $this->postJson($this->url($entry), $payload)->assertSuccessful();
        $this->assertDatabaseCount('appointments', 1); $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('vouchers', 1); $this->assertEquals(50, Payment::sum('monto'));
        $this->assertEquals(100, Appointment::first()->precio_programado);
        $this->assertSame('PENDIENTE_CONFIRMACION', Appointment::first()->estado_agenda);
        $this->postJson($this->url($entry), array_replace($payload, $this->pay($entry, '60.00')))->assertStatus(409);
        $this->assertDatabaseCount('payments', 1);
    }

    /** @dataProvider entries */
    public function test_explicit_confirmation_at_half_occupies_once(string $entry): void
    {
        $p = $this->payload($entry, ['mode' => 'CONFIRM'] + $this->pay($entry, '50.00'));
        $this->postJson($this->url($entry), $p)->assertSuccessful();
        $this->postJson($this->url($entry), $p)->assertSuccessful();
        $this->assertSame(1, Appointment::occupyingInterval()->count()); $this->assertDatabaseCount('payments', 1);
        $this->assertSame('CONFIRMADA', Appointment::first()->estado_agenda);
    }

    /** @dataProvider entries */
    public function test_role_alone_and_missing_payment_or_waiver_permission_cannot_bypass(string $entry): void
    {
        $plain = $this->createUserWithRole('ADMISION');
        $this->actingAs($plain)->postJson($this->url($entry), $this->payload($entry))->assertForbidden();
        $this->actingAs($this->actor); $this->actor->revokePermissionTo(C::SUBMIT_PAYMENT);
        $this->postJson($this->url($entry), $this->payload($entry, $this->pay($entry, '50')))->assertForbidden();
        $this->postJson($this->url($entry), $this->payload($entry, ['mode' => 'CONFIRM',
            'es_exonerado' => true, 'motivo_exoneracion'=>'Motivo ficticio autorizado', 'autorizado_por' => 'Autorización ficticia']))->assertForbidden();
        $this->assertDatabaseCount('appointments', 0); $this->assertDatabaseCount('payments', 0);
    }

    /** @dataProvider entries */
    public function test_authorized_waiver_keeps_catalogue_price_and_does_not_invent_money(string $entry): void
    {
        $this->actor->givePermissionTo(Permission::findOrCreate(C::APPROVE_ZERO_COST, 'web'));
        $this->postJson($this->url($entry), $this->payload($entry, ['mode' => 'CONFIRM',
            'es_exonerado' => true, 'motivo_exoneracion'=>'Motivo ficticio autorizado', 'autorizado_por' => 'Autorización ficticia']))->assertSuccessful();
        $a = Appointment::first(); $this->assertEquals(100, $a->precio_programado);
        $this->assertEquals(0, $a->saldo_pendiente); $this->assertEquals(0, $a->total_pagado);
        $this->assertSame('CONFIRMADA', $a->estado_agenda); $this->assertDatabaseCount('payments', 0);
    }

    /** @dataProvider entries */
    public function test_occupied_slot_rejects_confirmation_and_retains_history(string $entry): void
    {
        $historic = $this->lifecycleAppointment($this->actor, $this->catalog, ['hora_cita' => '09:00:00',
            'fecha_cita' => $this->catalog['schedule']->fecha_cita, 'precio_programado' => 175,
            'total_pagado' => 33, 'saldo_pendiente' => 142]);
        $before = $historic->fresh()->getAttributes();
        $this->postJson($this->url($entry), $this->payload($entry, ['mode' => 'CONFIRM'] + $this->pay($entry, '50')))->assertStatus(409);
        $this->assertSame($before, $historic->fresh()->getAttributes()); $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseCount('payments', 0);
    }

    /** @dataProvider entries */
    public function test_special_tariff_without_medical_indication_uses_normal_consultation_in_both_entries(string $entry): void
    {
        // Different assignment/service IDs exercise the legacy ID translation.
        $this->catalog['doctorService']->update(['estado' => 'INACTIVO']);
        $assignment = DoctorService::create(['doctor_id' => $this->catalog['doctor']->id,
            'service_id' => $this->catalog['service']->id, 'precio_primera_consulta' => 100,
            'precio_reconsulta' => 50, 'dias_reconsulta' => 7, 'estado' => 'ACTIVO']);
        $this->catalog['doctorService'] = $assignment;
        $this->lifecycleAppointment($this->actor, $this->catalog, ['estado_cita' => 'ATENDIDO'], $this->patient);
        $rate = AdditionalRate::create(['nombre' => 'Tarifa ficticia', 'tipo_tarifa' => 'MONTO_FIJO', 'tarifa' => 30, 'estado' => 'ACTIVO']);
        $p = $this->payload($entry, ['additional_rate_id' => $rate->id, 'precio_programado' => '130.00']);
        $this->postJson($this->url($entry), $p)->assertSuccessful();
        $this->assertEquals(130, Appointment::where('estado_agenda', 'PENDIENTE_CONFIRMACION')->first()->precio_programado);
        $quote = $entry === 'legacy' ? '/api/appointment/calculated' : '/scheduling-mvp/agenda/quote';
        $this->postJson($quote, $p)->assertOk()->assertJsonPath('tipo', 'PRIMERA_CONSULTA');
    }

    /** @dataProvider entries */
    public function test_invalid_or_expired_rate_is_rejected_without_side_effects(string $entry): void
    {
        $this->catalog['rate']->update(['fecha_fin' => now()->subDay()->toDateString()]);
        $this->postJson($this->url($entry), $this->payload($entry))->assertUnprocessable();
        $this->assertDatabaseCount('appointments', 0); $this->assertDatabaseCount('payments', 0);
    }

    public function test_legacy_money_and_confirmation_require_a_reusable_key(): void
    {
        foreach ([['total_pagado' => '1'], ['mode' => 'CONFIRM']] as $changes) {
            $p = $this->payload('legacy', $changes); unset($p['request_key']);
            $this->postJson($this->url('legacy'), $p)->assertUnprocessable()->assertJsonValidationErrors('request_key');
        }
        $p = $this->payload('legacy'); unset($p['request_key'], $p['mode']);
        $this->postJson($this->url('legacy'), $p)->assertOk();
        $this->assertSame('PENDIENTE_CONFIRMACION', Appointment::first()->estado_agenda);
    }

    public function test_bank_operation_cannot_be_reused_across_legacy_and_operational_entries(): void
    {
        $p = $this->payload('legacy', ['total_pagado' => '50', 'metodo_pago' => 'YAPE', 'numero_operacion' => 'QA-BANK-1', 'entidad_origen' => 'YAPE']);
        $this->postJson($this->url('legacy'), $p)->assertOk();
        $other = $this->createPatient($this->actor, ['numero_identidad' => '70000999']);
        $this->postJson($this->url('operational'), $this->payload('operational', ['patient_id' => $other->id,
            'hora_cita' => '10:00', 'payment' => ['amount' => '50', 'method' => 'YAPE', 'operation' => 'QA-BANK-1', 'origin' => 'YAPE']]))->assertUnprocessable();
        $this->assertDatabaseCount('payments', 1); $this->assertDatabaseCount('appointments', 1);
    }

    public function test_legacy_double_cannot_confirm_over_second_slot_or_past_block_end(): void
    {
        $this->lifecycleAppointment($this->actor, $this->catalog, ['fecha_cita' => $this->catalog['schedule']->fecha_cita, 'hora_cita' => '09:30:00']);
        $this->postJson($this->url('legacy'), $this->payload('legacy', ['cita_doble' => true, 'mode' => 'CONFIRM', 'total_pagado' => '50']))->assertStatus(409);
        $this->postJson($this->url('legacy'), $this->payload('legacy', ['cita_doble' => true, 'hora_cita' => '11:30']))->assertUnprocessable();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_legacy_updates_cannot_rewrite_money_or_promote_unpaid_reservations(): void
    {
        $this->postJson($this->url('legacy'), $this->payload('legacy'))->assertOk(); $a = Appointment::first();
        $before = $a->fresh()->getAttributes();
        foreach (['/admissionist/appointment/update', '/admissionist/schedule/update'] as $url) {
            $p = ['appointment_id' => $a->id, 'estado_cita' => 'CONFIRMADO', 'request_key' => (string) Str::uuid(),
                'doctor_id_edit' => $a->doctor_id, 'service_id_edit' => $this->catalog['doctorService']->id,
                'fecha_cita_edit' => substr($a->fecha_cita, 0, 10), 'hora_cita_edit' => '09:00'];
            $this->postJson($url, $p)->assertUnprocessable();
            $this->postJson($url, $p + ['total_pagado' => 100])->assertUnprocessable();
            $this->assertSame($before, $a->fresh()->getAttributes());
        }
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_paid_reservation_can_confirm_from_legacy_update_without_new_money(): void
    {
        $this->postJson($this->url('legacy'), $this->payload('legacy', ['total_pagado' => '50']))->assertOk();
        $a = Appointment::first(); $p = ['appointment_id' => $a->id, 'estado_cita' => 'CONFIRMADO', 'request_key' => (string) Str::uuid()];
        $this->postJson('/admissionist/appointment/update', $p)->assertOk();
        $this->postJson('/admissionist/appointment/update', $p)->assertOk();
        $this->assertSame('CONFIRMADA', $a->fresh()->estado_agenda); $this->assertDatabaseCount('payments', 1);
    }

    public function test_legacy_reprogramming_reuses_occupancy_and_keeps_price_and_money(): void
    {
        $a = $this->lifecycleAppointment($this->actor, $this->catalog, ['fecha_cita' => $this->catalog['schedule']->fecha_cita,
            'precio_programado' => 175, 'total_pagado' => 90, 'saldo_pendiente' => 85]);
        $blocked = $this->lifecycleAppointment($this->actor, $this->catalog, ['fecha_cita' => $a->fecha_cita, 'hora_cita' => '10:00:00']);
        $p = ['appointment_id' => $a->id, 'estado_cita' => 'PROGRAMADO', 'doctor_id_edit' => $a->doctor_id,
            'service_id_edit' => $this->catalog['doctorService']->id, 'fecha_cita_edit' => substr($a->fecha_cita, 0, 10), 'hora_cita_edit' => '10:00'];
        $before = $a->fresh()->getAttributes();
        $this->postJson('/admissionist/schedule/update', $p)->assertStatus(409);
        $this->assertSame($before, $a->fresh()->getAttributes());
        $p['hora_cita_edit'] = '10:30'; $this->postJson('/admissionist/schedule/update', $p)->assertOk();
        $this->assertEquals(175, $a->fresh()->precio_programado); $this->assertEquals(90, $a->fresh()->total_pagado);
        $this->assertEquals(85, $a->fresh()->saldo_pendiente); $this->assertDatabaseCount('payments', 0);
    }

    public function test_administrator_can_register_confirm_and_edit_with_effective_permissions(): void
    {
        $admin = $this->agendaReader('ADMINISTRADOR');
        foreach ([C::CREATE, C::SUBMIT_PAYMENT, C::UPDATE, C::RESCHEDULE] as $permission) {
            $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        CashierShift::first()->update(['user_id' => $admin->id]);
        $id = $this->actingAs($admin)->postJson($this->url('legacy'), $this->payload('legacy',
            ['mode' => 'CONFIRM', 'total_pagado' => '50.00']))->assertOk()->json('appointment_id');
        $a = Appointment::findOrFail($id);
        $economy = $a->only(['precio_programado', 'total_pagado', 'saldo_pendiente', 'estado_agenda']);
        $this->postJson('/admissionist/appointment/update', ['appointment_id' => $id,
            'estado_cita' => 'CONFIRMADO'])->assertOk();
        $this->postJson('/admissionist/schedule/update', ['appointment_id' => $id, 'estado_cita' => 'CONFIRMADO',
            'doctor_id_edit' => $a->doctor_id, 'service_id_edit' => $this->catalog['doctorService']->id,
            'fecha_cita_edit' => substr($a->fecha_cita, 0, 10), 'hora_cita_edit' => '09:30',
            'expected_fecha_cita' => substr($a->fecha_cita, 0, 10), 'expected_hora_cita' => '09:00'])->assertOk();
        $this->assertSame($economy, $a->fresh()->only(array_keys($economy)));
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_administrator_role_never_grants_payment_or_waiver_capabilities(): void
    {
        $admin = $this->agendaReader('ADMINISTRADOR');
        $admin->givePermissionTo(Permission::findOrCreate(C::CREATE, 'web'));
        CashierShift::first()->update(['user_id' => $admin->id]);
        $this->actingAs($admin)->postJson($this->url('legacy'), $this->payload('legacy',
            ['mode' => 'CONFIRM', 'total_pagado' => '50.00']))->assertForbidden();
        $this->postJson($this->url('legacy'), $this->payload('legacy', ['mode' => 'CONFIRM',
            'es_exonerado' => true, 'motivo_exoneracion'=>'Motivo ficticio autorizado', 'autorizado_por' => 'QA AUTORIZADOR']))->assertForbidden();
        $this->assertDatabaseCount('appointments', 0); $this->assertDatabaseCount('payments', 0);
        $this->postJson($this->url('legacy'), $this->payload('legacy'))->assertOk();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_administrator_and_worker_without_write_permissions_cannot_use_legacy_routes(): void
    {
        $a = $this->lifecycleAppointment($this->actor, $this->catalog, [], $this->patient);
        $before = $a->fresh()->getAttributes();
        foreach (['ADMINISTRADOR', 'ADMISION'] as $role) {
            $reader = $this->agendaReader($role);
            $this->actingAs($reader)->postJson($this->url('legacy'), $this->payload('legacy'))->assertForbidden();
            $this->postJson('/admissionist/appointment/update',
                ['appointment_id' => $a->id, 'estado_cita' => 'CONFIRMADO'])->assertForbidden();
            $this->postJson('/admissionist/schedule/update', ['appointment_id' => $a->id])->assertForbidden();
        }
        $this->assertSame($before, $a->fresh()->getAttributes());
        $this->assertDatabaseCount('appointments', 1); $this->assertDatabaseCount('payments', 0);
    }

    /** @dataProvider entries */
    public function test_inconsistent_private_attendance_is_ignored_for_owner_and_other_worker(string $entry): void
    {
        $this->catalog['doctorService']->update(['precio_reconsulta' => 50, 'dias_reconsulta' => 7]);
        $other = $this->agendaReader('COMERCIAL');
        $history = $this->lifecycleAppointment($other, $this->catalog, ['estado_cita' => 'ATENDIDO',
            'estado_agenda' => 'PENDIENTE_CONFIRMACION', 'responsible_user_id' => $other->id], $this->patient);
        $before = $history->fresh()->getAttributes();
        $url = $entry === 'legacy' ? '/api/appointment/calculated' : '/scheduling-mvp/agenda/quote';
        $payload = $this->payload($entry);
        $quote = $this->postJson($url, $payload)->assertOk()->assertJsonPath('tipo', 'PRIMERA_CONSULTA');
        $this->assertEquals(100, $quote->json('precio_programado'));
        // Patient masters remain searchable; a private reservation does not privatize the identity.
        $this->getJson(route('patients.operational.show', $this->patient->id))->assertOk();
        $this->postJson($this->url($entry), $this->payload($entry, ['hora_cita' => '09:30']))->assertSuccessful();
        $this->assertEquals(100, Appointment::where('id', '<>', $history->id)->first()->precio_programado);
        $quote = $this->actingAs($other)->postJson($url, $payload)->assertOk()->assertJsonPath('tipo', 'PRIMERA_CONSULTA');
        $this->assertEquals(100, $quote->json('precio_programado'));
        $other->givePermissionTo(Permission::findOrCreate(C::CREATE, 'web'));
        $ownResult = $this->postJson($this->url($entry), $this->payload($entry, ['hora_cita' => '10:00']))->assertSuccessful();
        $ownId = $entry === 'legacy' ? $ownResult->json('appointment_id') : $ownResult->json('appointment.appointment_id');
        $this->assertEquals(100, Appointment::findOrFail($ownId)->precio_programado);
        $this->assertSame($before, $history->fresh()->getAttributes());
    }

    /** @dataProvider entries */
    public function test_quote_rejects_readers_without_appointment_view_permission(string $entry): void
    {
        $url = $entry === 'legacy' ? '/api/appointment/calculated' : '/scheduling-mvp/agenda/quote';
        $this->actor->revokePermissionTo(C::VIEW);
        $this->postJson($url, $this->payload($entry))->assertForbidden()->assertJsonMissing(['tipo' => 'RECONSULTA']);
        $this->assertDatabaseCount('appointments', 0);
    }

    public function reconsultationHistories(): array
    {
        return [['legacy', 'LEGADO'], ['legacy', 'CONFIRMADA'],
            ['operational', 'LEGADO'], ['operational', 'CONFIRMADA']];
    }

    /** @dataProvider reconsultationHistories */
    public function test_completed_attendance_without_indication_has_normal_price_for_all_workers(string $entry, string $state): void
    {
        $this->catalog['doctorService']->update(['precio_reconsulta' => 50, 'dias_reconsulta' => 7]);
        $history = $this->lifecycleAppointment($this->actor, $this->catalog, ['estado_cita' => 'ATENDIDO',
            'estado_agenda' => $state, 'fecha_cita' => now()->subDay()->toDateString()], $this->patient);
        $before = $history->fresh()->getAttributes();
        $url = $entry === 'legacy' ? '/api/appointment/calculated' : '/scheduling-mvp/agenda/quote';
        $expected = null;
        foreach ([$this->actor, $this->agendaReader('COMERCIAL'), $this->agendaReader('ADMINISTRADOR')] as $i => $worker) {
            $worker->givePermissionTo(Permission::findOrCreate(C::CREATE, 'web'));
            $payload = $this->payload($entry, ['precio_programado' => '100.00', 'hora_cita' => ['09:30', '10:00', '10:30'][$i]]);
            $quote = $this->actingAs($worker)->postJson($url, $payload)->assertOk()->assertJsonPath('tipo', 'PRIMERA_CONSULTA');
            $this->assertEquals(100, $quote->json('precio_programado'));
            $expected ??= $quote->json(); $this->assertSame($expected, $quote->json());
            $result = $this->postJson($this->url($entry), $payload)->assertSuccessful();
            $id = $entry === 'legacy' ? $result->json('appointment_id') : $result->json('appointment.appointment_id');
            $this->assertEquals(100, Appointment::findOrFail($id)->precio_programado);
        }
        $this->assertSame($before, $history->fresh()->getAttributes());
        $this->assertDatabaseCount('payments', 0);
    }

    /** @dataProvider entries */
    public function test_no_history_implicitly_authorizes_reevaluation_without_indication(string $entry): void
    {
        $this->catalog['doctorService']->update(['precio_reconsulta' => 50, 'dias_reconsulta' => 7]);
        $valid = $this->lifecycleAppointment($this->actor, $this->catalog, ['estado_cita' => 'ATENDIDO',
            'estado_agenda' => 'CONFIRMADA', 'fecha_cita' => now()->subDays(2)->toDateString()], $this->patient);
        $private = $this->lifecycleAppointment($this->actor, $this->catalog, ['estado_cita' => 'ATENDIDO',
            'estado_agenda' => 'PENDIENTE_CONFIRMACION', 'responsible_user_id' => $this->actor->id], $this->patient);
        $before = [$valid->fresh()->getAttributes(), $private->fresh()->getAttributes()];
        $url = $entry === 'legacy' ? '/api/appointment/calculated' : '/scheduling-mvp/agenda/quote';
        foreach ([$this->actor, $this->agendaReader('COMERCIAL')] as $worker) {
            $this->actingAs($worker)->postJson($url, $this->payload($entry))->assertOk()->assertJsonPath('tipo', 'PRIMERA_CONSULTA');
        }
        $this->assertSame($before, [$valid->fresh()->getAttributes(), $private->fresh()->getAttributes()]);
    }

    private function adminPatientPayload(array $changes = []): array
    {
        return array_replace(['tipo_identificacion' => 'DNI', 'numero_identidad' => '70000881',
            'nombre' => 'QA ADMIN PACIENTE', 'apellido_paterno' => 'FICTICIO', 'apellido_materno' => 'LOCAL',
            'genero' => 'HOMBRE', 'telefono_prefijo' => '+51', 'telefono_numero' => '999000881'], $changes);
    }

    public function test_administrator_creates_completes_and_edits_a_patient_from_agenda_without_financial_grants(): void
    {
        $admin = $this->agendaReader('ADMINISTRADOR');
        $admin->givePermissionTo(Permission::findOrCreate(C::CREATE, 'web'));
        $permissions = $admin->getAllPermissions()->pluck('name')->sort()->values()->all();
        $payload = $this->payload('operational', ['patient_id' => null, 'patient' => $this->adminPatientPayload()]);
        $id = $this->actingAs($admin)->postJson($this->url('operational'), $payload)->assertCreated()->json('appointment.appointment_id');
        $appointment = Appointment::findOrFail($id); $patient = $appointment->patient;
        $this->assertSame('01-70000881', $patient->historia_clinica); $this->assertEquals($admin->id, $patient->user_id);
        $this->assertDatabaseHas('appointment_events', ['appointment_id' => $id, 'actor_user_id' => $admin->id, 'event_type' => 'REGISTRO_OPERATIVO']);
        $this->putJson(route('patients.operational.update', $patient->id), $this->adminPatientPayload(
            ['nombre' => 'QA ADMIN EDITADO', 'direccion' => 'DIRECCION FICTICIA']))->assertOk()->assertJsonPath('patient.nombre', 'QA ADMIN EDITADO');
        $this->patchJson(route('patients.operational.agenda-contact', $patient->id),
            ['appointment_id' => $id, 'telefono_secundario' => '+51999000882'])->assertOk();
        $this->assertDatabaseHas('appointment_events', ['appointment_id' => $id, 'actor_user_id' => $admin->id, 'event_type' => 'CONTACTO_PACIENTE_ACTUALIZADO']);
        $this->assertSame('01-70000881', $patient->fresh()->historia_clinica);
        $this->assertSame('PENDIENTE_CONFIRMACION', $appointment->fresh()->estado_agenda);
        $this->assertDatabaseCount('payments', 0); $this->assertDatabaseCount('vouchers', 0);
        $this->assertFalse($admin->can(C::SUBMIT_PAYMENT)); $this->assertFalse($admin->can(C::APPROVE_ZERO_COST));
        $this->assertSame($permissions, $admin->fresh()->getAllPermissions()->pluck('name')->sort()->values()->all());
        $this->assertSame(['ADMINISTRADOR'], $admin->getRoleNames()->all());
    }

    public function test_administrator_patient_capture_preserves_private_appointment_visibility(): void
    {
        $admin = $this->agendaReader('ADMINISTRADOR');
        $hidden = $this->lifecycleAppointment($this->actor, $this->catalog, ['estado_agenda' => 'PENDIENTE_CONFIRMACION'], $this->patient);
        $before = $this->patient->fresh()->getAttributes();
        $path = route('patients.operational.agenda-contact', $this->patient->id);
        $hiddenResponse = $this->actingAs($admin)->patchJson($path, ['appointment_id' => $hidden->id, 'telefono' => '+51999000991'])->assertNotFound();
        $missingResponse = $this->patchJson($path, ['appointment_id' => 999999, 'telefono' => '+51999000991'])->assertNotFound();
        $this->assertSame($missingResponse->json(), $hiddenResponse->json());
        $this->assertSame($before, $this->patient->fresh()->getAttributes());
        $this->getJson(route('scheduling.mvp.agenda.economy', $hidden->id))->assertNotFound();
    }

    public function test_administrator_still_needs_create_and_payment_permissions_when_registering_a_new_patient_and_appointment(): void
    {
        $admin = $this->agendaReader('ADMINISTRADOR');
        $before = \App\Models\Patient::count();
        $payload = $this->payload('operational', ['patient_id' => null, 'patient' => $this->adminPatientPayload()]);
        $this->actingAs($admin)->postJson($this->url('operational'), $payload)->assertForbidden();
        $admin->givePermissionTo(Permission::findOrCreate(C::CREATE, 'web'));
        CashierShift::first()->update(['user_id' => $admin->id]);
        $this->postJson($this->url('operational'), array_replace($payload, ['mode' => 'CONFIRM',
            'payment' => ['amount' => '50.00', 'method' => 'EFECTIVO']]))->assertForbidden();
        $this->assertSame($before, \App\Models\Patient::count()); $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('payments', 0); $this->assertDatabaseCount('vouchers', 0);
    }

    public function test_administrator_patient_writes_keep_identity_and_required_capture_validations(): void
    {
        $admin = $this->agendaReader('ADMINISTRADOR');
        $admin->givePermissionTo(Permission::findOrCreate(C::CREATE, 'web'));
        $this->actingAs($admin); $before = \App\Models\Patient::count();
        foreach (['123', '700000001', '70000ABC'] as $invalid) {
            $this->postJson(route('patients.operational.store'), $this->adminPatientPayload(['numero_identidad' => $invalid]))
                ->assertUnprocessable()->assertJsonValidationErrors('numero_identidad');
        }
        $this->postJson(route('patients.operational.store'), $this->adminPatientPayload(['numero_identidad' => $this->patient->numero_identidad]))
            ->assertUnprocessable()->assertJsonValidationErrors('numero_identidad');
        $this->postJson($this->url('operational'), $this->payload('operational', ['patient_id' => null,
            'patient' => $this->adminPatientPayload(['telefono_numero' => ''])]))->assertUnprocessable();
        $this->assertSame($before, \App\Models\Patient::count()); $this->assertDatabaseCount('appointments', 0);
        $this->postJson(route('patients.operational.store'), $this->adminPatientPayload(
            ['tipo_identificacion' => 'PASAPORTE', 'numero_identidad' => 'QA-PASSPORT-881']))->assertCreated();
    }
}
