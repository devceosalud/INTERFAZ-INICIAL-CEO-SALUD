<?php
namespace App\Http\Requests\Scheduling;

use Illuminate\Support\Str;

/** Compatibility entrypoint: an unpaid regular request is always a private reservation. */
class StoreRegularAgendaAppointmentRequest extends OperationalRegistrationRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $defaults = ['mode' => 'RESERVE', 'booking_type' => 'REGULAR'];
        foreach ($defaults as $field => $value) {
            // Invalid/empty supplied values must fail validation, never fall back silently.
            if (!$this->exists($field)) { $this->merge([$field => $value]); }
        }
        // Old unpaid callers may omit UUID. Money or explicit confirmation must carry a reusable client key.
        if (!$this->exists('request_key') && $this->input('mode') === 'RESERVE' && (float) $this->input('payment.amount', 0) === 0.0) {
            $this->merge(['request_key' => (string) Str::uuid()]);
        }
    }
    public function messages(): array
    {
        return parent::messages() + ['request_key.required' => 'Incluye una UUID request_key para registrar dinero o confirmar una cita. Reutilízala al reintentar la misma operación.'];
    }
    public function rules(): array
    {
        return array_replace(parent::rules(), ['booking_type' => 'required|in:REGULAR']);
    }
}
