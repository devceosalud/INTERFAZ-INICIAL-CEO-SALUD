<?php

namespace Tests\Feature\Scheduling;

use App\Models\Appointment;
use App\Services\Scheduling\DoctorAvailabilityService;
use App\Support\Scheduling\AgendaQuery;
use App\Support\Scheduling\AppointmentAgendaLifecycle as Lifecycle;
use App\Support\Scheduling\AvailabilityQuery;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

class AppointmentAgendaOccupancyTest extends TestCase
{
    use BuildsBaselineData;
    use RefreshDatabase;

    /** @dataProvider classifications */
    public function test_regular_occupancy_agrees_in_memory_sql_and_day_range_availability(
        string $agendaState,
        ?string $bookingType,
        string $careState,
        bool $blocks
    ): void {
        $creator = $this->createUser();
        $patient = $this->createPatient($creator);
        $catalog = $this->createAppointmentCatalog();
        $catalog['schedule']->update(['hora_fin' => '09:00:00', 'duracion_cita' => 15]);
        $appointment = Appointment::create([
            'numero_cita' => 'OCCUPANCY-1',
            'user_id' => $creator->id,
            'patient_id' => $patient->id,
            'doctor_id' => $catalog['doctor']->id,
            'service_id' => $catalog['service']->id,
            'additional_rate_id' => $catalog['rate']->id,
            'fecha_cita' => $catalog['schedule']->fecha_cita,
            'hora_cita' => '08:00:00',
            'duracion_cita' => 30,
            'estado_cita' => $careState,
            'estado_agenda' => $agendaState,
            'tipo_agendamiento' => $bookingType,
        ])->fresh();

        $this->assertSame($blocks, Lifecycle::consumesRegularSlot($appointment->estado_agenda, $appointment->tipo_agendamiento, $appointment->estado_cita));
        $this->assertSame($blocks, Appointment::consumingRegularSlot()->whereKey($appointment->id)->exists());
        $this->assertSame($blocks, Lifecycle::applyRegularSlotOccupancy(DB::table('appointments'))->where('id', $appointment->id)->exists());

        $date = Carbon::parse($appointment->fecha_cita);
        $engine = app(DoctorAvailabilityService::class);
        $day = $engine->forDay(new AvailabilityQuery($catalog['doctor']->id, $date));
        $range = $engine->forRange(new AgendaQuery([$catalog['doctor']->id], $date, $date))
            ->get($catalog['doctor']->id.'|'.$date->toDateString());

        foreach ([$day, $range] as $availability) {
            $this->assertSame(!$blocks, $availability->isAvailableAt('08:00'));
            $this->assertSame(!$blocks, $availability->isAvailableAt('08:15'));
            $this->assertTrue($availability->isAvailableAt('08:30'));
        }
    }

    public static function classifications(): array
    {
        return [
            'legacy scheduled' => [Lifecycle::LEGACY, null, 'PROGRAMADO', true],
            'legacy confirmed' => [Lifecycle::LEGACY, null, 'CONFIRMADO', true],
            'legacy cancelled' => [Lifecycle::LEGACY, null, 'CANCELADO', false],
            'legacy no show' => [Lifecycle::LEGACY, null, 'NO_ASISTIO', false],
            'legacy type cannot override care policy' => [Lifecycle::LEGACY, Lifecycle::ADDITIONAL, 'PROGRAMADO', true],
            'pending unclassified' => [Lifecycle::PENDING_CONFIRMATION, null, 'PROGRAMADO', false],
            'pending additional' => [Lifecycle::PENDING_CONFIRMATION, Lifecycle::ADDITIONAL, 'CONFIRMADO', false],
            'confirmed regular' => [Lifecycle::CONFIRMED, Lifecycle::REGULAR, 'CONFIRMADO', true],
            'confirmed additional' => [Lifecycle::CONFIRMED, Lifecycle::ADDITIONAL, 'CONFIRMADO', false],
            'cancelled regular' => [Lifecycle::CONFIRMED, Lifecycle::REGULAR, 'CANCELADO', false],
            'no show regular' => [Lifecycle::CONFIRMED, Lifecycle::REGULAR, 'NO_ASISTIO', false],
            'unclassified confirmed conservatively blocks' => [Lifecycle::CONFIRMED, null, 'PROGRAMADO', true],
        ];
    }
}
