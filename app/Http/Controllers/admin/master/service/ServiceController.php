<?php

namespace App\Http\Controllers\admin\master\service;

use App\Http\Controllers\Controller;
use App\Models\{Service, Specialty};
use App\Services\Catalog\{CatalogInput, CatalogManagement, ServiceCatalogDefinition};
use Illuminate\Http\Request;
class ServiceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    public function index()
    {
        return view('admin.master.service.index', ['services' => Service::with(['specialty', 'sharedSpecialties', 'doctorServices.doctor'])->orderBy('nombre')->get(), 'specialties' => Specialty::where('estado', 'ACTIVO')->get(), 'catalogTypes' => ServiceCatalogDefinition::TYPES, 'canonicalServices' => Service::where('service_type', 'CONSULTATION')->where('estado', 'ACTIVO')->get()]);
    }
    public function store(Request $r, CatalogManagement $manager)
    {
        $s = $manager->service(CatalogInput::service($r), $r->user());
        return response()->json(['code' => 1, 'msg' => 'Servicio guardado sin modificar citas existentes.', 'service' => $s]);
    }
    public function update(Request $r, CatalogManagement $manager)
    {
        $r->validate(['service_id_edit' => 'required|integer|exists:services,id']);
        $s = $manager->service(CatalogInput::service($r, true), $r->user(), (int) $r->service_id_edit);
        return response()->json(['code' => 1, 'msg' => 'Servicio actualizado. Los precios históricos se conservaron.', 'service' => $s]);
    }
    public function delete(Request $r, CatalogManagement $manager)
    {
        $d = $r->validate(['id' => 'required|integer|exists:services,id', 'catalog_version' => 'required|integer|min:0']);
        $s = Service::findOrFail($d['id']);
        $manager->service($s->only(['nombre', 'specialty_id', 'service_type', 'pricing_policy', 'institutional_price', 'price_valid_from', 'price_valid_until', 'pricing_notes', 'canonical_service_id']) + ['estado' => 'INACTIVO', 'catalog_version' => $d['catalog_version']], $r->user(), $s->id);
        return response()->json(['code' => 1, 'msg' => 'Servicio desactivado. Historial conservado.']);
    }
}
