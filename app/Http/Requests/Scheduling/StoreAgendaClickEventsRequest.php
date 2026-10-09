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
            'events.*' => ['required', 'array:event_uuid,screen,view_mode,element,x,y,viewport_width,viewport_height,layout_version,zone,geometry'],
            'events.*.event_uuid' => ['required', 'uuid', 'distinct'],
            'events.*.screen' => ['required', Rule::in(array_keys(AgendaClickTelemetry::MODULES))],
            'events.*.view_mode' => ['required', Rule::in(['dia', 'semana', 'mes', 'horarios', 'lista', 'ficha'])],
            'events.*.element' => ['required', Rule::in(array_merge(AgendaClickTelemetry::ELEMENTS, ['horarios.control', 'pacientes.control']))],
            'events.*.layout_version' => ['required', 'integer', 'in:2,3'],
            'events.*.zone' => ['required', Rule::in(['toolbar', 'doctors', 'mini', 'booking', 'grid', 'dialog', 'calendar', 'sidebar', 'list', 'record'])],
            'events.*.x' => ['required', 'numeric', 'between:0,1'],
            'events.*.y' => ['required', 'numeric', 'between:0,1'],
            'events.*.viewport_width' => ['required', 'integer', 'between:240,10000'],
            'events.*.viewport_height' => ['required', 'integer', 'between:240,10000'],
            'events.*.geometry' => ['required_if:events.*.layout_version,3', 'array:left,top,width,height,operations_scroll,doctors_scroll,grid_scroll,page_scroll,expanded,selected,revision,audit_link'],
            'events.*.geometry.left' => 'required_with:events.*.geometry|numeric|between:-10000,10000',
            'events.*.geometry.top' => 'required_with:events.*.geometry|numeric|between:-100000,100000',
            'events.*.geometry.width' => 'required_with:events.*.geometry|numeric|between:1,10000',
            'events.*.geometry.height' => 'required_with:events.*.geometry|numeric|between:1,100000',
            'events.*.geometry.operations_scroll' => 'required_with:events.*.geometry|numeric|between:0,100000',
            'events.*.geometry.doctors_scroll' => 'required_with:events.*.geometry|numeric|between:0,100000',
            'events.*.geometry.grid_scroll' => 'required_with:events.*.geometry|numeric|between:0,100000',
            'events.*.geometry.page_scroll' => 'required_with:events.*.geometry|numeric|between:0,100000',
            'events.*.geometry.expanded' => 'sometimes|array|max:5',
            'events.*.geometry.expanded.*' => 'required|string|distinct|in:capture,notes,payment,documents,workflow',
            'events.*.geometry.selected' => 'required_with:events.*.geometry|boolean',
            'events.*.geometry.audit_link' => 'sometimes|boolean',
            'events.*.geometry.revision' => 'required_with:events.*.geometry|integer|in:1',
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
                    || (isset($event['zone']) && !in_array((int) ($event['layout_version'] ?? 0), [2,3], true))
                    || !is_string($event['element'] ?? null) || !str_starts_with($event['element'], $event['screen'].'.')
                    || ($event['screen'] !== 'agenda' && ($event['layout_version'] ?? null) != 2))) {
                    $validator->errors()->add('events.'.$index, 'Módulo, vista y geometría incompatibles.');
                }
                if ((($event['layout_version'] ?? null) == 3 && ($event['screen'] ?? null) !== 'agenda')
                    || (($event['layout_version'] ?? null) == 2 && isset($event['geometry']))) {
                    $validator->errors()->add('events.'.$index, 'La geometría capturada v3 corresponde a Agenda.');
                }
                if (($event['layout_version'] ?? null) == 3 && !array_key_exists('expanded', (array) ($event['geometry'] ?? []))) {
                    $validator->errors()->add('events.'.$index.'.geometry.expanded', 'Falta el estado técnico de los paneles.');
                }
            }
        });
    }
}
