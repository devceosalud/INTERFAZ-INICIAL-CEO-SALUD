<?php

namespace App\Http\Requests\Scheduling;

use App\Support\Scheduling\SchedulingCapability;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgendaAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->can(SchedulingCapability::CREATE)) {
            return false;
        }

        return ! $this->filled('responsible_user_id')
            || $user->can(SchedulingCapability::ASSIGN_RESPONSIBLE);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'patient_id' => [
                'required',
                'integer',
                Rule::exists('patients', 'id')->where('estado', 'ACTIVO'),
            ],
            'doctor_id' => [
                'required',
                'integer',
                Rule::exists('doctors', 'id')->where('estado', 'ACTIVO'),
            ],
            'service_id' => [
                'required',
                'integer',
                Rule::exists('services', 'id')->where('estado', 'ACTIVO'),
            ],
            'site_id' => [
                'nullable',
                'integer',
                Rule::exists('sites', 'id')->where('estado', 'ACTIVO'),
            ],
            'fecha_cita' => ['required', 'date_format:Y-m-d'],
            'hora_cita' => ['required', 'date_format:H:i'],
            'duracion_cita' => ['required', 'integer', 'min:1', 'max:480'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
