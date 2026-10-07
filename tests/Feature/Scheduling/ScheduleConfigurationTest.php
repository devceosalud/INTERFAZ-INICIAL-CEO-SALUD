<?php

namespace Tests\Feature\Scheduling;

use App\Models\DoctorSchedule;
use App\Models\User;
use App\Support\Scheduling\SchedulingCapability;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

/**
 * Configuring operating hours: the advisory overlap warning, and the optional site.
 */
class ScheduleConfigurationTest extends TestCase
{
    use BuildsBaselineData;
    use RefreshDatabase;

    private const OVERLAP_URI = '/scheduling-mvp/schedule-overlap';

    private const STORE_URI = '/admissionist/doctor-schedule/store';

    private const UPDATE_URI = '/admissionist/doctor-schedule/update';

    /** @var array */
    private $catalog;

    /** @var string */
    private $date;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('scheduling.enabled', true);
        $this->catalog = $this->createAppointmentCatalog();
        DoctorSchedule::query()->delete();
        $this->date = Carbon::today()->addDays(7)->toDateString();
    }

    /*
    |--------------------------------------------------------------------------
    | Overlap warning
    |--------------------------------------------------------------------------
    */

    public function test_the_warning_is_hidden_while_the_feature_flag_is_off(): void
    {
        config()->set('scheduling.enabled', false);

        $this->actingAs($this->reader())
            ->getJson($this->overlapUri('08:00', '12:00'))
            ->assertNotFound();
    }

    public function test_the_warning_requires_an_explicit_capability(): void
    {
        $user = $this->createUser();
        $user->givePermissionTo(Permission::findOrCreate(SchedulingCapability::MVP_ACCESS, 'web'));

        $this->actingAs($user)->getJson($this->overlapUri('08:00', '12:00'))->assertForbidden();
    }

    public function test_a_clear_interval_is_reported_as_clear(): void
    {
        $this->block('08:00:00', '12:00:00');

        $this->actingAs($this->reader())
            ->getJson($this->overlapUri('13:00', '17:00'))
            ->assertOk()
            ->assertJsonPath('solapa', false)
            ->assertJsonPath('bloques', []);
    }

    public function test_a_crossing_interval_is_reported_with_the_blocks_it_crosses(): void
    {
        $existing = $this->block('08:00:00', '12:00:00');

        $this->actingAs($this->reader())
            ->getJson($this->overlapUri('11:00', '15:00'))
            ->assertOk()
            ->assertJsonPath('solapa', true)
            ->assertJsonCount(1, 'bloques')
            ->assertJsonPath('bloques.0.id', $existing->id)
            ->assertJsonPath('bloques.0.hora_inicio', '08:00')
            ->assertJsonPath('bloques.0.hora_fin', '12:00');
    }

    /**
     * The warning must never be read as a refusal: inherited data may already contain
     * overlaps, so this increment informs and lets the user decide.
     */
    public function test_the_warning_declares_itself_non_blocking(): void
    {
        $this->block('08:00:00', '12:00:00');

        $this->actingAs($this->reader())
            ->getJson($this->overlapUri('11:00', '15:00'))
            ->assertOk()
            ->assertJsonPath('bloqueante', false);
    }

    public function test_touching_intervals_are_not_a_crossing(): void
    {
        $this->block('08:00:00', '12:00:00');

        $this->actingAs($this->reader())
            ->getJson($this->overlapUri('12:00', '16:00'))
            ->assertOk()
            ->assertJsonPath('solapa', false);
    }

    public function test_a_block_being_edited_does_not_overlap_itself(): void
    {
        $existing = $this->block('08:00:00', '12:00:00');

        $this->actingAs($this->reader())
            ->getJson($this->overlapUri('08:00', '12:00', ['doctor_schedule_id' => $existing->id]))
            ->assertOk()
            ->assertJsonPath('solapa', false);
    }

    public function test_an_inactive_block_is_not_considered(): void
    {
        $this->block('08:00:00', '12:00:00')->update(['estado' => 'INACTIVO']);

        $this->actingAs($this->reader())
            ->getJson($this->overlapUri('09:00', '11:00'))
            ->assertOk()
            ->assertJsonPath('solapa', false);
    }

    public function test_the_warning_validates_its_input(): void
    {
        $this->actingAs($this->reader())
            ->getJson(self::OVERLAP_URI.'?doctor_id=999999&fecha_cita=nope&hora_inicio=12:00&hora_fin=08:00')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['doctor_id', 'fecha_cita', 'hora_fin']);
    }

    /*
    |--------------------------------------------------------------------------
    | Optional site on the inherited flow
    |--------------------------------------------------------------------------
    */

    public function test_a_block_can_now_be_created_with_a_site(): void
    {
        $site = $this->createSite();

        $this->actingAs($this->createUserWithRole('ADMISION'))
            ->postJson(self::STORE_URI, [
                'doctor_id' => $this->catalog['doctor']->id,
                'fecha_cita' => $this->date,
                'hora_inicio' => '08:00',
                'hora_fin' => '12:00',
                'duracion_cita' => 30,
                'site_id' => $site->id,
            ])
            ->assertOk()
            ->assertJsonPath('code', 1);

        $this->assertSame($site->id, (int) DoctorSchedule::first()->site_id);
    }

    /**
     * The site stays optional, so the inherited form keeps working exactly as before.
     */
    public function test_a_block_created_without_a_site_keeps_none(): void
    {
        $this->actingAs($this->createUserWithRole('ADMISION'))
            ->postJson(self::STORE_URI, [
                'doctor_id' => $this->catalog['doctor']->id,
                'fecha_cita' => $this->date,
                'hora_inicio' => '08:00',
                'hora_fin' => '12:00',
                'duracion_cita' => 30,
            ])
            ->assertOk()
            ->assertJsonPath('code', 1);

        $this->assertNull(DoctorSchedule::first()->site_id);
    }

    public function test_an_unknown_site_is_rejected(): void
    {
        $this->actingAs($this->createUserWithRole('ADMISION'))
            ->postJson(self::STORE_URI, [
                'doctor_id' => $this->catalog['doctor']->id,
                'fecha_cita' => $this->date,
                'hora_inicio' => '08:00',
                'hora_fin' => '12:00',
                'duracion_cita' => 30,
                'site_id' => 999999,
            ])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonStructure(['error' => ['site_id']]);

        $this->assertSame(0, DoctorSchedule::count());
    }

    /**
     * A form that does not send the site must not wipe the site already stored: the inherited
     * update payload has no site field at all.
     */
    public function test_updating_without_sending_a_site_keeps_the_stored_one(): void
    {
        $site = $this->createSite();
        $block = $this->block('08:00:00', '12:00:00');
        $block->update(['site_id' => $site->id]);

        $this->actingAs($this->createUserWithRole('ADMISION'))
            ->putJson(self::UPDATE_URI, [
                'doctor_schedule_id_edit' => $block->id,
                'doctor_id_edit' => $this->catalog['doctor']->id,
                'fecha_cita_edit' => $this->date,
                'hora_inicio_edit' => '09:00',
                'hora_fin_edit' => '13:00',
                'duracion_edit_cita' => 30,
            ])
            ->assertOk()
            ->assertJsonPath('code', 1);

        $this->assertSame($site->id, (int) $block->fresh()->site_id);
    }

    public function test_updating_can_clear_the_site_when_the_form_sends_it_empty(): void
    {
        $site = $this->createSite();
        $block = $this->block('08:00:00', '12:00:00');
        $block->update(['site_id' => $site->id]);

        $this->actingAs($this->createUserWithRole('ADMISION'))
            ->putJson(self::UPDATE_URI, [
                'doctor_schedule_id_edit' => $block->id,
                'doctor_id_edit' => $this->catalog['doctor']->id,
                'fecha_cita_edit' => $this->date,
                'hora_inicio_edit' => '09:00',
                'hora_fin_edit' => '13:00',
                'duracion_edit_cita' => 30,
                'site_id_edit' => '',
            ])
            ->assertOk()
            ->assertJsonPath('code', 1);

        $this->assertNull($block->fresh()->site_id);
    }

    /*
    |--------------------------------------------------------------------------
    | Inherited schedule page
    |--------------------------------------------------------------------------
    */

    public function test_the_schedule_page_exposes_the_operational_calendar_and_real_feed(): void
    {
        $site = $this->createSite(['nombre' => 'Sede Norte']);
        $this->block('08:00:00', '12:00:00')->update(['site_id' => $site->id]);
        $this->block('15:00:00', '18:00:00');

        $user = $this->createUserWithRole('ADMISION');

        $this->actingAs($user)
            ->get('/admissionist/doctor-schedule')
            ->assertOk()
            ->assertSee('Horarios médicos')
            ->assertSee('data-calendar-view="timeGridWeek"', false)
            ->assertSee('Duración programada por cita')
            ->assertSee('Sede Norte');

        $this->actingAs($user)
            ->getJson('/admissionist/doctor-schedule/calendar?'.http_build_query([
                'start' => $this->date,
                'end' => $this->date,
                'doctor_id' => $this->catalog['doctor']->id,
            ]))
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.extendedProps.start_time', '08:00')
            ->assertJsonPath('0.extendedProps.end_time', '12:00')
            ->assertJsonPath('0.extendedProps.appointment_duration', 30)
            ->assertJsonPath('0.extendedProps.site_name', 'Sede Norte')
            ->assertJsonPath('1.extendedProps.start_time', '15:00')
            ->assertJsonPath('1.extendedProps.end_time', '18:00');
    }

    private function overlapUri(string $start, string $end, array $extra = []): string
    {
        return self::OVERLAP_URI.'?'.http_build_query([
            'doctor_id' => $this->catalog['doctor']->id,
            'fecha_cita' => $this->date,
            'hora_inicio' => $start,
            'hora_fin' => $end,
        ] + $extra);
    }

    private function reader(): User
    {
        $user = $this->createUser();
        $user->givePermissionTo(Permission::findOrCreate(SchedulingCapability::MVP_ACCESS, 'web'));
        $user->givePermissionTo(Permission::findOrCreate(SchedulingCapability::VIEW, 'web'));

        return $user;
    }

    private function block(string $start, string $end): DoctorSchedule
    {
        return DoctorSchedule::create([
            'doctor_id' => $this->catalog['doctor']->id,
            'dia_semana' => Carbon::parse($this->date)->dayOfWeekIso,
            'fecha_cita' => $this->date,
            'hora_inicio' => $start,
            'hora_fin' => $end,
            'duracion_cita' => 30,
            'estado' => 'ACTIVO',
        ]);
    }
}
