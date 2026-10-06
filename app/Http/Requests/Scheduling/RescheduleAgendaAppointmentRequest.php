<?php

namespace App\Http\Requests\Scheduling;

use App\Support\Scheduling\SchedulingCapability;
use Illuminate\Foundation\Http\FormRequest;

class RescheduleAgendaAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can(SchedulingCapability::RESCHEDULE);
    }

    public function rules(): array
    {
        return [
            'fecha_cita' => ['required', 'date_format:Y-m-d'], 'hora_cita' => ['required', 'date_format:H:i'],
            'expected_fecha_cita' => ['required', 'date_format:Y-m-d'],
            'expected_hora_cita' => ['required', 'date_format:H:i'],
            'confirmed_booking_type' => ['nullable', 'in:REGULAR,FUERA_HORARIO'],
        ];
    }
}
