<?php
namespace App\Http\Controllers\Scheduling;
use App\Http\Controllers\Controller;
use App\Services\Scheduling\MedicalProcedureFollowupService as Followups;
use App\Support\Scheduling\{ClinicalAccess,ProcedureCatalog};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
final class MedicalProcedureFollowupController extends Controller {
    public function refer(Request $r,int $appointmentId,Followups $service){\App\Support\Scheduling\MedicalFollowupAccess::actor($r->user(),\App\Support\Scheduling\ClinicalCapability::PROCEDURES);$service->refer($r->user(),$appointmentId,$r->all());return response()->json(['message'=>'Procedimientos pendientes enviados a seguimiento.','redirect_url'=>route('scheduling.mvp.medical-followups')])->header('Cache-Control','private, no-store');}
    public function index(Request $r){\App\Support\Scheduling\MedicalFollowupAccess::actor($r->user());return response()->view('scheduling.followups.index')->header('Cache-Control','private, no-store')->header('Referrer-Policy','no-referrer');}
    public function feed(Request $r,Followups $service){
        \App\Support\Scheduling\MedicalFollowupAccess::actor($r->user());$filters=$r->validate(['patient'=>'nullable|string|max:100','status'=>['nullable',Rule::in(array_keys(Followups::STATES))],'procedure'=>['nullable',Rule::in(array_keys(ProcedureCatalog::FAMILIES))],'origin'=>'nullable|in:manual,automatic,general','date_from'=>'nullable|date_format:Y-m-d','date_to'=>'nullable|date_format:Y-m-d|after_or_equal:date_from','older_than'=>'nullable|in:7,30,90','order'=>'nullable|in:oldest,newest','page'=>'nullable|integer|min:1|max:10000']);
        return response()->json($service->feed($r->user(),$filters))->header('Cache-Control','private, no-store')->header('Referrer-Policy','no-referrer');
    }
    public function show(Request $r,int $followupId,Followups $service){return response()->json($service->detail($r->user(),$followupId))->header('Cache-Control','private, no-store');}
    public function update(Request $r,int $followupId,Followups $service){
        \App\Support\Scheduling\MedicalFollowupAccess::actor($r->user(),\App\Support\Scheduling\ClinicalCapability::PROCEDURES);
        $data=$r->validate(['request_key'=>'required|uuid','expected_version'=>'required|integer|min:1','status'=>['required',Rule::in(array_keys(Followups::STATES))],'planned_review_on'=>'nullable|required_if:status,SCHEDULED|date_format:Y-m-d','notes'=>'nullable|string|max:1000','reason'=>'required|string|min:15|max:1000','confirmed'=>'required|accepted']);
        $data['expected_version']=(int)$data['expected_version'];$service->update($r->user(),$followupId,$data);return response()->json(['message'=>'Seguimiento actualizado con auditoría.'])->header('Cache-Control','private, no-store');
    }
}
