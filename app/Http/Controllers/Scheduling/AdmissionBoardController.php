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
    public function index(AdmissionBoardService $board)
    {
        return response()->view('scheduling.admission.index',[
            'today'=>now('America/Lima')->toDateString(),'defaultSite'=>$board->defaultSite(),
            'sites'=>Site::activo()->orderBy('nombre')->get(['id','nombre']),
            'doctors'=>Doctor::orderBy('nombre')->get(['id','nombre','specialty_id','estado']),
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
            'page'=>'nullable|integer|min:1|max:1000','revision'=>'nullable|string|regex:/\A[a-f0-9]{64}\z/']);
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
        abort_if(array_diff(array_keys($r->all()), ['expected_event_id','expected_state','request_key','motivo','_token']),422);
        $data = $r->validate(['request_key'=>'required|uuid','expected_event_id'=>'required|integer|min:1',
            'expected_state'=>'required|string|max:40','motivo'=>'required|string|min:3|max:500']);
        $a = $service->reverse($appointmentId,$r->user(),$data);
        return response()->json(['id'=>$a->id,'estado_cita'=>$a->estado_cita])->header('Cache-Control','private, no-store');
    }

    public function care(Request $r, int $appointmentId, AdmissionCareService $service)
    {
        abort_if(array_diff(array_keys($r->all()),['state','expected_state','request_key','motivo','_token']),422,'Esta acción solo actualiza el recorrido de atención.');
        $data = $r->validate(['request_key'=>'required|uuid','expected_state'=>['required',Rule::in(AdmissionStatus::STATES)],
            'state'=>'required|in:EN_ESPERA,LLAMANDO,EN_ATENCION,ATENDIDO,SALIDA_REGISTRADA',
            'motivo'=>'required_if:state,SALIDA_REGISTRADA|nullable|string|max:2000']);
        $a = $service->transition($appointmentId,$r->user(),$data);
        return response()->json(['id'=>$a->id,'estado_cita'=>$a->estado_cita,'estado_agenda'=>$a->estado_agenda])
            ->header('Cache-Control','private, no-store');
    }
}
