<?php
namespace App\Http\Requests\Scheduling;

use App\Support\Scheduling\SchedulingCapability;

class StoreAdditionalAppointmentRequest extends StoreAgendaAppointmentRequest
{
    protected function bookingType(): string { return 'ADICIONAL'; }
    public function authorize(): bool
    {
        return parent::authorize() && $this->user()->can(SchedulingCapability::CREATE_ADDITIONAL);
    }
}
