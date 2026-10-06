<?php

namespace App\Http\Requests\Scheduling;

use App\Support\Scheduling\AgendaClickTelemetry;
use App\Support\Scheduling\SchedulingCapability;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgendaClickEventsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can(SchedulingCapability::VIEW);
    }

    public function rules(): array
    {
        return [
            'events' => ['required', 'array', 'min:1', 'max:20'],
            'events.*' => ['required', 'array:event_uuid,screen,view_mode,element,x,y,viewport_width,viewport_height'],
            'events.*.event_uuid' => ['required', 'uuid', 'distinct'],
            'events.*.screen' => ['required', Rule::in(['agenda'])],
            'events.*.view_mode' => ['required', Rule::in(['dia', 'semana', 'mes'])],
            'events.*.element' => ['required', Rule::in(AgendaClickTelemetry::ELEMENTS)],
            'events.*.x' => ['required', 'numeric', 'between:0,1'],
            'events.*.y' => ['required', 'numeric', 'between:0,1'],
            'events.*.viewport_width' => ['required', 'integer', 'between:240,10000'],
            'events.*.viewport_height' => ['required', 'integer', 'between:240,10000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (array_diff(array_keys($this->all()), ['events']) !== []) {
                $validator->errors()->add('events', 'Solo se admite telemetría de UI autorizada.');
            }
        });
    }
}
