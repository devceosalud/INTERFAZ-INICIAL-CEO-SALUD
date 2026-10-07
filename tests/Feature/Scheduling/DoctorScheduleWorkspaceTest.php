<?php

namespace Tests\Feature\Scheduling;

use App\Models\Appointment;
use App\Models\DoctorSchedule;
use App\Models\User;
use App\Services\Scheduling\DoctorAvailabilityService;
use App\Support\Scheduling\AvailabilityQuery;
use App\Support\Scheduling\SchedulingCapability;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

class DoctorScheduleWorkspaceTest extends TestCase
{
    use BuildsBaselineData;
    use RefreshDatabase;

    private const PAGE = '/admissionist/doctor-schedule';
    private const FEED = '/admissionist/doctor-schedule/calendar';
    private const STORE = '/admissionist/doctor-schedule/store';
    private const UPDATE = '/admissionist/doctor-schedule/update';

    private array $catalog;
    private User $admission;
    private Carbon $monday;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('scheduling.enabled', true);
        $this->catalog = $this->createAppointmentCatalog();
        DoctorSchedule::query()->delete();
        $this->admission = $this->createUserWithRole('ADMISION');
        $this->monday = Carbon::parse('next monday')->startOfDay();
    }

    public function test_the_workspace_uses_week_as_its_primary_view_and_keeps_day_and_month(): void
    {
        $this->actingAs($this->admission)
            ->get(self::PAGE)
            ->assertOk()
            ->assertSee('Horarios médicos')
            ->assertSee('data-calendar-view="timeGridDay"', false)
            ->assertSee('data-calendar-view="timeGridWeek"', false)
            ->assertSee('data-calendar-view="dayGridMonth"', false)
            ->assertSee('Duración programada por cita')
            ->assertSee('El horario activo define los turnos disponibles en Agenda.');
    }

    public function test_site_specialty_and_doctor_filters_are_applied_to_the_feed(): void
    {
        $site = $this->createSite(['codigo' => 'NORTE', 'nombre' => 'Sede Norte']);
        $this->catalog['doctor']->update(['nombre' => 'Dra. Ana Quispe']);
        $this->block($this->monday, ['site_id' => $site->id]);
        $other = $this->createAppointmentCatalog();
        DoctorSchedule::whereKey($other['schedule']->id)->update([
            'fecha_cita' => $this->monday->toDateString(),
            'dia_semana' => 1,
        ]);

        $uri = self::FEED.'?'.http_build_query([
            'start' => $this->monday->toDateString(),
            'end' => $this->monday->toDateString(),
            'site_id' => $site->id,
            'specialty_id' => $this->catalog['specialty']->id,
            'doctor_id' => $this->catalog['doctor']->id,
        ]);

        $this->actingAs($this->admission)
            ->getJson($uri)
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.extendedProps.site_id', $site->id)
            ->assertJsonPath('0.extendedProps.doctor_id', $this->catalog['doctor']->id)
            ->assertJsonPath('0.extendedProps.doctor_initial', 'AQ');
    }

    public function test_multiple_blocks_on_the_same_day_remain_distinct(): void
    {
        $this->block($this->monday, ['hora_inicio' => '08:00:00', 'hora_fin' => '12:00:00']);
        $this->block($this->monday, ['hora_inicio' => '15:00:00', 'hora_fin' => '18:00:00']);

        $this->actingAs($this->admission)
            ->getJson($this->feedFor($this->monday, $this->monday))
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.extendedProps.start_time', '08:00')
            ->assertJsonPath('1.extendedProps.start_time', '15:00');
    }

    public function test_a_single_day_accepts_an_empty_weekday_list(): void
    {
        $this->actingAs($this->admission)
            ->postJson(self::STORE, $this->storePayload(['weekdays' => []]))
            ->assertOk()
            ->assertJsonPath('created_count', 1);

        $block = DoctorSchedule::firstOrFail();
        $this->assertSame($this->monday->toDateString(), substr((string) $block->fecha_cita, 0, 10));
        $this->assertSame((int) $this->monday->dayOfWeekIso, (int) $block->dia_semana);
    }

    public function test_a_single_day_accepts_a_payload_without_weekdays(): void
    {
        $payload = $this->storePayload();
        unset($payload['weekdays']);

        $this->actingAs($this->admission)
            ->postJson(self::STORE, $payload)
            ->assertOk()
            ->assertJsonPath('created_count', 1);

        $this->assertSame(1, DoctorSchedule::count());
    }

    public function test_selected_days_without_weekdays_are_rejected_in_spanish(): void
    {
        $missing = $this->storePayload(['scope' => 'selected']);
        unset($missing['weekdays']);

        $this->actingAs($this->admission)
            ->postJson(self::STORE, $missing)
            ->assertStatus(422)
            ->assertJsonPath('error.weekdays.0', 'Selecciona al menos un día de la semana.');

        $this->actingAs($this->admission)
            ->postJson(self::STORE, $this->storePayload([
                'scope' => 'selected',
                'weekdays' => [],
            ]))
            ->assertStatus(422)
            ->assertJsonPath('error.weekdays.0', 'Selecciona al menos un día de la semana.');

        $this->assertSame(0, DoctorSchedule::count());
    }

    public function test_one_selected_day_creates_a_single_dated_block(): void
    {
        $this->actingAs($this->admission)
            ->postJson(self::STORE, $this->storePayload([
                'scope' => 'selected',
                'weekdays' => [1],
            ]))
            ->assertOk()
            ->assertJsonPath('created_count', 1);

        $block = DoctorSchedule::firstOrFail();
        $this->assertSame($this->monday->toDateString(), substr((string) $block->fecha_cita, 0, 10));
        $this->assertSame(1, (int) $block->dia_semana);
    }

    public function test_a_weekly_pattern_without_weekdays_is_rejected_in_spanish(): void
    {
        $payload = $this->storePayload([
            'scope' => 'weekly',
            'fecha_cita' => null,
        ]);
        unset($payload['weekdays']);

        $this->actingAs($this->admission)
            ->postJson(self::STORE, $payload)
            ->assertStatus(422)
            ->assertJsonPath('error.weekdays.0', 'Selecciona al menos un día de la semana.');

        $this->actingAs($this->admission)
            ->postJson(self::STORE, $this->storePayload([
                'scope' => 'weekly',
                'fecha_cita' => null,
                'weekdays' => [],
            ]))
            ->assertStatus(422)
            ->assertJsonPath('error.weekdays.0', 'Selecciona al menos un día de la semana.');

        $this->assertSame(0, DoctorSchedule::count());
    }

    public function test_selected_days_create_independent_dated_blocks(): void
    {
        $this->actingAs($this->admission)
            ->postJson(self::STORE, $this->storePayload([
                'scope' => 'selected',
                'weekdays' => [1, 3, 5],
            ]))
            ->assertOk()
            ->assertJsonPath('created_count', 3);

        $blocks = DoctorSchedule::orderBy('dia_semana')->get();
        $this->assertSame([1, 3, 5], $blocks->pluck('dia_semana')->map(fn ($day) => (int) $day)->all());
        $this->assertSame([
            $this->monday->toDateString(),
            $this->monday->copy()->addDays(2)->toDateString(),
            $this->monday->copy()->addDays(4)->toDateString(),
        ], $blocks->pluck('fecha_cita')->map(fn ($date) => substr((string) $date, 0, 10))->all());
    }

    public function test_weekly_pattern_uses_the_real_null_date_recurrence_contract(): void
    {
        $this->actingAs($this->admission)
            ->postJson(self::STORE, $this->storePayload([
                'scope' => 'weekly',
                'fecha_cita' => null,
                'weekdays' => [2, 4],
            ]))
            ->assertOk()
            ->assertJsonPath('created_count', 2);

        $this->assertSame(2, DoctorSchedule::whereNull('fecha_cita')->count());

        $this->actingAs($this->admission)
            ->getJson($this->feedFor($this->monday, $this->monday->copy()->addDays(6)))
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.extendedProps.recurrence', 'WEEKLY');
    }

    public function test_a_recurring_block_is_considered_by_the_overlap_warning(): void
    {
        DoctorSchedule::create([
            'doctor_id' => $this->catalog['doctor']->id,
            'dia_semana' => 1,
            'fecha_cita' => null,
            'hora_inicio' => '09:00:00',
            'hora_fin' => '13:00:00',
            'duracion_cita' => 20,
            'estado' => 'ACTIVO',
        ]);

        $this->actingAs($this->admission)
            ->getJson('/admissionist/doctor-schedule/overlap?'.http_build_query([
                'doctor_id' => $this->catalog['doctor']->id,
                'fecha_cita' => $this->monday->toDateString(),
                'hora_inicio' => '12:00',
                'hora_fin' => '14:00',
            ]))
            ->assertOk()
            ->assertJsonPath('solapa', true);
    }

    public function test_schedule_duration_is_the_real_cadence_consumed_by_availability_and_agenda(): void
    {
        $this->actingAs($this->admission)
            ->postJson(self::STORE, $this->storePayload([
                'hora_inicio' => '09:00',
                'hora_fin' => '11:00',
                'duracion_cita' => 20,
            ]))->assertOk();

        $this->assertSame(
            ['09:00', '09:20', '09:40', '10:00', '10:20', '10:40'],
            $this->availableStarts()
        );

        $block = DoctorSchedule::firstOrFail();
        $this->actingAs($this->admission)
            ->putJson(self::UPDATE, [
                'doctor_schedule_id_edit' => $block->id,
                'doctor_id_edit' => $this->catalog['doctor']->id,
                'fecha_cita_edit' => $this->monday->toDateString(),
                'hora_inicio_edit' => '10:00',
                'hora_fin_edit' => '12:00',
                'duracion_edit_cita' => 30,
            ])->assertOk();

        $this->assertSame(['10:00', '10:30', '11:00', '11:30'], $this->availableStarts());

        $reader = $this->agendaReader();
        $this->actingAs($reader)
            ->getJson('/scheduling-mvp/agenda/feed?'.http_build_query([
                'vista' => 'dia',
                'fecha' => $this->monday->toDateString(),
                'doctor_id' => [$this->catalog['doctor']->id],
            ]))
            ->assertOk()
            ->assertJsonPath('profesionales.0.dias.0.slots.0.inicio', '10:00')
            ->assertJsonPath('profesionales.0.dias.0.slots.0.minutos', 30);
    }

    public function test_changing_a_schedule_only_warns_and_never_modifies_existing_appointments(): void
    {
        $block = $this->block($this->monday, ['hora_inicio' => '09:00:00', 'hora_fin' => '13:00:00']);
        $appointment = $this->appointment('09:20:00');

        $impactUri = '/admissionist/doctor-schedule/'.$block->id.'/impact?'.http_build_query([
            'action' => 'update',
            'new_date' => $this->monday->toDateString(),
            'new_start' => '10:00',
            'new_end' => '13:00',
        ]);

        $this->actingAs($this->admission)
            ->getJson($impactUri)
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonMissingPath('appointments.0.patient');

        $this->actingAs($this->admission)
            ->putJson(self::UPDATE, [
                'doctor_schedule_id_edit' => $block->id,
                'doctor_id_edit' => $this->catalog['doctor']->id,
                'fecha_cita_edit' => $this->monday->toDateString(),
                'hora_inicio_edit' => '10:00',
                'hora_fin_edit' => '13:00',
                'duracion_edit_cita' => 20,
            ])->assertOk();

        $this->assertSame('09:20:00', $appointment->fresh()->hora_cita);
        $this->assertSame('PROGRAMADO', $appointment->fresh()->estado_cita);
    }

    public function test_a_pending_reservation_survives_schedule_changes_and_inactivation_without_automatic_cancellation(): void
    {
        $block = $this->block($this->monday);
        $appointment = $this->appointment('09:20:00');
        $appointment->update(['estado_agenda' => 'PENDIENTE_CONFIRMACION', 'tipo_agendamiento' => 'REGULAR']);
        $before = $appointment->fresh()->getAttributes();
        $this->actingAs($this->admission)->putJson(self::UPDATE, [
            'doctor_schedule_id_edit' => $block->id, 'doctor_id_edit' => $this->catalog['doctor']->id,
            'fecha_cita_edit' => $this->monday->toDateString(), 'hora_inicio_edit' => '10:00',
            'hora_fin_edit' => '13:00', 'duracion_edit_cita' => 20,
        ])->assertOk()->assertJsonPath('code', 1);
        $this->assertSame($before, $appointment->fresh()->getAttributes());
        $this->postJson('/admissionist/doctor-schedule/delete', ['id' => $block->id])->assertOk()->assertJsonPath('code', 1);
        $this->assertSame('INACTIVO', $block->fresh()->estado);
        $this->assertSame($before, $appointment->fresh()->getAttributes());
    }

    public function test_two_concrete_dates_create_two_schedules(): void
    {
        $this->actingAs($this->admission)
            ->postJson(self::STORE, $this->datesPayload(['2026-10-05', '2026-10-06']))
            ->assertOk()
            ->assertJsonPath('created', 2)
            ->assertJsonPath('dates', ['2026-10-05', '2026-10-06']);

        $this->assertSame(2, DoctorSchedule::query()->count());
    }

    public function test_four_irregular_dates_create_only_those_rows_and_leave_appointments_untouched(): void
    {
        $appointment = $this->appointment('15:00');
        $before = [
            'fecha_cita' => substr((string) $appointment->fecha_cita, 0, 10),
            'hora_cita' => substr((string) $appointment->hora_cita, 0, 5),
            'estado_cita' => $appointment->estado_cita,
            'doctor_id' => (string) $appointment->doctor_id,
        ];

        $this->actingAs($this->admission)
            ->postJson(self::STORE, $this->datesPayload([
                '2026-10-05',
                '2026-10-06',
                '2026-10-08',
                '2026-10-12',
            ]))
            ->assertOk()
            ->assertJsonPath('created', 4)
            ->assertJsonPath('message', 'Se programaron 4 horarios correctamente.')
            ->assertJsonPath('dates', ['2026-10-05', '2026-10-06', '2026-10-08', '2026-10-12']);

        $rows = DoctorSchedule::query()->orderBy('fecha_cita')->get();
        $this->assertSame(
            ['2026-10-05', '2026-10-06', '2026-10-08', '2026-10-12'],
            $rows->map(fn (DoctorSchedule $row) => substr((string) $row->fecha_cita, 0, 10))->all()
        );
        $this->assertSame([1, 2, 4, 1], $rows->map(fn (DoctorSchedule $row) => (int) $row->dia_semana)->all());
        $this->assertSame(0, DoctorSchedule::query()->whereIn('fecha_cita', ['2026-10-07', '2026-10-09', '2026-10-10', '2026-10-11'])->count());

        $appointment->refresh();
        $this->assertSame($before, [
            'fecha_cita' => substr((string) $appointment->fecha_cita, 0, 10),
            'hora_cita' => substr((string) $appointment->hora_cita, 0, 5),
            'estado_cita' => $appointment->estado_cita,
            'doctor_id' => (string) $appointment->doctor_id,
        ]);

        $feed = $this->actingAs($this->admission)
            ->getJson(self::FEED.'?'.http_build_query([
                'start' => '2026-10-01',
                'end' => '2026-10-31',
                'doctor_id' => $this->catalog['doctor']->id,
            ]))
            ->assertOk()
            ->assertJsonCount(4)
            ->json();

        $this->assertSame(
            ['2026-10-05', '2026-10-06', '2026-10-08', '2026-10-12'],
            collect($feed)->pluck('extendedProps.occurrence_date')->sort()->values()->all()
        );
    }

    public function test_a_duplicated_concrete_date_is_rejected_and_creates_nothing(): void
    {
        $this->actingAs($this->admission)
            ->postJson(self::STORE, $this->datesPayload(['2026-10-05', '2026-10-05']))
            ->assertStatus(422);

        $this->assertSame(0, DoctorSchedule::query()->count());
    }

    public function test_concrete_dates_are_required_in_spanish(): void
    {
        $missing = $this->datesPayload(['2026-10-05']);
        unset($missing['dates']);

        $this->actingAs($this->admission)
            ->postJson(self::STORE, $missing)
            ->assertStatus(422)
            ->assertJsonPath('error.dates.0', 'Selecciona al menos una fecha.');

        $this->actingAs($this->admission)
            ->postJson(self::STORE, $this->datesPayload([]))
            ->assertStatus(422)
            ->assertJsonPath('error.dates.0', 'Selecciona al menos una fecha.');

        $this->assertSame(0, DoctorSchedule::query()->count());
    }

    public function test_an_invalid_concrete_date_is_rejected(): void
    {
        $response = $this->actingAs($this->admission)
            ->postJson(self::STORE, $this->datesPayload(['2026-13-01']));

        $response->assertStatus(422);
        $this->assertSame('La fecha no es válida.', $response->json('error')['dates.0'][0]);

        $this->assertSame(0, DoctorSchedule::query()->count());
    }

    public function test_one_conflicting_concrete_date_creates_no_rows(): void
    {
        $this->block(Carbon::parse('2026-10-06'));
        $before = DoctorSchedule::query()->count();

        $this->actingAs($this->admission)
            ->postJson(self::STORE, $this->datesPayload([
                '2026-10-05',
                '2026-10-06',
                '2026-10-08',
                '2026-10-12',
            ]))
            ->assertStatus(422)
            ->assertJsonPath('conflict_dates', ['2026-10-06'])
            ->assertJsonPath('message', "No se pudo aplicar el horario porque existen cruces.\nFechas con conflicto:\n- 06/10");

        $this->assertSame($before, DoctorSchedule::query()->count());
    }

    public function test_a_conflicting_recurring_block_creates_no_concrete_rows(): void
    {
        DoctorSchedule::create([
            'doctor_id' => $this->catalog['doctor']->id,
            'dia_semana' => 1,
            'fecha_cita' => null,
            'hora_inicio' => '09:00:00',
            'hora_fin' => '13:00:00',
            'duracion_cita' => 20,
            'estado' => 'ACTIVO',
        ]);

        $this->actingAs($this->admission)
            ->postJson(self::STORE, $this->datesPayload(['2026-10-05', '2026-10-06', '2026-10-12']))
            ->assertStatus(422)
            ->assertJsonPath('conflict_dates', ['2026-10-05', '2026-10-12']);

        $this->assertSame(1, DoctorSchedule::query()->count());
        $this->assertSame(1, DoctorSchedule::query()->whereNull('fecha_cita')->count());
    }

    public function test_a_failure_inside_the_concrete_date_transaction_creates_nothing(): void
    {
        $attempts = 0;
        DoctorSchedule::creating(function () use (&$attempts) {
            $attempts++;
            if ($attempts === 3) {
                throw new \RuntimeException('forced failure');
            }
        });

        try {
            $this->actingAs($this->admission)
                ->postJson(self::STORE, $this->datesPayload([
                    '2026-10-05',
                    '2026-10-06',
                    '2026-10-08',
                    '2026-10-12',
                ]))
                ->assertStatus(500);
        } finally {
            DoctorSchedule::flushEventListeners();
        }

        $this->assertSame(0, DoctorSchedule::query()->count());
    }

    public function test_concrete_dates_accept_a_null_site(): void
    {
        $this->actingAs($this->admission)
            ->postJson(self::STORE, $this->datesPayload(['2026-10-05', '2026-10-08'], ['site_id' => null]))
            ->assertOk()
            ->assertJsonPath('created', 2);

        $this->assertSame(2, DoctorSchedule::query()->whereNull('site_id')->count());
    }

    public function test_commercial_can_view_but_cannot_modify_the_workspace(): void
    {
        $commercial = $this->createUserWithRole('COMERCIAL');

        $this->actingAs($commercial)
            ->get(self::PAGE)
            ->assertOk()
            ->assertSee('Solo consulta. Admisión administra los horarios.')
            ->assertSee('data-can-manage="false"', false);

        $this->actingAs($commercial)
            ->postJson(self::STORE, $this->storePayload())
            ->assertForbidden();
    }

    private function datesPayload(array $dates, array $overrides = []): array
    {
        $payload = $this->storePayload(array_merge([
            'scope' => 'dates',
            'dates' => $dates,
        ], $overrides));
        unset($payload['fecha_cita'], $payload['weekdays']);

        return $payload;
    }

    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'doctor_id' => $this->catalog['doctor']->id,
            'scope' => 'single',
            'fecha_cita' => $this->monday->toDateString(),
            'hora_inicio' => '09:00',
            'hora_fin' => '13:00',
            'duracion_cita' => 20,
        ], $overrides);
    }

    private function block(Carbon $date, array $overrides = []): DoctorSchedule
    {
        return DoctorSchedule::create(array_merge([
            'doctor_id' => $this->catalog['doctor']->id,
            'dia_semana' => $date->dayOfWeekIso,
            'fecha_cita' => $date->toDateString(),
            'hora_inicio' => '09:00:00',
            'hora_fin' => '13:00:00',
            'duracion_cita' => 20,
            'estado' => 'ACTIVO',
        ], $overrides));
    }

    private function feedFor(Carbon $start, Carbon $end): string
    {
        return self::FEED.'?'.http_build_query([
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'doctor_id' => $this->catalog['doctor']->id,
        ]);
    }

    private function availableStarts(): array
    {
        return app(DoctorAvailabilityService::class)
            ->forDay(new AvailabilityQuery($this->catalog['doctor']->id, $this->monday))
            ->availableSlots()
            ->map(fn ($slot) => $slot->range()->start()->format('H:i'))
            ->all();
    }

    private function appointment(string $time): Appointment
    {
        $patient = $this->createPatient($this->admission, ['numero_identidad' => '79990001']);

        return Appointment::create([
            'numero_cita' => 'SCHEDULE-IMPACT-1',
            'user_id' => $this->admission->id,
            'patient_id' => $patient->id,
            'doctor_id' => $this->catalog['doctor']->id,
            'service_id' => $this->catalog['service']->id,
            'additional_rate_id' => $this->catalog['rate']->id,
            'fecha_cita' => $this->monday->toDateString(),
            'hora_cita' => $time,
            'duracion_cita' => 20,
            'estado_cita' => 'PROGRAMADO',
            'estado_pagado' => 'PENDIENTE',
        ]);
    }

    private function agendaReader(): User
    {
        $user = $this->createUser();
        $user->givePermissionTo(Permission::findOrCreate(SchedulingCapability::MVP_ACCESS, 'web'));
        $user->givePermissionTo(Permission::findOrCreate(SchedulingCapability::VIEW, 'web'));

        return $user;
    }
}
