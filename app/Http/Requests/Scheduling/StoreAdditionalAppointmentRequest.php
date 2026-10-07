<?php

namespace App\Http\Requests\Scheduling;

use App\Support\Scheduling\SchedulingCapability;

class StoreAdditionalAppointmentRequest extends StoreAgendaAppointmentRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (!$user || !$user->can(SchedulingCapability::CREATE_ADDITIONAL)) { return false; }
        if (!$user->hasRole('ADMINISTRADOR') && $user->hasAnyRole(['COMERCIAL', 'ADMISION'])) {
            return !$this->filled('responsible_user_id') || (int) $this->input('responsible_user_id') === (int) $user->id;
        }
        return !$this->filled('responsible_user_id') || $user->can(SchedulingCapability::ASSIGN_RESPONSIBLE);
    }
}
