<?php

namespace App\Services\Catalog;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
final class CatalogInput
{
    public const MONEY = 'nullable|numeric|gt:0|max:99999999.99|regex:/\A[0-9]+(?:\.[0-9]{1,2})?\z/';
    public static function service(Request $r, bool $edit = false): array
    {
        if ($r->boolean('shared_specialties_present') && !$r->has('shared_specialty_ids')) {
            $r->merge(['shared_specialty_ids' => []]);
        }
        if ($edit) {
            $r->merge(['nombre' => $r->input('nombre_edit', $r->input('nombre')), 'specialty_id' => $r->input('specialty_id_edit', $r->input('specialty_id'))]);
        }
        return $r->validate(['nombre' => 'required|string|max:255', 'specialty_id' => 'required|integer|exists:specialties,id', 'service_type' => [$edit ? 'nullable' : 'required', Rule::in(array_keys(ServiceCatalogDefinition::TYPES))], 'pricing_policy' => ($edit ? 'nullable' : 'required') . '|in:INSTITUTIONAL,PROFESSIONAL', 'institutional_price' => self::MONEY, 'canonical_service_id' => 'nullable|integer|exists:services,id', 'price_valid_from' => 'nullable|date_format:Y-m-d', 'shared_specialty_ids' => 'sometimes|array', 'shared_specialty_ids.*' => 'required|integer|distinct|exists:specialties,id', 'specialty_authorization_reason' => 'nullable|string|max:500', 'price_valid_until' => 'nullable|date_format:Y-m-d' . ($r->filled('price_valid_from') ? '|after_or_equal:price_valid_from' : ''), 'pricing_notes' => 'nullable|string|max:500', 'estado' => 'sometimes|in:ACTIVO,INACTIVO', 'catalog_version' => ($edit ? 'required' : 'sometimes') . '|integer|min:0']);
    }
    public static function assignment(Request $r, bool $edit = false): array
    {
        $d = $r->validate(['doctor_id' => 'required|integer|exists:doctors,id', 'service_id' => 'required|integer|exists:services,id', 'precio_estandar' => self::MONEY, 'reconsulta' => self::MONEY, 'dias' => 'nullable|integer|min:1|max:365', 'specialty_exception_authorized' => 'sometimes|boolean', 'specialty_exception_reason' => 'nullable|string|max:500', 'estado' => 'sometimes|in:ACTIVO,INACTIVO', 'catalog_version' => ($edit ? 'required' : 'sometimes') . '|integer|min:0']);
        if (!$edit || array_key_exists('precio_estandar', $d)) {
            $d['precio_primera_consulta'] = $d['precio_estandar'] ?? null;
        }
        if (!$edit || array_key_exists('reconsulta', $d)) {
            $d['precio_reconsulta'] = $d['reconsulta'] ?? null;
        }
        if (!$edit || array_key_exists('dias', $d)) {
            $d['dias_reconsulta'] = $d['dias'] ?? 15;
        }
        return $d;
    }
}
