<?php

namespace Tests\Feature\Scheduling;

use App\Models\Appointment;
use App\Services\Scheduling\RegularCapacityService;
use App\Support\Scheduling\AgendaQuery;
use App\Support\Scheduling\SchedulingCapability as Capability;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

class AgendaRefinementTest extends TestCase
{
    use BuildsAgendaLifecycleData, RefreshDatabase;
    private $actor;
    private $patient;
    private $catalog;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scheduling.enabled' => true]);
        $this->actor = $this->agendaReader('ADMISION');
        foreach ([Capability::CREATE, Capability::RESCHEDULE, Capability::CREATE_ADDITIONAL] as $permission) {
            $this->actor->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $this->patient = $this->createPatient($this->actor);
        $this->catalog = $this->createAppointmentCatalog();
        $this->catalog['rate']->update(['nombre' => 'TARIFA ESTANDAR', 'tipo_tarifa' => 'MONTO_FIJO']);
        $this->actingAs($this->actor);
    }

    public function test_explicit_off_hours_creation_blocks_exact_interval_and_preserves_regular_capacity(): void
    {
        $id = $this->postJson(route('scheduling.mvp.agenda.appointments.off-hours'), $this->payload('06:00'))
            ->assertCreated()->assertJsonPath('appointment.tipo_agendamiento', 'FUERA_HORARIO')->json('appointment.appointment_id');
        $appointment = Appointment::findOrFail($id);
        $this->assertSame([$id], Appointment::occupyingInterval()->pluck('id')->all());
        $this->assertSame([], Appointment::consumingRegularSlot()->pluck('id')->all());
        $this->assertEquals(100, $appointment->precio_programado);
        $this->assertEquals(0, $appointment->total_pagado);
        $this->assertSame('PENDIENTE', $appointment->estado_pagado);
        $this->assertSame($this->actor->id, (int) $appointment->updated_by_user_id);
        $this->postJson(route('scheduling.mvp.agenda.appointments.off-hours'), $this->payload('06:20'))->assertConflict();
        $this->postJson(route('scheduling.mvp.agenda.appointments.off-hours'), $this->payload('06:30'))->assertCreated();
        foreach (['dia', 'semana', 'mes'] as $view) {
            $feed = $this->feed($view)->assertOk();
            $this->assertStringContainsString($view === 'mes' ? 'FH' : 'FUERA DE HORARIO', $feed->getContent());
            $this->assertSame(2, $feed->json('especiales.fuera_horario'));
        }
    }

    public function test_normal_never_becomes_off_hours_and_invalid_regular_cadence_is_not_an_exception(): void
    {
        $this->postJson(route('scheduling.mvp.agenda.appointments.store'), $this->payload('06:00') + ['tipo_agendamiento' => 'FUERA_HORARIO'])->assertConflict();
        foreach (['08:00', '08:10', '07:50', '23:50'] as $time) {
            $this->postJson(route('scheduling.mvp.agenda.appointments.off-hours'), $this->payload($time))->assertConflict();
        }
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_both_conversions_require_confirmation_preserve_snapshot_and_reject_occupied_destination(): void
    {
        $id = $this->postJson(route('scheduling.mvp.agenda.appointments.store'), $this->payload('08:00'))->assertCreated()->json('appointment.appointment_id');
        $appointment = Appointment::findOrFail($id);
        $appointment->update(['total_pagado' => 50, 'saldo_pendiente' => 50, 'numero_operacion' => 'TEST-OP']);
        $before = $appointment->fresh()->getAttributes();
        $this->move($id, '08:00', '06:00')->assertConflict()->assertJsonPath('confirmation_required', true)->assertJsonPath('target_booking_type', 'FUERA_HORARIO');
        $this->assertSame($before, $appointment->fresh()->getAttributes());
        $this->move($id, '08:00', '06:00', 'FUERA_HORARIO')->assertOk();
        $this->assertSame('FUERA_HORARIO', $appointment->fresh()->tipo_agendamiento);
        $this->postJson(route('scheduling.mvp.agenda.appointments.store'), $this->payload('09:00'))->assertCreated();
        $this->move($id, '06:00', '09:00', 'REGULAR')->assertConflict()->assertJsonMissing(['confirmation_required' => true]);
        $this->assertSame('06:00', substr($appointment->fresh()->hora_cita, 0, 5));
        $this->move($id, '06:00', '08:00')->assertConflict()->assertJsonPath('target_booking_type', 'REGULAR');
        $this->move($id, '06:00', '08:00', 'REGULAR')->assertOk();
        foreach ($before as $field => $value) {
            if (!in_array($field, ['tipo_agendamiento', 'updated_at'], true)) { $this->assertSame($value, $appointment->fresh()->getAttributes()[$field], $field); }
        }
        $this->assertSame('REGULAR', $appointment->fresh()->tipo_agendamiento);
        $this->assertSame(1, $this->capacity()['ocupacion_segura']);
    }

    public function test_capacity_endpoint_is_authorized_bounded_and_independent_of_private_identity(): void
    {
        $this->lifecycleAppointment($this->actor, $this->catalog, [
            'estado_agenda' => 'PENDIENTE_CONFIRMACION', 'tipo_agendamiento' => 'REGULAR',
            'total_pagado' => 100, 'fecha_cita' => $this->catalog['schedule']->fecha_cita,
        ], $this->patient);
        $params = ['doctor_id' => $this->catalog['doctor']->id,
            'from' => $this->catalog['schedule']->fecha_cita, 'to' => $this->catalog['schedule']->fecha_cita];
        $url = route('scheduling.mvp.agenda.regular-capacity', $params);
        $first = $this->getJson($url)->assertOk()->assertJsonPath('dias.0.ocupacion_segura', 0)->json();
        $other = $this->agendaReader('COMERCIAL');
        $this->actingAs($other)->getJson($url)->assertOk()->assertExactJson($first);
        $this->assertSame(['doctor_id', 'fecha', 'capacidad_regular', 'ocupacion_segura', 'porcentaje', 'color'], array_keys($first['dias'][0]));
        $params['to'] = Carbon::parse($params['from'])->addDays(42)->toDateString();
        $this->getJson(route('scheduling.mvp.agenda.regular-capacity', $params))->assertUnprocessable();
        $other->revokePermissionTo(Capability::VIEW);
        $this->getJson($url)->assertForbidden();
    }

    public function test_additional_stays_additional_and_both_rows_and_month_counts_are_visible(): void
    {
        $regular = $this->postJson(route('scheduling.mvp.agenda.appointments.store'), $this->payload('08:00'))->assertCreated()->json('appointment.appointment_id');
        $additional = $this->postJson(route('scheduling.mvp.agenda.appointments.additional'), $this->payload('08:00'))->assertCreated()->json('appointment.appointment_id');
        foreach (['dia', 'semana'] as $view) {
            $events = collect($this->feed($view)->assertOk()->json('eventos'))->pluck('extendedProps');
            $this->assertNotNull($events->firstWhere('appointment_id', $regular));
            $this->assertSame('ADICIONAL', $events->firstWhere('appointment_id', $additional)['etiqueta']);
        }
        $this->feed('mes')->assertOk()->assertJsonPath('especiales.adicionales', 1);
        $this->assertSame([$regular], Appointment::consumingRegularSlot()->pluck('id')->all());
        $this->move($additional, '08:00', '06:00', 'FUERA_HORARIO')->assertConflict();
        $this->move($additional, '08:00', '09:00', 'REGULAR')->assertOk();
        $this->assertSame('ADICIONAL', Appointment::findOrFail($additional)->tipo_agendamiento);
    }

    public function test_service_is_required_and_both_controls_and_error_feedback_are_rendered(): void
    {
        $payload = $this->payload('08:00'); unset($payload['service_id']);
        $this->postJson(route('scheduling.mvp.agenda.appointments.store'), $payload)->assertUnprocessable()
            ->assertJsonPath('errors.service_id.0', 'Selecciona un servicio para agendar la cita.');
        $this->get(route('scheduling.mvp.agenda'))->assertOk()->assertSee('agenda-draft-service')->assertSee('agenda-service-error')
            ->assertSee('agenda-draft-commercial-owner')->assertSee('data-quick-minute="20"', false)
            ->assertDontSee('id="agenda-reschedule-time" class="agenda-field__input" type="time"', false);
        $this->assertDatabaseCount('appointments', 0);
    }

    /** @dataProvider thresholds */
    public function test_capacity_exact_thresholds(int $occupied, string $color): void
    {
        $schedule = $this->catalog['schedule'];
        $schedule->update(['hora_inicio' => '08:00', 'hora_fin' => '09:40', 'duracion_cita' => 1]);
        for ($i = 0; $i < $occupied; $i++) {
            $this->lifecycleAppointment($this->actor, $this->catalog, [
                'fecha_cita' => $schedule->fecha_cita, 'hora_cita' => Carbon::parse('08:00')->addMinutes($i)->format('H:i'),
                'duracion_cita' => 1, 'estado_agenda' => 'CONFIRMADA', 'tipo_agendamiento' => 'REGULAR', 'total_pagado' => 50,
            ], $this->patient);
        }
        $metric = $this->capacity();
        $this->assertSame(100, $metric['capacidad_regular']);
        $this->assertSame($occupied, $metric['ocupacion_segura']);
        $this->assertEquals($occupied, $metric['porcentaje']);
        $this->assertSame($color, $metric['color']);
    }

    public static function thresholds(): array { return [[0, 'verde'], [49, 'verde'], [50, 'amarillo'], [79, 'amarillo'], [80, 'rojo']]; }

    public function test_capacity_ignores_insufficient_payment_specials_private_reservations_and_releasing_states(): void
    {
        foreach ([
            ['total_pagado' => 49.99], ['precio_programado' => 0, 'total_pagado' => 100],
            ['tipo_agendamiento' => 'ADICIONAL'], ['tipo_agendamiento' => 'FUERA_HORARIO'],
            ['estado_cita' => 'CANCELADO'], ['estado_agenda' => 'PENDIENTE_CONFIRMACION'],
        ] as $attributes) {
            $this->lifecycleAppointment($this->actor, $this->catalog, array_merge([
                'fecha_cita' => $this->catalog['schedule']->fecha_cita, 'hora_cita' => '08:00', 'duracion_cita' => 30,
                'estado_agenda' => 'CONFIRMADA', 'tipo_agendamiento' => 'REGULAR', 'total_pagado' => 100,
            ], $attributes), $this->patient);
        }
        $this->assertSame(0, $this->capacity()['ocupacion_segura']);
        $this->catalog['schedule']->update(['estado' => 'INACTIVO']);
        $this->assertSame('plomo', $this->capacity()['color']);
        $this->assertSame(0, $this->capacity()['capacidad_regular']);
    }

    private function capacity(): array
    {
        $date = Carbon::parse($this->catalog['schedule']->fecha_cita);
        return app(RegularCapacityService::class)->forRange(new AgendaQuery([$this->catalog['doctor']->id], $date, $date))[0];
    }
    private function payload(string $time): array
    {
        return ['patient_id' => $this->patient->id, 'doctor_id' => $this->catalog['doctor']->id,
            'service_id' => $this->catalog['service']->id, 'fecha_cita' => $this->catalog['schedule']->fecha_cita,
            'hora_cita' => $time, 'duracion_cita' => 30, 'site_id' => null];
    }
    private function feed(string $view)
    {
        return $this->getJson(route('scheduling.mvp.agenda.feed', ['vista' => $view,
            'fecha' => $this->catalog['schedule']->fecha_cita, 'doctor_id' => [$this->catalog['doctor']->id]]));
    }
    private function move(int $id, string $from, string $to, ?string $type = null)
    {
        return $this->patchJson(route('scheduling.mvp.agenda.appointments.reschedule', ['appointmentId' => $id]), [
            'fecha_cita' => $this->catalog['schedule']->fecha_cita, 'expected_fecha_cita' => $this->catalog['schedule']->fecha_cita,
            'expected_hora_cita' => $from, 'hora_cita' => $to, 'confirmed_booking_type' => $type,
        ]);
    }
}
