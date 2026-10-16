<?php

namespace App\Http\Controllers\admin\master\additionalRate;

use App\Http\Controllers\Controller;
use App\Models\AdditionalRate;
use App\Services\Catalog\CatalogManagement;
use Illuminate\Http\Request;
class AdditionalRateController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    public function index()
    {
        return view('admin.master.additional-rate.index', ['additionalRates' => AdditionalRate::orderBy('nombre')->get()]);
    }
    private function data(Request $r, bool $edit): array
    {
        $d = $r->validate([$edit ? 'nombre_edit_tarifa' : 'nombre_tarifa' => 'required|string|max:255', 'tipo_tarifa' => 'required|in:MONTO_FIJO,PORCENTAJE', 'saldo_tarifa' => 'required|numeric|min:0|max:99999999.99|regex:/\A[0-9]+(?:\.[0-9]{1,2})?\z/', 'fecha_inicio' => 'nullable|date_format:Y-m-d', 'fecha_fin' => 'nullable|date_format:Y-m-d' . ($r->filled('fecha_inicio') ? '|after_or_equal:fecha_inicio' : ''), 'estado' => 'sometimes|in:ACTIVO,INACTIVO', 'catalog_version' => ($edit ? 'required' : 'sometimes') . '|integer|min:0']);
        return ['nombre' => $d[$edit ? 'nombre_edit_tarifa' : 'nombre_tarifa'], 'tipo_tarifa' => $d['tipo_tarifa'], 'tarifa' => $d['saldo_tarifa'], 'fecha_inicio' => $d['fecha_inicio'] ?? null, 'fecha_fin' => $d['fecha_fin'] ?? null, 'estado' => $d['estado'] ?? 'ACTIVO', 'catalog_version' => $d['catalog_version'] ?? 0];
    }
    public function store(Request $r, CatalogManagement $manager)
    {
        $manager->rate($this->data($r, false), $r->user());
        return response()->json(['code' => 1, 'msg' => 'Recargo guardado. Este importe se suma al precio base.']);
    }
    public function update(Request $r, CatalogManagement $manager)
    {
        $r->validate(['additional_rate_id_edit' => 'required|integer|exists:additional_rates,id']);
        $manager->rate($this->data($r, true), $r->user(), (int) $r->additional_rate_id_edit);
        return response()->json(['code' => 1, 'msg' => 'Recargo actualizado sin modificar citas existentes.']);
    }
    public function delete(Request $r, CatalogManagement $manager)
    {
        $d = $r->validate(['id' => 'required|integer|exists:additional_rates,id', 'catalog_version' => 'required|integer|min:0']);
        $row = AdditionalRate::findOrFail($d['id']);
        $manager->rate($row->only(['nombre', 'tipo_tarifa', 'tarifa', 'fecha_inicio', 'fecha_fin']) + ['estado' => 'INACTIVO', 'catalog_version' => $d['catalog_version']], $r->user(), $row->id);
        return response()->json(['code' => 1, 'msg' => 'Recargo desactivado. Historial conservado.']);
    }
}
