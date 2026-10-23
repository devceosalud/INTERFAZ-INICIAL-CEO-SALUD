<?php
namespace App\Http\Controllers\Scheduling;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Site;
use App\Models\Specialty;
use App\Services\Scheduling\AdmissionBoardService;
use App\Services\Scheduling\AdmissionCareService;
use App\Support\Scheduling\AdmissionStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdmissionBoardController extends Controller
{
    public function index(Request $r,AdmissionBoardService $board)
    {
        return response()->view('scheduling.admission.index',[
            'clinicalOnly'=>$clinicalOnly=\App\Support\Scheduling\AdmissionAccess::clinicalOnly($r->user()),
            'today'=>now('America/Lima')->toDateString(),'defaultSite'=>$board->defaultSite(),
            'sites'=>Site::activo()->orderBy('nombre')->get(['id','nombre']),
            'doctors'=>Doctor::when($clinicalOnly,fn($q)=>$q->whereKey($r->user()->doctor_id))->orderBy('nombre')->get(['id','nombre','specialty_id','estado']),
            'specialties'=>Specialty::orderBy('nombre')->get(['id','nombre']),
            'labels'=>AdmissionStatus::LABELS,'states'=>AdmissionStatus::STATES,'visuals'=>AdmissionBoardService::VISUAL_FILTERS,
        ])->header('Cache-Control','private, no-store')->header('Referrer-Policy','same-origin');
    }

    public function feed(Request $r, AdmissionBoardService $board)
    {
        $data = $r->validate(['fecha'=>'required|date_format:Y-m-d',
            'hasta'=>'nullable|date_format:Y-m-d|after_or_equal:fecha','tab'=>'nullable|in:citas,reevaluaciones',
            'site_id'=>['nullable','integer',Rule::exists('sites','id')->where('estado','ACTIVO')],
            'doctor_id'=>'nullable|integer|exists:doctors,id','specialty_id'=>'nullable|integer|exists:specialties,id',
            'patient'=>'nullable|string|max:100','estado'=>['nullable',Rule::in(AdmissionStatus::STATES)],
            'grupo'=>['nullable',Rule::in(array_merge(array_diff(array_keys(AdmissionStatus::LABELS),['total']),['reservas','otros']))],
            'visual'=>['nullable',Rule::in(array_keys(AdmissionBoardService::VISUAL_FILTERS))],
            'only_reevaluations'=>'nullable|boolean',
            'only_pending_reevaluations'=>'nullable|boolean',
            'reevaluation_stage'=>'nullable|in:PRE_PENDING,PENDING',
            'focus_appointment_id'=>'nullable|integer|min:1',
            'history_before'=>'nullable|integer|min:1','page'=>'nullable|integer|min:1|max:1000','revision'=>'nullable|string|regex:/\A[a-f0-9]{64}\z/']);
        abort_if(Carbon::parse($data['fecha'])->diffInDays(Carbon::parse($data['hasta'] ?? $data['fecha'])) > 30,422,'El rango máximo es de 31 días.');
        // Missing site chooses the actual catalog record; explicit null selects all sites.
        if (!$r->exists('site_id')) $data['site_id'] = $board->defaultSite()?->id;
        $payload=$board->board($data,$r->user());
        // Reauthorize and recalculate before acknowledging an in-memory client revision.
        $revision=hash('sha256',$r->user()->id.':'.json_encode($payload));
        $response=isset($data['revision'])&&hash_equals($revision,$data['revision'])
            ? ['unchanged'=>true,'revision'=>$revision] : $payload+['revision'=>$revision];
        return response()->json($response)->header('Cache-Control','private, no-store');
    }

    public function detail(Request $r, int $appointmentId, AdmissionBoardService $board)
    {
        $input=$r->validate(['revision'=>'nullable|string|regex:/\A[a-f0-9]{64}\z/']);
        // Visibility and current permissions are checked before acknowledging a client revision.
        $payload=$board->detail($appointmentId,$r->user());
        $revision=hash_hmac('sha256',json_encode([$r->user()->id,$payload]),config('app.key'));
        return response()->json(isset($input['revision'])&&hash_equals($revision,$input['revision'])
            ? ['unchanged'=>true,'revision'=>$revision] : $payload+['revision'=>$revision])
            ->header('Cache-Control','private, no-store');
    }

    public function reverseCare(Request $r, int $appointmentId, AdmissionCareService $service)
    {
        abort_unless($r->user()->hasAnyRole(['ADMINISTRADOR','ADMISION']), 403);
        abort_if(array_diff(array_keys($r->all()), ['expected_event_id','expected_state','request_key','motivo','confirmed','_token']),422);
        $data = $r->validate(['request_key'=>'required|uuid','expected_event_id'=>'required|integer|min:1',
            'expected_state'=>'required|string|max:40','motivo'=>'required|string|min:3|max:500']);
        $a = $service->reverse($appointmentId,$r->user(),$data);
        return response()->json(['id'=>$a->id,'estado_cita'=>$a->estado_cita])->header('Cache-Control','private, no-store');
    }

    public function physicianReevaluation(Request $r,int $appointmentId,string $action,
        \App\Services\Scheduling\AppointmentReevaluationService $service)
    {
        abort_unless(in_array($action,['indicate','withdraw','resume'],true),404);
        abort_if(array_diff(array_keys($r->all()),['expected_state','request_key','confirmed','reason','_token']),422);
        $data=$r->validate([
            'expected_state'=>'required|in:EN_ATENCION',
            'request_key'=>'required|uuid',
            'confirmed'=>'required|accepted',
            'reason'=>$action==='withdraw'?'required|string|min:3|max:500':'nullable|string|max:500',
        ]);
        $data['action']=$action;
        $a=$service->physicianDecision($appointmentId,$r->user(),$data);
        return response()->json(['id'=>$a->id,'estado_cita'=>$a->estado_cita])
            ->header('Cache-Control','private, no-store');
    }

    public function confirmOperationalReevaluation(Request $r,int $appointmentId,
        \App\Services\Scheduling\AppointmentReevaluationService $service)
    {
        abort_if(array_diff(array_keys($r->all()),['expected_state','request_key','confirmed','_token']),422);
        $data=$r->validate(['expected_state'=>'required|in:EN_ATENCION',
            'request_key'=>'required|uuid','confirmed'=>'required|accepted']);
        $a=$service->confirmOperational($appointmentId,$r->user(),$data);
        return response()->json(['id'=>$a->id,'estado_cita'=>$a->estado_cita])
            ->header('Cache-Control','private, no-store');
    }

    public function registerArrival(Request $r,int $appointmentId,AdmissionCareService $service)
    {
        abort_if(array_diff(array_keys($r->all()),['expected_state','request_key','confirmed','_token']),422);
        $data=$r->validate([
            'expected_state'=>'required|in:PROGRAMADO,CONFIRMADO,PACIENTE_LLEGO',
            'request_key'=>'required|uuid','confirmed'=>'required|accepted']);
        $a=$service->registerArrival($appointmentId,$r->user(),$data);
        return response()->json(['id'=>$a->id,'estado_cita'=>$a->estado_cita])
            ->header('Cache-Control','private, no-store');
    }

    public function closeDeclinedReevaluation(Request $r,int $appointmentId,
        \App\Services\Scheduling\AppointmentReevaluationService $service)
    {
        abort_if(array_diff(array_keys($r->all()),['expected_state','request_key','confirmed','reason','_token']),422);
        $data=$r->validate(['expected_state'=>'required|in:EN_ATENCION',
            'request_key'=>'required|uuid','confirmed'=>'required|accepted',
            'reason'=>'required|string|min:3|max:500']);
        $a=$service->closeDeclined($appointmentId,$r->user(),$data);
        return response()->json(['id'=>$a->id,'estado_cita'=>$a->estado_cita,
            'reevaluation_declined'=>true])->header('Cache-Control','private, no-store');
    }

    public function confirmExoneration(Request $r,int $appointmentId)
    {
        abort_if(array_diff(array_keys($r->all()),['request_key','revision','confirmed','_token']),422);
        $data=$r->validate(['request_key'=>'required|uuid','revision'=>'required|string|size:64','confirmed'=>'required|accepted']);
        try{$a=app(\App\Services\Scheduling\AdmissionExonerationConfirmation::class)->confirm($appointmentId,$r->user(),$data);}
        catch(\App\Exceptions\Scheduling\AppointmentSlotUnavailableException $e){return response()->json(['message'=>$e->getMessage()],409);}
        return response()->json(['id'=>$a->id,'estado_agenda'=>$a->estado_agenda])->header('Cache-Control','private, no-store');
    }

    public function confirmWithoutDeposit(Request $r,int $appointmentId)
    {
        abort_unless(\App\Services\Scheduling\ReservationPaymentService::withoutDepositActor($r->user()),403);
        abort_if(array_diff(array_keys($r->all()),['request_key','revision','confirmed','reason','_token']),422);
        $data=$r->validate(['request_key'=>'required|uuid','revision'=>'required|string|size:64',
            'confirmed'=>'required|accepted','reason'=>'required|string|min:5|max:500']);
        try{$a=app(\App\Services\Scheduling\ReservationPaymentService::class)->submit($appointmentId,$r->user(),[
            'request_key'=>$data['request_key'],'expected_revision'=>$data['revision'],'confirmed'=>$data['confirmed'],
            'reason'=>$data['reason'],'confirm'=>true,'without_deposit'=>true,'payment'=>['amount'=>0]]);}
        catch(\App\Exceptions\Scheduling\AppointmentSlotUnavailableException $e){return response()->json(['message'=>$e->getMessage()],409);}
        return response()->json(['id'=>$a->id,'estado_agenda'=>$a->estado_agenda])->header('Cache-Control','private, no-store');
    }

    public function care(Request $r, int $appointmentId, AdmissionCareService $service)
    {
        abort_if(array_diff(array_keys($r->all()),['state','expected_state','request_key','motivo','confirmed','_token']),422,'Esta acción solo actualiza el recorrido de atención.');
        $data = $r->validate(['request_key'=>'required|uuid','expected_state'=>['required',Rule::in(AdmissionStatus::STATES)],
            'state'=>'required|in:EN_ESPERA,LLAMANDO,EN_ATENCION,ATENDIDO,SALIDA_REGISTRADA',
            'motivo'=>'nullable|string|max:2000','confirmed'=>'sometimes|accepted']);
        $a = $service->transition($appointmentId,$r->user(),$data);
        return response()->json(['id'=>$a->id,'estado_cita'=>$a->estado_cita,'estado_agenda'=>$a->estado_agenda]+($data['state']==='SALIDA_REGISTRADA' ? ['detail'=>app(AdmissionBoardService::class)->detail($a->id,$r->user())] : []))
            ->header('Cache-Control','private, no-store');
    }
}
