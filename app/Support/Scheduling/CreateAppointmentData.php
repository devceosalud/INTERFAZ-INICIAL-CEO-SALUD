<?php

namespace App\Support\Scheduling;

class CreateAppointmentData
{
    public $patientId;

    public $doctorId;

    public $serviceId;

    public $siteId;

    public $date;

    public $time;

    public $duration;

    public $responsibleUserId;

    public $creatorUserId;

    public function __construct(
        int $patientId,
        int $doctorId,
        int $serviceId,
        ?int $siteId,
        string $date,
        string $time,
        int $duration,
        ?int $responsibleUserId,
        int $creatorUserId
    ) {
        $this->patientId = $patientId;
        $this->doctorId = $doctorId;
        $this->serviceId = $serviceId;
        $this->siteId = $siteId;
        $this->date = $date;
        $this->time = $time;
        $this->duration = $duration;
        $this->responsibleUserId = $responsibleUserId;
        $this->creatorUserId = $creatorUserId;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated, int $creatorUserId): self
    {
        return new self(
            (int) $validated['patient_id'],
            (int) $validated['doctor_id'],
            (int) $validated['service_id'],
            isset($validated['site_id']) ? (int) $validated['site_id'] : null,
            $validated['fecha_cita'],
            $validated['hora_cita'],
            (int) $validated['duracion_cita'],
            isset($validated['responsible_user_id']) ? (int) $validated['responsible_user_id'] : null,
            $creatorUserId
        );
    }
}
