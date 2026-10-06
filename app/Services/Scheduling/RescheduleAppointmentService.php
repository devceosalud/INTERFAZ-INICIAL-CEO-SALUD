<?php

namespace App\Services\Scheduling;

use App\Exceptions\Scheduling\AppointmentConfigurationException;
use App\Exceptions\Scheduling\AppointmentSlotUnavailableException;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Support\Scheduling\AppointmentAgendaLifecycle;
use Illuminate\Support\Facades\DB;

class RescheduleAppointmentService
{
    public function __construct(private AppointmentSlotValidator $slots)
    {
    }

    public function reschedule(int $id, int $actorId, string $date, string $time, string $expectedDate, string $expectedTime): Appointment
    {
        // Hidden and absent IDs take the same path, before inspecting target fields.
        $original = Appointment::query()->visibleToAgendaUser($actorId)->find($id);
        abort_if($original === null, 404);

        return DB::transaction(function () use ($original, $id, $actorId, $date, $time, $expectedDate, $expectedTime) {
            $doctor = Doctor::query()->lockForUpdate()->find($original->doctor_id);
            $appointment = Appointment::query()->visibleToAgendaUser($actorId)->lockForUpdate()->find($id);
            abort_if($appointment === null, 404);
            if (substr((string) $appointment->fecha_cita, 0, 10) !== $expectedDate
                || substr((string) $appointment->hora_cita, 0, 5) !== $expectedTime) {
                throw new AppointmentSlotUnavailableException('La cita cambió de fecha u hora. Actualiza la Agenda antes de reprogramar.');
            }
            if (!$doctor || $doctor->estado !== 'ACTIVO' || (int) $appointment->doctor_id !== (int) $doctor->id) {
                throw new AppointmentConfigurationException('El médico no está disponible para reprogramación.');
            }
            if (!in_array($appointment->estado_cita, ['PROGRAMADO', 'CONFIRMADO'], true) || (int) $appointment->duracion_cita < 1) {
                throw new AppointmentConfigurationException('Solo se reprograman citas programadas o confirmadas con duración definida.');
            }
            $this->slots->assertValid((int) $doctor->id, $date, $time, (int) $appointment->duracion_cita,
                $appointment->site_id ? (int) $appointment->site_id : null, $id,
                $appointment->estado_agenda === AppointmentAgendaLifecycle::CONFIRMED
                    && $appointment->tipo_agendamiento === AppointmentAgendaLifecycle::ADDITIONAL, true);
            $appointment->update(['fecha_cita' => $date, 'hora_cita' => $time, 'updated_by_user_id' => $actorId]);

            return $appointment;
        }, 3);
    }
}
