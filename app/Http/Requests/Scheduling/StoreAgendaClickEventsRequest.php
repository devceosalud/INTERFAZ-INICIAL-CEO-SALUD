<?php

namespace App\Http\Requests\Scheduling;

use App\Support\Scheduling\AgendaClickTelemetry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgendaClickEventsRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (!$this->user()) { return false; }
        foreach ((array) $this->input('events', []) as $event) {
            if (is_array($event) && isset($event['screen']) && is_string($event['screen'])
                && isset(AgendaClickTelemetry::MODULES[$event['screen']])
                && !AgendaClickTelemetry::canRecord($this->user(), $event['screen'])) { return false; }
        }
        return true;
    }

    public function rules(): array
    {
        return [
            'events' => ['required', 'array', 'min:1', 'max:20'],
            'events.*' => ['required', 'array:event_uuid,screen,view_mode,element,x,y,viewport_width,viewport_height,layout_version,zone'],
            'events.*.event_uuid' => ['required', 'uuid', 'distinct'],
            'events.*.screen' => ['required', Rule::in(array_keys(AgendaClickTelemetry::MODULES))],
            'events.*.view_mode' => ['required', Rule::in(['dia', 'semana', 'mes', 'horarios', 'lista', 'ficha'])],
            'events.*.element' => ['required', Rule::in(array_merge(AgendaClickTelemetry::ELEMENTS, ['horarios.control', 'pacientes.control']))],
            'events.*.layout_version' => ['required', 'integer', 'in:2'],
            'events.*.zone' => ['required', Rule::in(['toolbar', 'doctors', 'mini', 'booking', 'grid', 'dialog', 'calendar', 'sidebar', 'list', 'record'])],
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
            foreach ((array) $this->input('events', []) as $index => $event) {
                if (!is_array($event)) { continue; }
                $module = is_string($event['screen'] ?? null) ? (AgendaClickTelemetry::MODULES[$event['screen']] ?? null) : null;
                if ($module && (!in_array($event['view_mode'] ?? null, $module['views'], true)
                    || (isset($event['zone']) && !in_array($event['zone'], $module['zones'], true))
                    || (isset($event['zone']) && ($event['layout_version'] ?? null) != 2)
                    || !is_string($event['element'] ?? null) || !str_starts_with($event['element'], $event['screen'].'.')
                    || ($event['screen'] !== 'agenda' && ($event['layout_version'] ?? null) != 2))) {
                    $validator->errors()->add('events.'.$index, 'Módulo, vista y geometría incompatibles.');
                }
            }
        });
    }
}
