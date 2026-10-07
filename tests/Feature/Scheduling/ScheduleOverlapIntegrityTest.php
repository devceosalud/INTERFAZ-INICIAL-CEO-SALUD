<?php
namespace Tests\Feature\Scheduling;

use App\Models\DoctorSchedule;
use App\Models\Site;
use App\Services\Scheduling\AgendaBoardPresenter;
use App\Services\Scheduling\DoctorAvailabilityService;
use App\Services\Scheduling\RegularCapacityService;
use App\Support\Scheduling\AgendaQuery;
use App\Support\Scheduling\AgendaRange;
use App\Support\Scheduling\AvailabilityQuery;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

class ScheduleOverlapIntegrityTest extends TestCase
{
    use RefreshDatabase, BuildsAgendaLifecycleData;

    public function test_compatible_overlap_is_one_set_of_slots_in_feed_capacity_and_minutes(): void
    {
        $c = $this->createAppointmentCatalog(); $b = $c['schedule'];
        $b->update(['hora_inicio' => '08:00', 'hora_fin' => '10:00', 'duracion_cita' => 30]);
        DoctorSchedule::create(array_merge($b->only(['doctor_id','site_id','fecha_cita','dia_semana','duracion_cita','estado']), ['hora_inicio'=>'08:30','hora_fin'=>'09:30']));
        $date = Carbon::parse($b->fecha_cita); $id = (int) $b->doctor_id;
        $day = app(DoctorAvailabilityService::class)->forDay(new AvailabilityQuery($id,$date));
        $this->assertSame(['08:00','08:30','09:00','09:30'],$day->availableStartTimes());
        $range = new AgendaRange('dia',$date); $actor = $this->agendaReader();
        $feed = app(AgendaBoardPresenter::class)->build($range, collect([$c['doctor']]),$actor->id);
        $this->assertSame(4,$feed['resumen']['libres']); $this->assertSame(120,$feed['resumen']['minutos_libres']);
        $capacity = app(RegularCapacityService::class)->forRange(new AgendaQuery([$id],$date,$date));
        $this->assertSame(4,$capacity[0]['capacidad_regular']);
    }

    public function test_incompatible_duration_site_and_offset_are_rejected_without_changing_original(): void
    {
        $c=$this->createAppointmentCatalog();$b=$c['schedule'];
        $b->update(['hora_inicio'=>'08:00','hora_fin'=>'10:00','duracion_cita'=>30]);
        $site=Site::create(['codigo'=>'QA-OTHER','nombre'=>'QA LOCAL','estado'=>'ACTIVO']);
        foreach ([['duracion_cita'=>20],['site_id'=>$site->id],['hora_inicio'=>'08:10']] as $change) {
            $attributes=array_merge($b->only(['doctor_id','site_id','fecha_cita','dia_semana','duracion_cita','estado']),['hora_inicio'=>'08:30','hora_fin'=>'09:30'],$change);
            try { DoctorSchedule::create($attributes); $this->fail('Accepted incompatible schedule'); }
            catch (ValidationException $e) { $this->assertStringContainsString('Admisión debe revisar',$e->getMessage()); }
        }
        $this->assertSame(1,DoctorSchedule::count());
        $compatible=DoctorSchedule::create(array_merge($b->only(['doctor_id','site_id','fecha_cita','dia_semana','duracion_cita','estado']),['hora_inicio'=>'08:30','hora_fin'=>'09:30']));
        try { $compatible->update(['duracion_cita'=>20]); $this->fail('Accepted incompatible edit'); }
        catch (ValidationException $e) { $this->assertSame(30,(int)$compatible->fresh()->duracion_cita); }
        $compatible->fresh()->update(['estado'=>'INACTIVO']);
        $this->assertSame('INACTIVO',$compatible->fresh()->estado);
    }
}
