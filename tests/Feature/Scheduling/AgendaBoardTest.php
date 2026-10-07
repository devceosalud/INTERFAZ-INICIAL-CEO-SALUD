<?php

namespace Tests\Feature\Scheduling;

use App\Http\Controllers\Scheduling\AgendaFeedController;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Specialty;
use App\Models\User;
use App\Support\Scheduling\SchedulingCapability;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

/**
 * The agenda board: the page, and the feed that answers Day / Week / Month.
 */
class AgendaBoardTest extends TestCase
{
    use BuildsBaselineData;
    use RefreshDatabase;

    private const PAGE_URI = '/scheduling-mvp/agenda';

    private const FEED_URI = '/scheduling-mvp/agenda/feed';

    /** @var array */
    private $catalog;

    /** @var string A Monday, so week boundaries are predictable. */
    private $monday;

    /** @var User|null */
    private $reader;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('scheduling.enabled', true);
        $this->catalog = $this->createAppointmentCatalog();
        DoctorSchedule::query()->delete();
        $this->monday = Carbon::today()->addWeek()->startOfWeek(Carbon::MONDAY)->toDateString();
    }

    /*
    |--------------------------------------------------------------------------
    | Access
    |--------------------------------------------------------------------------
    */

    public function test_the_board_is_hidden_while_the_feature_flag_is_off(): void
    {
        config()->set('scheduling.enabled', false);

        $this->actingAs($this->reader())->get(self::PAGE_URI)->assertNotFound();
        $this->actingAs($this->reader())->getJson($this->feedUri())->assertNotFound();
    }

    public function test_an_anonymous_visitor_cannot_reach_the_board(): void
    {
        $this->get(self::PAGE_URI)->assertRedirect();
        $this->getJson($this->feedUri())->assertUnauthorized();
    }

    public function test_reading_the_board_requires_an_explicit_capability(): void
    {
        $user = $this->createUser();
        $user->givePermissionTo(Permission::findOrCreate(SchedulingCapability::MVP_ACCESS, 'web'));

        $this->actingAs($user)->get(self::PAGE_URI)->assertForbidden();
        $this->actingAs($user)->getJson($this->feedUri())->assertForbidden();
    }

    public function test_the_page_renders_the_two_zone_operational_workspace(): void
    {
        $site = $this->createSite(['nombre' => 'Sede Central']);

        $this->actingAs($this->reader())
            ->get(self::PAGE_URI)
            ->assertOk()
            ->assertSee('Agenda operativa')
            ->assertSee('Agenda horaria')
            ->assertSee('Registro rápido')
            ->assertSee('Calendario')
            ->assertSee('Comparar')
            ->assertSee('Sede Central')
            ->assertSee($this->catalog['doctor']->nombre)
            ->assertSee('Sin horario de atención')
            ->assertSee('Disponible');
    }

    public function test_the_page_exposes_interaction_hooks_without_a_false_save_action(): void
    {
        $this->actingAs($this->reader())
            ->get(self::PAGE_URI)
            ->assertOk()
            ->assertSee('id="agenda-doctor-list"', false)
            ->assertSee('id="agenda-compare-toggle"', false)
            ->assertSee('id="agenda-mini-grid"', false)
            ->assertSee('id="agenda-quick-registration"', false)
            ->assertSee('id="agenda-quick-patient-id-display"', false)
            ->assertSee('id="agenda-quick-doctor"', false)
            ->assertSee('id="agenda-quick-date"', false)
            ->assertSee('id="agenda-quick-time"', false)
            ->assertSee('id="agenda-quick-payment"', false)
            ->assertSee('id="agenda-quick-clinical-record"', false)
            ->assertSee('id="agenda-calendar"', false)
            ->assertSee('id="agenda-day-grid"', false)
            ->assertSee('id="agenda-day-grid-body"', false)
            ->assertSee('id="agenda-row-head"', false)
            ->assertSee('js/scheduling/agenda-selection.js', false)
            ->assertSee('js/scheduling/agenda-day-grid.js', false)
            ->assertSee('js/scheduling/agenda-week-event.js', false)
            ->assertSee('js/scheduling/agenda-week-background.js', false)
            ->assertSee('js/scheduling/agenda-patient-lookup.js', false)
            ->assertSee('js/scheduling/agenda-appointment-create.js', false)
            ->assertSeeInOrder(['Hora', 'Citado', 'Pago', 'H.C.', 'Apellidos y nombres'])
            ->assertSee('Seleccione médico, fecha, intervalo disponible, paciente y servicio.')
            ->assertSee('id="agenda-appointment-submit"', false)
            ->assertSee('id="agenda-appointment-submit" disabled', false);
    }

    public function test_the_quick_registration_exposes_a_local_document_lookup(): void
    {
        $this->actingAs($this->reader())
            ->get(self::PAGE_URI)
            ->assertOk()
            ->assertSee('id="agenda-patient-lookup"', false)
            ->assertSee('id="agenda-document-type"', false)
            ->assertSee('id="agenda-document-number"', false)
            ->assertSee('id="agenda-document-search"', false)
            ->assertSee('Buscar')
            ->assertSee('id="agenda-patient-register"', false)
            ->assertSee('Registrar paciente')
            ->assertSee('disabled', false)
            ->assertSee('Sin paciente seleccionado')
            ->assertDontSee('placeholder="Disponible en el flujo de registro"', false);
    }

    public function test_the_patient_modal_is_embedded_in_agenda_and_read_only_users_cannot_save(): void
    {
        $this->actingAs($this->reader())
            ->get(self::PAGE_URI)
            ->assertOk()
            ->assertSee('id="agenda-patient-modal"', false)
            ->assertSee('Celular')
            ->assertSee('Más datos del paciente · opcional')
            ->assertSee('Guardar reserva')
            ->assertSee('Guardar y agendar')
            ->assertSee('data-store-endpoint="'.route('patients.operational.store').'"', false)
            ->assertSee('data-can-write="0"', false)
            ->assertSee('id="agenda-draft-save" disabled', false)
            ->assertSee('Comercial dueño')
            ->assertSee('separado del usuario creador');
    }

    public function test_admission_can_use_the_embedded_patient_write_actions(): void
    {
        $admission = $this->createUserWithRole('ADMISION');
        $admission->givePermissionTo(Permission::findOrCreate(SchedulingCapability::MVP_ACCESS, 'web'));
        $admission->givePermissionTo(Permission::findOrCreate(SchedulingCapability::VIEW, 'web'));

        $this->actingAs($admission)
            ->get(self::PAGE_URI)
            ->assertOk()
            ->assertSee('data-can-write="1"', false)
            ->assertSee('Guardado habilitado para Admisión, Recepción y Comercial.')
            ->assertDontSee('id="agenda-draft-save" disabled', false)
            ->assertSee('Quién agenda')
            ->assertSee($admission->name);
    }

    public function test_appointment_create_capability_exposes_the_real_endpoint_and_service_selection(): void
    {
        $creator = $this->agendaOperator('ADMISION');
        $creator->givePermissionTo(Permission::findOrCreate(SchedulingCapability::CREATE, 'web'));

        $this->actingAs($creator)
            ->get(self::PAGE_URI)
            ->assertOk()
            ->assertSee('data-appointment-store="'.route('scheduling.mvp.agenda.appointments.store').'"', false)
            ->assertSee('data-can-create-appointments="1"', false)
            ->assertSee('id="agenda-service-select"', false)
            ->assertSee('id="agenda-responsible-select" disabled', false)
            ->assertDontSee('id="agenda-appointment-submit" disabled', false);
    }

    public function test_reception_and_commercial_can_use_the_embedded_patient_write_actions(): void
    {
        foreach (['RECEPCION', 'COMERCIAL'] as $role) {
            $this->actingAs($this->agendaOperator($role))
                ->get(self::PAGE_URI)
                ->assertOk()
                ->assertSee('data-can-write="1"', false)
                ->assertSee('Guardado habilitado para Admisión, Recepción y Comercial.')
                ->assertDontSee('id="agenda-draft-save" disabled', false);
        }
    }

    public function test_an_administrator_with_agenda_access_cannot_save_patients(): void
    {
        $this->actingAs($this->agendaOperator('ADMINISTRADOR'))
            ->get(self::PAGE_URI)
            ->assertOk()
            ->assertSee('data-can-write="0"', false)
            ->assertSee('id="agenda-draft-save" disabled', false)
            ->assertSee('Solo lectura: guardar requiere Admisión, Recepción o Comercial.');
    }

    public function test_the_operational_board_is_day_first_and_uses_versioned_calendar_assets(): void
    {
        $this->actingAs($this->reader())
            ->get(self::PAGE_URI)
            ->assertOk()
            ->assertSee('data-view="dia"', false)
            ->assertSee('data-grid-minutes="20"', false)
            ->assertSee('assets/vendor/fullcalendar/css/main.min.css', false)
            ->assertSee('assets/vendor/fullcalendar/js/main.min.js', false)
            ->assertDontSee('cdn.jsdelivr.net/npm/fullcalendar', false);
    }

    /*
    |--------------------------------------------------------------------------
    | Day / Week / Month
    |--------------------------------------------------------------------------
    */

    public function test_the_day_view_returns_one_event_per_interval(): void
    {
        $this->block($this->monday, '08:00:00', '09:00:00', 30);

        $response = $this->feed('dia', $this->monday)->assertOk();

        $response->assertJsonPath('rango.inicio', $this->monday)
            ->assertJsonPath('rango.fin', $this->monday)
            ->assertJsonPath('rango.dias', 1)
            ->assertJsonPath('rango.detallada', true)
            ->assertJsonCount(2, 'eventos')
            ->assertJsonPath('eventos.0.start', $this->monday.'T08:00:00')
            ->assertJsonPath('eventos.0.end', $this->monday.'T08:30:00')
            ->assertJsonPath('resumen.libres', 2);
    }

    public function test_the_week_view_covers_monday_to_sunday(): void
    {
        $sunday = Carbon::parse($this->monday)->addDays(6)->toDateString();
        $this->block($this->monday, '08:00:00', '09:00:00', 30);
        $this->block($sunday, '08:00:00', '09:00:00', 30);

        $this->feed('semana', Carbon::parse($this->monday)->addDays(3)->toDateString())
            ->assertOk()
            ->assertJsonPath('rango.inicio', $this->monday)
            ->assertJsonPath('rango.fin', $sunday)
            ->assertJsonPath('rango.dias', 7)
            ->assertJsonPath('resumen.libres', 4)
            ->assertJsonCount(4, 'eventos');
    }

    public function test_a_sunday_anchor_still_opens_monday_through_sunday(): void
    {
        $sunday = Carbon::parse($this->monday)->addDays(6)->toDateString();

        $this->feed('semana', $sunday)
            ->assertOk()
            ->assertJsonPath('rango.inicio', $this->monday)
            ->assertJsonPath('rango.fin', $sunday)
            ->assertJsonPath('rango.dias', 7)
            ->assertJsonPath('rango.etiqueta', Carbon::parse($this->monday)->format('d/m').' — '.Carbon::parse($sunday)->format('d/m/Y'));
    }

    public function test_the_week_view_exposes_only_the_minimum_identity_needed_by_its_renderer(): void
    {
        $this->block($this->monday, '08:00:00', '09:00:00', 30);
        $appointment = $this->appointment($this->monday, '08:15:00', 30, 'CONFIRMADO');

        $response = $this->feed('semana', $this->monday)->assertOk();
        $body = $response->getContent();
        $event = collect($response->json('eventos'))->firstWhere('id', 'cita-'.$appointment->id);

        $this->assertSame('08:15', $event['extendedProps']['hora_inicio']);
        $this->assertSame('Baseline Test Paciente', $event['extendedProps']['paciente']);
        $this->assertSame('Consulta baseline', $event['extendedProps']['servicio']);
        $this->assertSame($appointment->id, $event['extendedProps']['appointment_id']);
        $this->assertArrayNotHasKey('numero_identidad', $event['extendedProps']);
        $this->assertArrayNotHasKey('telefono', $event['extendedProps']);
        $this->assertArrayNotHasKey('email', $event['extendedProps']);
        $this->assertStringNotContainsString($appointment->patient->numero_identidad, $body);
    }

    /**
     * The month view answers how loaded a day is. Shipping every interval of every day would
     * be weight nobody reads, so it sends one summary per professional and day instead.
     */
    public function test_the_month_view_summarises_the_load_instead_of_listing_intervals(): void
    {
        $this->block($this->monday, '08:00:00', '09:00:00', 30);

        $response = $this->feed('mes', $this->monday)->assertOk();

        $response->assertJsonPath('rango.inicio', Carbon::parse($this->monday)->startOfMonth()->toDateString())
            ->assertJsonPath('rango.fin', Carbon::parse($this->monday)->endOfMonth()->toDateString())
            ->assertJsonPath('rango.detallada', false)
            ->assertJsonCount(1, 'eventos')
            ->assertJsonPath('eventos.0.allDay', true)
            ->assertJsonPath('eventos.0.title', '2 libres / 0 ocupadas');

        $this->assertArrayNotHasKey('slots', $response->json('profesionales.0.dias.0'));
    }

    public function test_an_unknown_view_is_rejected(): void
    {
        $this->actingAs($this->reader())
            ->getJson(self::FEED_URI.'?vista=anio&fecha='.$this->monday)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['vista']);
    }

    public function test_the_feed_requires_a_view_and_a_date(): void
    {
        $this->actingAs($this->reader())
            ->getJson(self::FEED_URI)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['vista', 'fecha']);
    }

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */

    public function test_moving_to_another_day_changes_what_is_returned(): void
    {
        $tuesday = Carbon::parse($this->monday)->addDay()->toDateString();
        $this->block($tuesday, '08:00:00', '09:00:00', 30);

        $this->feed('dia', $this->monday)->assertOk()->assertJsonPath('resumen.total', 0);
        $this->feed('dia', $tuesday)->assertOk()->assertJsonPath('resumen.total', 2);
    }

    public function test_a_range_crossing_the_year_is_answered(): void
    {
        $december = Carbon::create(Carbon::today()->year, 12, 15);
        $this->block($december->toDateString(), '08:00:00', '09:00:00', 30);

        $this->feed('mes', $december->toDateString())
            ->assertOk()
            ->assertJsonPath('rango.fin', $december->copy()->endOfMonth()->toDateString())
            ->assertJsonPath('resumen.libres', 2);
    }

    /*
    |--------------------------------------------------------------------------
    | Filters and professional comparison
    |--------------------------------------------------------------------------
    */

    public function test_filtering_by_professional_excludes_the_others(): void
    {
        $other = $this->extraDoctor('Doctora Segunda');
        $this->block($this->monday, '08:00:00', '09:00:00', 30);
        $this->block($this->monday, '08:00:00', '09:00:00', 30, $other->id);

        $this->feed('dia', $this->monday, ['doctor_id' => [$other->id]])
            ->assertOk()
            ->assertJsonCount(1, 'profesionales')
            ->assertJsonPath('profesionales.0.id', $other->id)
            ->assertJsonPath('comparando', false);
    }

    public function test_filtering_by_specialty_keeps_only_its_professionals(): void
    {
        $otherSpecialty = Specialty::create(['nombre' => 'Otra especialidad', 'estado' => 'ACTIVO']);
        $this->extraDoctor('Doctora Otra', $otherSpecialty->id);

        $this->feed('dia', $this->monday, ['specialty_id' => $otherSpecialty->id])
            ->assertOk()
            ->assertJsonCount(1, 'profesionales')
            ->assertJsonPath('profesionales.0.especialidad', 'Otra especialidad');
    }

    /**
     * Comparing professionals is the question the board exists to answer: who has room.
     */
    public function test_several_professionals_are_compared_in_the_same_window(): void
    {
        $other = $this->extraDoctor('Doctora Segunda');
        $this->block($this->monday, '08:00:00', '09:00:00', 30);
        $this->block($this->monday, '08:00:00', '08:30:00', 30, $other->id);

        $response = $this->feed('dia', $this->monday, [
            'doctor_id' => [$this->catalog['doctor']->id, $other->id],
        ])->assertOk();

        $response->assertJsonPath('comparando', true)
            ->assertJsonCount(2, 'profesionales')
            ->assertJsonPath('resumen.libres', 3);

        $byName = collect($response->json('profesionales'))->keyBy('nombre');
        $this->assertSame(2, $byName[$this->catalog['doctor']->nombre]['resumen']['libres']);
        $this->assertSame(1, $byName['Doctora Segunda']['resumen']['libres']);

        // With more than one professional the event title says whose slot it is.
        $this->assertStringContainsString('Doctora Segunda', collect($response->json('eventos'))
            ->pluck('title')
            ->implode(' '));
    }

    public function test_the_default_board_is_capped_and_says_so(): void
    {
        for ($i = 0; $i < AgendaFeedController::DEFAULT_SHOWN + 2; $i++) {
            $this->extraDoctor('Doctora '.$i);
        }

        $this->feed('dia', $this->monday)
            ->assertOk()
            ->assertJsonCount(AgendaFeedController::DEFAULT_SHOWN, 'profesionales')
            ->assertJsonPath('profesionales_mostrados', AgendaFeedController::DEFAULT_SHOWN)
            ->assertJsonPath('truncado', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Traffic light and schedules
    |--------------------------------------------------------------------------
    */

    public function test_a_scheduled_and_a_confirmed_appointment_read_differently(): void
    {
        $this->block($this->monday, '08:00:00', '09:00:00', 30);
        $this->appointment($this->monday, '08:00:00', 30, 'PROGRAMADO');
        $this->appointment($this->monday, '08:30:00', 30, 'CONFIRMADO');

        $response = $this->feed('dia', $this->monday)->assertOk();

        $this->assertSame(
            ['PROGRAMADO', 'CONFIRMADO'],
            collect($response->json('profesionales.0.dias.0.slots'))->pluck('leyenda')->all()
        );
    }

    /**
     * Only the distinctions this increment needs are modelled. Every other production state
     * reads as one generic "Ocupado" rather than leaking a half-built state machine.
     */
    public function test_any_other_consuming_state_reads_as_generically_occupied(): void
    {
        $this->block($this->monday, '08:00:00', '08:30:00', 30);
        $this->appointment($this->monday, '08:00:00', 30, 'EN_ATENCION');

        $this->feed('dia', $this->monday)
            ->assertOk()
            ->assertJsonPath('profesionales.0.dias.0.slots.0.leyenda', 'OCUPADO');
    }

    public function test_an_appointment_is_one_row_with_its_real_duration(): void
    {
        $this->block($this->monday, '08:00:00', '10:00:00', 15);
        $appointment = $this->appointment($this->monday, '08:15:00', 45, 'CONFIRMADO');

        $events = $this->feed('dia', $this->monday)->assertOk()->json('eventos');
        $appointmentEvents = collect($events)->where('id', 'cita-'.$appointment->id)->values();

        $this->assertCount(1, $appointmentEvents);
        $this->assertSame($this->monday.'T08:15:00', $appointmentEvents[0]['start']);
        $this->assertSame($this->monday.'T09:00:00', $appointmentEvents[0]['end']);
        $this->assertSame(45, $appointmentEvents[0]['extendedProps']['minutos']);
    }

    public function test_the_legend_never_relies_on_colour_alone(): void
    {
        $legend = $this->feed('dia', $this->monday)->assertOk()->json('leyenda');

        $this->assertNotEmpty($legend);

        foreach ($legend as $entry) {
            $this->assertNotEmpty($entry['etiqueta']);
            $this->assertNotEmpty($entry['glifo']);
            $this->assertNotEmpty($entry['color']);
        }
    }

    public function test_a_professional_without_hours_is_reported_as_such(): void
    {
        $this->feed('dia', $this->monday)
            ->assertOk()
            ->assertJsonPath('profesionales.0.dias.0.con_horario', false)
            ->assertJsonPath('profesionales.0.dias_con_horario', 0)
            ->assertJsonPath('profesionales.0.dias.0.slots', [])
            ->assertJsonPath('eventos', []);
    }

    public function test_several_blocks_on_one_day_are_kept_apart_and_ordered(): void
    {
        $this->block($this->monday, '08:00:00', '09:00:00', 30);
        $this->block($this->monday, '15:00:00', '16:00:00', 30);

        $this->feed('dia', $this->monday)
            ->assertOk()
            ->assertJsonPath('profesionales.0.dias.0.resumen.libres', 4)
            ->assertJsonPath('profesionales.0.dias.0.slots.0.inicio', '08:00')
            ->assertJsonPath('profesionales.0.dias.0.slots.2.inicio', '15:00');
    }

    public function test_the_duration_of_each_interval_is_visible(): void
    {
        $this->block($this->monday, '08:00:00', '08:45:00', 45);

        $this->feed('dia', $this->monday)
            ->assertOk()
            ->assertJsonPath('profesionales.0.dias.0.slots.0.minutos', 45)
            ->assertJsonPath('profesionales.0.dias.0.resumen.minutos_libres', 45)
            ->assertJsonPath('eventos.0.end', $this->monday.'T08:45:00');
    }

    /*
    |--------------------------------------------------------------------------
    | Sites
    |--------------------------------------------------------------------------
    */

    /**
     * A legacy block has no site. Filtering by site must still show it, because hiding
     * inherited occupancy is how double booking happens.
     */
    public function test_a_legacy_block_without_a_site_still_appears_when_filtering_by_site(): void
    {
        $site = $this->createSite();
        $this->block($this->monday, '08:00:00', '09:00:00', 30);

        $this->feed('dia', $this->monday, ['site_id' => $site->id])
            ->assertOk()
            ->assertJsonPath('site_id', $site->id)
            ->assertJsonPath('resumen.libres', 2)
            ->assertJsonPath('profesionales.0.dias.0.slots.0.site_id', null);
    }

    public function test_a_block_of_another_site_is_excluded(): void
    {
        $requested = $this->createSite(['nombre' => 'Sede A', 'codigo' => 'A']);
        $other = $this->createSite(['nombre' => 'Sede B', 'codigo' => 'B']);
        $this->block($this->monday, '08:00:00', '09:00:00', 30, null, $other->id);

        $this->feed('dia', $this->monday, ['site_id' => $requested->id])
            ->assertOk()
            ->assertJsonPath('resumen.total', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Contract and performance
    |--------------------------------------------------------------------------
    */

    public function test_the_selected_interval_carries_the_context_quick_booking_will_need(): void
    {
        $site = $this->createSite();
        $this->block($this->monday, '08:00:00', '08:30:00', 30, null, $site->id);

        $event = $this->feed('dia', $this->monday, ['site_id' => $site->id])
            ->assertOk()
            ->json('eventos.0.extendedProps');

        $this->assertSame($this->catalog['doctor']->id, $event['doctor_id']);
        $this->assertSame($this->catalog['doctor']->nombre, $event['doctor']);
        $this->assertSame($this->monday, $event['fecha']);
        $this->assertSame('08:00', $event['hora_inicio']);
        $this->assertSame('08:30', $event['hora_fin']);
        $this->assertSame(30, $event['minutos']);
        $this->assertSame($site->id, $event['site_id']);
        $this->assertTrue($event['seleccionable']);
        $this->assertSame('slot_libre', $event['tipo_contexto']);
        $this->assertNull($event['patient_id']);
    }

    public function test_the_authorised_operational_board_exposes_only_minimum_patient_context(): void
    {
        $this->block($this->monday, '08:00:00', '09:00:00', 30);
        $appointment = $this->appointment($this->monday, '08:00:00', 30, 'CONFIRMADO');

        $response = $this->feed('dia', $this->monday)->assertOk();
        $body = $response->getContent();
        $event = collect($response->json('eventos'))->firstWhere('id', 'cita-'.$appointment->id);

        $this->assertSame('cita_existente', $event['extendedProps']['tipo_contexto']);
        $this->assertSame($appointment->patient_id, $event['extendedProps']['patient_id']);
        $this->assertSame('Baseline Test Paciente', $event['extendedProps']['paciente']);
        $this->assertSame('Consulta baseline', $event['extendedProps']['servicio']);
        $this->assertSame('CONFIRMADO', $event['extendedProps']['estado_cita']);
        $this->assertSame('PENDIENTE', $event['extendedProps']['estado_pagado']);
        $this->assertSame(
            (string) $appointment->patient->historia_clinica,
            $event['extendedProps']['historia_clinica']
        );
        $this->assertStringContainsString($appointment->patient->nombre, $body);

        // The feed stays minimal: legacy H.C. and the price already stored on the
        // appointment, without contact data, identity document or clinical notes.
        $this->assertStringNotContainsString($appointment->patient->numero_identidad, $body);
        $this->assertStringNotContainsString($appointment->numero_cita, $body);
        $this->assertArrayNotHasKey('numero_identidad', $event['extendedProps']);
        $this->assertArrayNotHasKey('email', $event['extendedProps']);
        $this->assertArrayNotHasKey('telefono', $event['extendedProps']);
        $this->assertArrayNotHasKey('observaciones', $event['extendedProps']);
        $this->assertEquals(
            (float) $appointment->precio_programado,
            (float) $event['extendedProps']['precio_programado']
        );
        $this->assertSame($appointment->service_id, $event['extendedProps']['service_id']);
        $this->assertSame((int) $appointment->user_id, $event['extendedProps']['creator_user_id']);
        $this->assertNull($event['extendedProps']['responsible_user_id']);
        $this->assertStringNotContainsString('hora_cita', $body);
    }

    public function test_an_existing_appointment_exposes_its_stored_price_service_and_people(): void
    {
        $this->block($this->monday, '08:00:00', '09:00:00', 30);
        $appointment = $this->appointment($this->monday, '08:00:00', 30, 'PROGRAMADO');
        $commercial = $this->createUser();
        $appointment->forceFill([
            'precio_programado' => 150,
            'responsible_user_id' => $commercial->id,
        ])->save();

        $event = collect($this->feed('dia', $this->monday)->assertOk()->json('eventos'))
            ->firstWhere('id', 'cita-'.$appointment->id);

        $this->assertSame('cita_existente', $event['extendedProps']['tipo_contexto']);
        $this->assertFalse($event['extendedProps']['seleccionable']);
        $this->assertSame($this->catalog['service']->id, $event['extendedProps']['service_id']);
        $this->assertSame('Consulta baseline', $event['extendedProps']['servicio']);
        $this->assertEquals(150, (float) $event['extendedProps']['precio_programado']);
        $this->assertSame($commercial->id, $event['extendedProps']['responsible_user_id']);
        $this->assertSame($commercial->name, $event['extendedProps']['responsable']);
        $this->assertSame((int) $appointment->user_id, $event['extendedProps']['creator_user_id']);
        $this->assertSame($appointment->user->name, $event['extendedProps']['creador']);
        $this->assertSame('PROGRAMADO', $event['extendedProps']['estado_cita']);
        $this->assertSame('PENDIENTE', $event['extendedProps']['estado_pagado']);
    }

    public function test_an_occupied_row_reads_as_time_state_patient_and_service(): void
    {
        $this->block($this->monday, '08:00:00', '09:00:00', 30);
        $appointment = $this->appointment($this->monday, '08:00:00', 30, 'CONFIRMADO');

        $event = collect($this->feed('dia', $this->monday)->assertOk()->json('eventos'))
            ->firstWhere('id', 'cita-'.$appointment->id);

        $this->assertSame(
            '08:00 | Sí | PENDIENTE | '.$appointment->patient->historia_clinica.' | Baseline Test Paciente',
            $event['title']
        );
        $this->assertSame('cita_existente', $event['extendedProps']['tipo_contexto']);
        $this->assertFalse($event['extendedProps']['seleccionable']);
    }

    public function test_payment_column_uses_the_real_appointment_payment_state(): void
    {
        $this->block($this->monday, '08:00:00', '10:00:00', 15);

        foreach (['PENDIENTE', 'PARCIAL', 'PAGADO'] as $index => $payment) {
            $appointment = $this->appointment(
                $this->monday,
                Carbon::parse('08:00')->addMinutes($index * 30)->format('H:i:s'),
                15,
                'CONFIRMADO',
                $payment
            );

            $event = collect($this->feed('dia', $this->monday)->assertOk()->json('eventos'))
                ->firstWhere('id', 'cita-'.$appointment->id);

            $this->assertSame($payment, $event['extendedProps']['estado_pagado']);
        }
    }

    public function test_the_month_view_does_not_include_patient_identity(): void
    {
        $this->block($this->monday, '08:00:00', '09:00:00', 30);
        $appointment = $this->appointment($this->monday, '08:00:00', 30, 'CONFIRMADO');

        $appointment->patient->update(['nombre' => 'NOMBRE_PRIVADO_QA_94721', 'apellido_paterno' => 'APELLIDO_PRIVADO_QA_94721']);

        $response = $this->feed('mes', $this->monday)->assertOk();
        $body = $response->getContent();

        $this->assertStringNotContainsString($appointment->patient->nombre, $body);
        $this->assertStringNotContainsString($appointment->patient->apellido_paterno, $body);
        $this->assertTrue(collect($response->json('eventos'))->every(
            fn (array $event) => ($event['allDay'] ?? false) === true
        ));
    }

    public function test_complete_registration_stays_prepared_hidden_and_disabled_until_a_legacy_patient_is_selected(): void
    {
        $this->actingAs($this->reader())
            ->get(self::PAGE_URI)
            ->assertOk()
            ->assertSee('id="agenda-complete-registration" disabled hidden', false)
            ->assertSee('id="agenda-overlap-start"', false)
            ->assertSee('id="agenda-quick-patient-id"', false)
            ->assertSee('class="agenda-operations"', false);
    }

    /**
     * A whole month of several professionals must not cost a query per day. The engine reads
     * the blocks once and the appointments once, so a month costs the same as a single day —
     * which is the claim worth testing, rather than an arbitrary ceiling.
     */
    public function test_a_month_costs_the_same_number_of_queries_as_a_single_day(): void
    {
        $others = collect(range(1, 3))->map(fn ($i) => $this->extraDoctor('Doctora '.$i));
        $month = Carbon::parse($this->monday);

        foreach ($month->copy()->startOfMonth()->daysUntil($month->copy()->endOfMonth()) as $date) {
            $this->block($date->toDateString(), '08:00:00', '09:00:00', 30);
        }

        $ids = $others->pluck('id')->push($this->catalog['doctor']->id)->all();
        $reader = $this->reader();
        $request = fn (string $view) => $this->actingAs($reader)
            ->getJson($this->feedUri($view, $this->monday, ['doctor_id' => $ids]))
            ->assertOk();

        // Warm-up: the first request also fills the permission cache, which would otherwise be
        // charged to whichever view happened to run first.
        $request('dia');

        $day = $this->countQueries(fn () => $request('dia'));
        $full = $this->countQueries(fn () => $request('mes'));

        $this->assertSame(
            $day,
            $full,
            'Un mes costó '.$full.' consultas frente a '.$day.' de un solo día.'
        );
    }

    private function countQueries(callable $action): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $action();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        return $count;
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function feed(string $view, string $date, array $extra = [])
    {
        return $this->actingAs($this->reader())->getJson($this->feedUri($view, $date, $extra));
    }

    private function feedUri(string $view = 'dia', ?string $date = null, array $extra = []): string
    {
        return self::FEED_URI.'?'.http_build_query([
            'vista' => $view,
            'fecha' => $date ?: $this->monday,
        ] + $extra);
    }

    private function agendaOperator(string $role): User
    {
        $user = $this->createUserWithRole($role);
        $user->givePermissionTo(Permission::findOrCreate(SchedulingCapability::MVP_ACCESS, 'web'));
        $user->givePermissionTo(Permission::findOrCreate(SchedulingCapability::VIEW, 'web'));

        return $user;
    }

    private function reader(): User
    {
        if ($this->reader === null) {
            $this->reader = $this->createUser();
            $this->reader->givePermissionTo(Permission::findOrCreate(SchedulingCapability::MVP_ACCESS, 'web'));
            $this->reader->givePermissionTo(Permission::findOrCreate(SchedulingCapability::VIEW, 'web'));
        }

        return $this->reader;
    }

    private function extraDoctor(string $name, ?int $specialtyId = null): Doctor
    {
        return Doctor::create([
            'specialty_id' => $specialtyId ?: $this->catalog['specialty']->id,
            'nombre' => $name,
            'estado' => 'ACTIVO',
        ]);
    }

    private function block(
        string $date,
        string $start,
        string $end,
        int $minutes,
        ?int $doctorId = null,
        ?int $siteId = null
    ): DoctorSchedule {
        return DoctorSchedule::create([
            'doctor_id' => $doctorId ?: $this->catalog['doctor']->id,
            'site_id' => $siteId,
            'dia_semana' => Carbon::parse($date)->dayOfWeekIso,
            'fecha_cita' => $date,
            'hora_inicio' => $start,
            'hora_fin' => $end,
            'duracion_cita' => $minutes,
            'estado' => 'ACTIVO',
        ]);
    }

    private function appointment(
        string $date,
        string $time,
        int $minutes,
        string $state,
        string $payment = 'PENDIENTE'
    ): Appointment
    {
        $creator = $this->createUser();
        $patient = $this->createPatient($creator, [
            'numero_identidad' => (string) random_int(10000000, 99999999),
            'historia_clinica' => random_int(1000, 999999),
        ]);

        return Appointment::create([
            'numero_cita' => 'CIT-'.uniqid(),
            'user_id' => $creator->id,
            'patient_id' => $patient->id,
            'doctor_id' => $this->catalog['doctor']->id,
            'service_id' => $this->catalog['service']->id,
            'additional_rate_id' => $this->catalog['rate']->id,
            'fecha_cita' => $date,
            'hora_cita' => $time,
            'duracion_cita' => $minutes,
            'estado_cita' => $state,
            'estado_pagado' => $payment,
        ]);
    }
}
