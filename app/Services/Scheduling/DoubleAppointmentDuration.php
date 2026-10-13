<?php
namespace App\Services\Scheduling;
use App\Support\Scheduling\AvailabilityQuery;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
final class DoubleAppointmentDuration {
    public function resolve(int $doctor,string $date,string $time,?int $site):array {
        $slots=app(DoctorAvailabilityService::class)->scheduledSlots(new AvailabilityQuery($doctor,Carbon::parse($date),$site,null,2));
        $matches=$slots->filter(fn($s)=>$s->startsAt($time) && $s->siteId()===$site);
        if($matches->isEmpty() || $matches->map(fn($s)=>$s->range()->minutes())->unique()->count()!==1) {
            throw new \App\Exceptions\Scheduling\AppointmentSlotUnavailableException('Selecciona dos intervalos consecutivos del mismo horario médico y sede.');
        }
        $minutes=$matches->first()->range()->minutes();
        if($minutes>255) throw ValidationException::withMessages(['cita_doble'=>'La duración excede el límite del registro.']);
        return ['minutes'=>$minutes,'site_id'=>$site];
    }
}
