<?php

namespace App\Http\Requests\Scheduling;

use App\Support\Scheduling\SchedulingCapability;

class StoreAdditionalAppointmentRequest extends StoreAgendaAppointmentRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && $user->can(SchedulingCapability::CREATE_ADDITIONAL)
            && (!$this->filled('responsible_user_id') || $user->can(SchedulingCapability::ASSIGN_RESPONSIBLE));
    }
}
