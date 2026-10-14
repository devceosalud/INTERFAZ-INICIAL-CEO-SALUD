<?php
namespace App\Services\Scheduling;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Exceptions\Scheduling\AppointmentSlotUnavailableException;
use App\Support\Scheduling\AppointmentAgendaLifecycle as Lifecycle;

/** Read-only projection is advisory. Financial writes repeat it while holding the doctor lock. */
final class RegularReservationAvailability
{
    public const BLOCKED = 'El horario de esta reserva regular no está disponible. No se aceptan nuevos adelantos; reprograma o solicita revisión.';

    public function assertAvailable(Appointment $appointment): void
    {
        if ($appointment->estado_agenda !== Lifecycle::PENDING_CONFIRMATION) return;
        if (!in_array($appointment->tipo_agendamiento,[null,Lifecycle::REGULAR],true)
            || !Doctor::whereKey($appointment->doctor_id)->where('estado','ACTIVO')->exists()) {
            throw new AppointmentSlotUnavailableException(self::BLOCKED);
        }
        try {
            app(AppointmentSlotValidator::class)->assertValid((int)$appointment->doctor_id,
                substr($appointment->fecha_cita,0,10),substr($appointment->hora_cita,0,5),
                (int)$appointment->duracion_cita,$appointment->site_id ? (int)$appointment->site_id : null,
                (int)$appointment->id,false,true);
        } catch (AppointmentSlotUnavailableException $e) {
            throw new AppointmentSlotUnavailableException(self::BLOCKED);
        }
    }

    public function describe(Appointment $appointment): array
    {
        if ($appointment->estado_agenda !== Lifecycle::PENDING_CONFIRMATION) {
            return ['reservation_slot_available'=>null,'reservation_block_reason'=>null];
        }
        try {
            $this->assertAvailable($appointment);
            return ['reservation_slot_available'=>true,'reservation_block_reason'=>null];
        } catch (AppointmentSlotUnavailableException $e) {
            return ['reservation_slot_available'=>false,'reservation_block_reason'=>self::BLOCKED];
        }
    }
}
