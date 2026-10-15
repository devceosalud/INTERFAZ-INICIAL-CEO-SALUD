<?php

namespace Tests\Feature\Scheduling;

use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Services\Scheduling\ScheduleOverlapDetector;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

/**
 * READ-ONLY audit of overlapping operating blocks.
 *
 * New overlapping writes are rejected by the approved 14/10 policy. Existing overlapping
 * fixtures represent inherited records and remain readable without automatic repair.
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
    public function test_new_overlapping_blocks_are_rejected_without_changing_the_original(): void
    {
        $date = Carbon::today()->addDays(5)->toDateString();

        $this->block($date, '08:00:00', '12:00:00');
        $before=DoctorSchedule::first()->getAttributes();
        try{$this->block($date, '11:00:00', '15:00:00');$this->fail('New overlap was accepted');}
        catch(\Illuminate\Validation\ValidationException $e){$this->assertArrayHasKey('hora_inicio',$e->errors());}
        $this->assertCount(1, DoctorSchedule::all());$this->assertSame($before,DoctorSchedule::first()->getAttributes());
        $this->assertCount(0, $this->overlappingPairs());
    }

    public function test_inherited_overlaps_are_detected_and_preserved_when_a_new_overlap_is_rejected():void{
        $first=$this->block(Carbon::today()->addDays(5)->toDateString(),'08:00:00','12:00:00');
        // Test-only import of a legacy row; never use the normal write path to manufacture history.
        $legacy=$first->getAttributes();unset($legacy['id']);$legacy['hora_inicio']='11:00:00';$legacy['hora_fin']='15:00:00';
        \Illuminate\Support\Facades\DB::table('doctor_schedules')->insert($legacy);
        $before=DoctorSchedule::orderBy('id')->get()->map->getAttributes()->all();$this->assertCount(1,$this->overlappingPairs());
        try{$this->block($first->fecha_cita,'10:00:00','14:00:00');$this->fail('New overlap was accepted');}
        catch(\Illuminate\Validation\ValidationException $e){$this->assertArrayHasKey('hora_inicio',$e->errors());}
        $this->assertSame($before,DoctorSchedule::orderBy('id')->get()->map->getAttributes()->all());
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
     * The detection rule lives in ScheduleOverlapDetector, expressed with TimeRange, so the
     * audit, the UI warning and any future validation cannot answer differently.
     *
     * @return Collection
     */
    private function overlappingPairs(): Collection
    {
        return app(ScheduleOverlapDetector::class)->pairsIn(
            DoctorSchedule::where('estado', 'ACTIVO')
                ->orderBy('doctor_id')
                ->orderBy('fecha_cita')
                ->orderBy('hora_inicio')
                ->get()
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
