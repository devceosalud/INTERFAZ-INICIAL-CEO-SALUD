<?php

namespace Tests\Feature\Scheduling;

use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Support\Scheduling\TimeRange;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

/**
 * READ-ONLY audit of overlapping operating blocks.
 *
 * MVP-2B deliberately does NOT reject an overlapping block on save: the inherited data may
 * already contain overlaps and refusing them now could stop real work. What this test does is
 * pin down the rule and the detection query, so the future increment that enables validation
 * starts from a measured baseline instead of an assumption.
 */
class DoctorScheduleOverlapAuditTest extends TestCase
{
    use BuildsBaselineData;
    use RefreshDatabase;

    /** @var array */
    private $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalog = $this->createAppointmentCatalog();
        DoctorSchedule::query()->delete();
    }

    /**
     * Current behaviour, not desired behaviour: two blocks of the same professional on the
     * same date can overlap and nothing stops it.
     */
    public function test_overlapping_blocks_are_still_accepted_today(): void
    {
        $date = Carbon::today()->addDays(5)->toDateString();

        $this->block($date, '08:00:00', '12:00:00');
        $this->block($date, '11:00:00', '15:00:00');

        $this->assertCount(2, DoctorSchedule::all());
        $this->assertCount(1, $this->overlappingPairs());
    }

    public function test_adjacent_blocks_do_not_overlap(): void
    {
        $date = Carbon::today()->addDays(5)->toDateString();

        $this->block($date, '08:00:00', '12:00:00');
        $this->block($date, '12:00:00', '16:00:00');

        $this->assertCount(0, $this->overlappingPairs());
    }

    public function test_blocks_of_different_professionals_never_overlap_each_other(): void
    {
        $date = Carbon::today()->addDays(5)->toDateString();
        $other = Doctor::create([
            'specialty_id' => $this->catalog['specialty']->id,
            'nombre' => 'Doctor Solapamiento',
            'estado' => 'ACTIVO',
        ]);

        $this->block($date, '08:00:00', '12:00:00');
        $this->block($date, '08:00:00', '12:00:00', $other->id);

        $this->assertCount(0, $this->overlappingPairs());
    }

    public function test_blocks_on_different_dates_never_overlap_each_other(): void
    {
        $this->block(Carbon::today()->addDays(5)->toDateString(), '08:00:00', '12:00:00');
        $this->block(Carbon::today()->addDays(6)->toDateString(), '08:00:00', '12:00:00');

        $this->assertCount(0, $this->overlappingPairs());
    }

    /**
     * The detection rule: same professional, same date, and ranges that overlap as half-open
     * intervals. Written with TimeRange so it cannot drift from the availability engine.
     *
     * @return Collection
     */
    private function overlappingPairs(): Collection
    {
        $blocks = DoctorSchedule::where('estado', 'ACTIVO')
            ->whereNotNull('fecha_cita')
            ->orderBy('doctor_id')
            ->orderBy('fecha_cita')
            ->orderBy('hora_inicio')
            ->get();

        $pairs = collect();

        foreach ($blocks as $index => $block) {
            foreach ($blocks->slice($index + 1) as $candidate) {
                if ($block->doctor_id !== $candidate->doctor_id) {
                    continue;
                }

                if ((string) $block->fecha_cita !== (string) $candidate->fecha_cita) {
                    continue;
                }

                if ($this->range($block)->overlaps($this->range($candidate))) {
                    $pairs->push([$block->id, $candidate->id]);
                }
            }
        }

        return $pairs;
    }

    private function range(DoctorSchedule $block): TimeRange
    {
        return new TimeRange(
            Carbon::parse($block->fecha_cita . ' ' . $block->hora_inicio),
            Carbon::parse($block->fecha_cita . ' ' . $block->hora_fin)
        );
    }

    private function block(string $date, string $start, string $end, ?int $doctorId = null): DoctorSchedule
    {
        return DoctorSchedule::create([
            'doctor_id' => $doctorId ?: $this->catalog['doctor']->id,
            'dia_semana' => Carbon::parse($date)->dayOfWeekIso,
            'fecha_cita' => $date,
            'hora_inicio' => $start,
            'hora_fin' => $end,
            'duracion_cita' => 30,
            'estado' => 'ACTIVO',
        ]);
    }
}
