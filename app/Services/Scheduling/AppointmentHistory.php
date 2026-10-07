<?php
namespace App\Services\Scheduling;
use App\Models\Appointment;
use App\Models\AppointmentEvent;

class AppointmentHistory
{
    public function record(Appointment $a, string $type, ?int $actor, array $attributes = []): AppointmentEvent
    {
        return AppointmentEvent::create(array_merge($attributes, ['appointment_id' => $a->id,
            'event_type' => $type, 'actor_user_id' => $actor, 'occurred_at' => now()]));
    }
}
