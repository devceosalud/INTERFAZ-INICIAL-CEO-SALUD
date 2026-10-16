<?php
namespace App\Http\Requests\Scheduling;

use App\Support\Scheduling\SchedulingCapability;

/** Existing special routes keep their URLs/response contract and use the common financial request. */
class StoreAgendaAppointmentRequest extends OperationalRegistrationRequest
{
    protected function bookingType(): string { return 'FUERA_HORARIO'; }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        if (!$this->exists('mode')) $this->merge(['mode'=>'CONFIRM']);
        if (!$this->exists('booking_type')) $this->merge(['booking_type'=>$this->bookingType()]);
    }

    public function authorize(): bool
    {
        if (!parent::authorize()) return false;
        $user = $this->user();
        if (!$user->hasRole('ADMINISTRADOR') && $user->hasAnyRole(['COMERCIAL','ADMISION'])) {
            return !$this->filled('responsible_user_id') || (int)$this->input('responsible_user_id') === (int)$user->id;
        }
        return !$this->filled('responsible_user_id') || $user->can(SchedulingCapability::ASSIGN_RESPONSIBLE);
    }

    public function rules(): array
    {
        return array_replace(parent::rules(),['mode'=>'required|in:CONFIRM','booking_type'=>'required|in:'.$this->bookingType()]);
    }

    public function messages(): array
    {
        return parent::messages()+['request_key.required'=>'Incluye request_key UUID y reutilízala al reintentar. Confirmar requiere un pago real positivo o exoneración autorizada.'];
    }
}
