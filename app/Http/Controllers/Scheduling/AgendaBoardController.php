<?php

namespace App\Http\Controllers\Scheduling;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Services\Catalog\ActiveDoctorServiceResolver;
use Illuminate\Validation\ValidationException;
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
use Illuminate\Http\Response;

/**
 * The agenda board page. It ships filters and the legend only; every interval is fetched by
 * the feed for the window actually being looked at, so opening the page never loads a year.
 */
class AgendaBoardController extends Controller
{
    public function __invoke(): Response
    {

        $resolver = app(ActiveDoctorServiceResolver::class);
        $doctorServices = $resolver->query()
            ->with('service')
            ->orderBy('doctor_id')
            ->orderBy('service_id')
            ->get(['id', 'doctor_id', 'service_id', 'precio_primera_consulta','precio_reconsulta','estado'])
            ->groupBy('doctor_id')
            ->map(fn ($rows) => $rows->groupBy('service_id')->map(function ($assignments) use ($resolver) {
                $doctorService = $assignments->first();
                try {
                    $doctorService = $resolver->uniqueAssignment($assignments);
                    // This list is configuration, not a quote. The selected appointment date is validated by quote().
                    $price=app(\App\Services\Catalog\ServicePriceResolver::class)->resolve($doctorService,now(config('scheduling.operational_timezone'))->toDateString(),false,false,false);
                    $available = true;
                } catch (ValidationException $exception) {
                    $available = false;
                    $price=null;
                }

                return [
                    'service_id' => (int) $doctorService->service_id,
                    'nombre' => $doctorService->service ? $doctorService->service->nombre : 'Servicio sin nombre',
                    'precio' => $available ? (float) $price['price'] : null,
                    'price_source'=>$price['source']??null,'service_type'=>$doctorService->service?->service_type,
                    'reevaluation_services'=>\App\Models\Service::where('canonical_service_id',$doctorService->service_id)->where('service_type','REEVALUATION')->where('estado','ACTIVO')->get(['id','nombre'])->toArray(),
                    'disponible' => $available,
                    'motivo' => $assignments->count() > 1
                        ? 'Asignación duplicada: requiere revisión del catálogo'
                        : ($available ? null : ($exception->errors()['service_id'][0]??'Sin precio normal válido')),
                ];
            })->values()->all())
            ->all();

        return response()->view('scheduling.agenda.index', [
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
            'additionalRates' => \App\Models\AdditionalRate::where('estado', 'ACTIVO')->orderBy('nombre')->get(['id', 'nombre']),
            'commercialUsers' => User::query()
                ->whereHas('roles', fn ($query) => $query->where('name', 'COMERCIAL'))
                ->orderBy('name')
                ->get(['id', 'name']),
            'canWritePatients' => PatientWriteAccess::allows(auth()->user()),
            'canCreateAppointments' => auth()->user()->can(SchedulingCapability::CREATE),
            'canRescheduleAppointments' => auth()->user()->can(SchedulingCapability::RESCHEDULE),
            'canCreateAdditional' => auth()->user()->can(SchedulingCapability::CREATE_ADDITIONAL),
            'canAssignResponsible' => auth()->user()->hasRole('ADMINISTRADOR') && auth()->user()->can(SchedulingCapability::ASSIGN_RESPONSIBLE),
            'autoOwner' => !auth()->user()->hasRole('ADMINISTRADOR') && auth()->user()->hasAnyRole(['COMERCIAL', 'ADMISION']),
            'canSubmitPayment' => auth()->user()->can(SchedulingCapability::SUBMIT_PAYMENT),
            'canAuthorize' => auth()->user()->can(SchedulingCapability::OVERRIDE_DOWN_PAYMENT) || auth()->user()->can(SchedulingCapability::APPROVE_ZERO_COST),
            'canWaive' => auth()->user()->can(SchedulingCapability::APPROVE_ZERO_COST),
            'canWithdraw' => auth()->user()->can(SchedulingCapability::WITHDRAW),
            'canCancel' => auth()->user()->can(SchedulingCapability::CANCEL),
            'canNoShow' => auth()->user()->can(SchedulingCapability::MARK_NO_SHOW),
            'canWorkflow' => auth()->user()->can(SchedulingCapability::WITHDRAW) || auth()->user()->can(SchedulingCapability::VIEW_AUDIT)
                || auth()->user()->can(SchedulingCapability::CANCEL) || auth()->user()->can(SchedulingCapability::MARK_NO_SHOW)
                || (auth()->user()->can(SchedulingCapability::CREATE) && auth()->user()->can(SchedulingCapability::RESCHEDULE)),
            'canAudit' => auth()->user()->can(SchedulingCapability::VIEW_AUDIT),
            'doctorServices' => $doctorServices,
            'legend' => AgendaLegend::compactForAgenda(),
            'views' => AgendaRange::views(),
            'today' => Carbon::today(config('scheduling.operational_timezone','America/Lima'))->toDateString(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
