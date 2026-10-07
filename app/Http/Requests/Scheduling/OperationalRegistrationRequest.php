<?php
namespace App\Http\Requests\Scheduling;

use App\Services\Billing\VoucherPaymentRecorder;
use App\Support\Scheduling\SchedulingCapability as Capability;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OperationalRegistrationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('payload')) {
            $payload = json_decode((string) $this->input('payload'), true);
            if (is_array($payload)) { $this->merge($payload); }
        }
    }
    public function authorize(): bool { return $this->user()?->can(Capability::CREATE) ?? false; }
    public function rules(): array
    {
        return [
            'request_key' => 'required|uuid', 'mode' => 'required|in:RESERVE,CONFIRM',
            'booking_type' => 'required|in:REGULAR,ADICIONAL,FUERA_HORARIO',
            'patient_id' => ['nullable', 'integer', 'required_without:patient', Rule::exists('patients', 'id')->where('estado', 'ACTIVO')],
            'patient' => 'nullable|array', 'patient_capture' => 'nullable|array:telefono,telefono_secundario,channel_id,interaction_medium_id',
            'patient_capture.telefono' => 'nullable|string|max:32|regex:/\A\+?[0-9][0-9 -]{5,30}\z/',
            'patient_capture.telefono_secundario' => 'nullable|string|max:32|regex:/\A\+?[0-9][0-9 -]{5,30}\z/',
            'patient_capture.channel_id' => ['nullable', 'integer', Rule::exists('channels', 'id')->where('estado', 'ACTIVO')],
            'patient_capture.interaction_medium_id' => ['nullable', 'integer', Rule::exists('interaction_media', 'id')->where('estado', 'ACTIVO')],
            'doctor_id' => ['required', 'integer', Rule::exists('doctors', 'id')->where('estado', 'ACTIVO')],
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('estado', 'ACTIVO')],
            'site_id' => ['nullable', 'integer', Rule::exists('sites', 'id')->where('estado', 'ACTIVO')],
            'fecha_cita' => 'required|date_format:Y-m-d', 'hora_cita' => 'required|date_format:H:i',
            'duracion_cita' => 'required|integer|between:1,255', 'responsible_user_id' => 'nullable|integer|exists:users,id',
            'motivo_consulta' => 'nullable|string|max:2000', 'observaciones' => 'nullable|string|max:2000',
            'es_exonerado' => 'sometimes|boolean', 'autorizado_por' => 'nullable|string|max:255',
            'payment' => 'nullable|array:amount,method,operation,origin',
            'payment.amount' => 'nullable|numeric|min:0|max:99999999|regex:/\A[0-9]+(?:\.[0-9]{1,2})?\z/',
            'payment.method' => ['nullable', Rule::in(VoucherPaymentRecorder::METHODS)],
            'payment.operation' => 'nullable|string|max:120', 'payment.origin' => 'nullable|string|max:120',
            'proof' => ['nullable', 'file', new \App\Rules\PrivateAppointmentFile(), 'max:8192'],
            'links' => 'nullable|array|max:10', 'links.*' => 'array:label,url',
            'links.*.label' => 'required|string|max:120', 'links.*.url' => 'required|url|max:2048|starts_with:https://',
        ];
    }
    public function messages(): array { return ['service_id.required' => 'Selecciona un servicio para agendar la cita.']; }
}
