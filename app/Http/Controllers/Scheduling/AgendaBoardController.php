<?php

namespace App\Http\Controllers\Scheduling;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\DoctorService;
use App\Models\Channel;
use App\Models\InteractionMedium;
use App\Models\Site;
use App\Models\Specialty;
use App\Models\User;
use App\Http\Controllers\Patients\OperationalPatientController;
use App\Support\Patients\DemoChannelCatalog;
use App\Support\Patients\PatientPhone;
use App\Support\Patients\PatientWriteAccess;
use App\Support\Scheduling\AgendaLegend;
use App\Support\Scheduling\AgendaRange;
use App\Support\Scheduling\SchedulingCapability;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;

/**
 * The agenda board page. It ships filters and the legend only; every interval is fetched by
 * the feed for the window actually being looked at, so opening the page never loads a year.
 */
class AgendaBoardController extends Controller
{
    public function __invoke(): View
    {
        DemoChannelCatalog::ensure();

        $doctorServices = DoctorService::query()
            ->with('service:id,nombre')
            ->where('estado', 'ACTIVO')
            ->whereHas('service', fn ($query) => $query->where('estado', 'ACTIVO'))
            ->orderBy('doctor_id')
            ->orderBy('service_id')
            ->get(['id', 'doctor_id', 'service_id', 'precio_primera_consulta'])
            ->groupBy('doctor_id')
            ->map(fn ($rows) => $rows->map(fn (DoctorService $doctorService) => [
                'service_id' => (int) $doctorService->service_id,
                'nombre' => $doctorService->service ? $doctorService->service->nombre : 'Servicio sin nombre',
                'precio' => $doctorService->precio_primera_consulta !== null
                    ? (float) $doctorService->precio_primera_consulta
                    : null,
            ])->values()->all())
            ->all();

        return view('scheduling.agenda.index', [
            'sites' => Site::activo()->orderBy('nombre')->get(['id', 'nombre']),
            'specialties' => Specialty::where('estado', 'ACTIVO')->orderBy('nombre')->get(['id', 'nombre']),
            'doctors' => Doctor::where('estado', 'ACTIVO')
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'specialty_id']),
            'channels' => Channel::where('estado', 'ACTIVO')->orderBy('nombre')->get(['id', 'nombre']),
            'civilStatuses' => OperationalPatientController::CIVIL_STATUSES,
            'relationships' => OperationalPatientController::RELATIONSHIPS,
            'phonePrefixes' => PatientPhone::PREFIXES,
            'documentTypes' => OperationalPatientController::DOCUMENT_TYPES,
            'interactionMedia' => InteractionMedium::where('estado', 'ACTIVO')->orderBy('nombre')->get(['id', 'nombre']),
            'commercialUsers' => User::query()
                ->whereHas('roles', fn ($query) => $query->where('name', 'COMERCIAL'))
                ->orderBy('name')
                ->get(['id', 'name']),
            'canWritePatients' => PatientWriteAccess::allows(auth()->user()),
            'canCreateAppointments' => auth()->user()->can(SchedulingCapability::CREATE),
            'canAssignResponsible' => auth()->user()->can(SchedulingCapability::ASSIGN_RESPONSIBLE),
            'doctorServices' => $doctorServices,
            'legend' => AgendaLegend::ordered(),
            'views' => AgendaRange::views(),
            'today' => Carbon::today()->toDateString(),
        ]);
    }
}
