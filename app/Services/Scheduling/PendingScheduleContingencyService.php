<?php
namespace App\Services\Scheduling;
use App\Models\Appointment;
use App\Models\DoctorSchedule;
use Illuminate\Support\Facades\DB;

class PendingScheduleContingencyService
{
    /** Called inside the schedule writer transaction/doctor lock, never returned as impact data. */
    public function inspect(array $former, ?int $actor): void
    {
        $pending = Appointment::where('doctor_id', $former['doctor_id'])->where('estado_agenda', 'PENDIENTE_CONFIRMACION')
            ->whereNotIn('estado_cita', \App\Support\Scheduling\AppointmentOccupancy::RELEASING_STATES)
            ->when($former['fecha_cita'], fn ($q) => $q->whereDate('fecha_cita', $former['fecha_cita']))->lockForUpdate()->get();
        foreach ($pending as $a) {
            $date = substr($a->fecha_cita, 0, 10); $time = substr($a->hora_cita, 0, 5);
            if (!$former['fecha_cita'] && (int) \Carbon\Carbon::parse($date)->dayOfWeekIso !== (int) $former['dia_semana']) { continue; }
            if ($time < substr($former['hora_inicio'], 0, 5) || $time >= substr($former['hora_fin'], 0, 5)) { continue; }
            try { app(AppointmentSlotValidator::class)->assertValid($a->doctor_id, $date, $time, \App\Support\Scheduling\AppointmentOccupancy::minutesFor($a->duracion_cita),
                $a->site_id, $a->id, true, true); continue; }
            catch (\App\Exceptions\Scheduling\AppointmentSlotUnavailableException $e) { /* Retain reservation. */ }
            if (DB::table('appointment_contingencies')->where('appointment_id', $a->id)->where('status', 'ABIERTA')->exists()) { continue; }
            $event = app(AppointmentHistory::class)->record($a, 'HORARIO_AFECTA_RESERVA', $actor,
                ['motivo' => 'El horario médico cambió o desapareció. La reserva se conserva para seguimiento humano.',
                    'metadata' => ['former_schedule_id' => $former['id']]]);
            DB::table('appointment_contingencies')->insert(['appointment_id' => $a->id, 'event_id' => $event->id,
                'owner_user_id' => $a->responsible_user_id ?? $a->user_id, 'status' => 'ABIERTA', 'notified_at' => now(),
                'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
