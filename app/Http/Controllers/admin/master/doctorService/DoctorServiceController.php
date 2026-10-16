<?php

namespace App\Http\Controllers\admin\master\doctorService;

use App\Http\Controllers\Controller;
use App\Models\{Doctor, DoctorService, Service};
use App\Services\Catalog\{CatalogInput, CatalogManagement};
use Illuminate\Http\Request;
class DoctorServiceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    public function index(Request $request)
    {
        $filter = $request->validate(['doctor_id' => 'nullable|integer|exists:doctors,id']);
        return view('admin.master.doctor-service.index', ['selectedDoctorId' => $filter['doctor_id'] ?? null, 'doctors' => Doctor::orderBy('nombre')->get(), 'services' => Service::with('sharedSpecialties')->where('estado', 'ACTIVO')->where(fn($q) => $q->whereNull('service_type')->orWhere('service_type', '<>', 'REEVALUATION'))->get(), 'doctorServices' => DoctorService::with(['doctor', 'service'])->when($filter['doctor_id'] ?? null, fn($q, $id) => $q->where('doctor_id', $id))->get(), 'count' => 31]);
    }
    public function show(int $assignmentId)
    {
        return response()->json(['assignment' => DoctorService::with(['service.sharedSpecialties', 'doctor'])->findOrFail($assignmentId)]);
    }
    public function store(Request $r, CatalogManagement $manager)
    {
        $s = $manager->assignment(CatalogInput::assignment($r), $r->user());
        return response()->json(['code' => 1, 'msg' => 'Asignación guardada.', 'assignment' => $s]);
    }
    public function update(Request $r, CatalogManagement $manager)
    {
        $r->validate(['doctor_service_id' => 'required|integer|exists:doctor_services,id']);
        $s = $manager->assignment(CatalogInput::assignment($r, true), $r->user(), (int) $r->doctor_service_id);
        return response()->json(['code' => 1, 'msg' => 'Precios actualizados. No se recalcularon citas existentes.', 'assignment' => $s]);
    }
    public function delete(Request $r, CatalogManagement $manager)
    {
        $d = $r->validate(['id' => 'required|integer|exists:doctor_services,id', 'catalog_version' => 'required|integer|min:0']);
        $s = DoctorService::findOrFail($d['id']);
        $manager->assignment(['doctor_id' => $s->doctor_id, 'service_id' => $s->service_id, 'estado' => 'INACTIVO', 'catalog_version' => $d['catalog_version']], $r->user(), $s->id);
        return response()->json(['code' => 1, 'msg' => 'Asignación desactivada. Historial conservado.']);
    }
}
