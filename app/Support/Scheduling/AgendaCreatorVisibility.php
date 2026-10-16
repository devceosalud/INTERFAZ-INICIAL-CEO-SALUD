<?php

namespace App\Support\Scheduling;

use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\User;

/** Response projection only; stored attribution and existing audit permissions remain intact. */
final class AgendaCreatorVisibility
{
    public static function allows(User $actor): bool
    {
        return $actor->hasRole('ADMINISTRADOR');
    }

    public static function eventActor(Appointment $appointment, AppointmentEvent $event, bool $allowed): ?string
    {
        if (! $allowed && ((int) $event->actor_user_id === (int) $appointment->user_id
            || in_array($event->event_type, ['RESERVA_CREADA', 'CITA_CREADA', 'PRECIO_REGISTRADO', 'REGISTRO_OPERATIVO'], true))) {
            return null;
        }
        return $event->actor?->name;
    }
}
