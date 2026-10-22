<?php
namespace App\Http\Controllers\Scheduling;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Services\Scheduling\{MedicalBoardService,MedicalCareService,OperationalProcedureService};
use App\Support\Scheduling\{ClinicalAccess,ClinicalCapability as C};
use Illuminate\Http\Request;

final class MedicalBoardController extends Controller
{
    public function index(Request $r)
    {
        $u=ClinicalAccess::actor($r->user());
        return response()->view('scheduling.medical.index',['doctor'=>$u->doctor,'today'=>now('America/Lima')->toDateString(),
            'sites'=>Site::activo()->orderBy('nombre')->get(['id','nombre'])])->header('Cache-Control','private, no-store');
    }
    public function feed(Request $r,MedicalBoardService $board)
    {
        $data=$r->validate(['fecha'=>'required|date_format:Y-m-d','site_id'=>'nullable|integer|exists:sites,id',
            'patient'=>'nullable|string|max:100','page'=>'nullable|integer|min:1|max:1000']);
        abort_if(array_diff(array_keys($r->all()),['fecha','site_id','patient','page','_token']),422);
        return response()->json($board->feed($r->user(),$data))->header('Cache-Control','private, no-store');
    }
    public function detail(Request $r,int $appointmentId,MedicalBoardService $board)
    {
        return response()->json($board->detail($appointmentId,$r->user()))->header('Cache-Control','private, no-store');
    }
    public function care(Request $r,int $appointmentId,string $action,MedicalCareService $service,MedicalBoardService $board)
    {
        ClinicalAccess::actor($r->user(),MedicalCareService::capability($action));
        abort_if(array_diff(array_keys($r->all()),['request_key','expected_state','revision','confirmed','reason','_token']),422);
        $data=$r->validate(['request_key'=>'required|uuid','expected_state'=>'required|string|max:40',
            'revision'=>'required|string|size:64','confirmed'=>'required|accepted',
            'reason'=>in_array($action,['indicate','withdraw'],true)?'required|string|min:3|max:500':'nullable|string|max:500']);
        $service->perform($appointmentId,$r->user(),$action,$data);
        return response()->json($board->detail($appointmentId,$r->user()))->header('Cache-Control','private, no-store');
    }
    public function procedures(Request $r,int $appointmentId,OperationalProcedureService $service,MedicalBoardService $board)
    {
        ClinicalAccess::actor($r->user(),C::PROCEDURES);
        $rules=OperationalProcedureController::selectionRules()+['request_key'=>'required|uuid','expected_version'=>'required|integer|min:0'];
        abort_if(array_diff(array_keys($r->all()),array_merge(array_keys($rules),['_token'])),422);
        $data=$r->validate($rules);$data['expected_version']=(int)$data['expected_version'];
        $service->mutate($appointmentId,$r->user(),'selection',$data,true);
        return response()->json($board->detail($appointmentId,$r->user()))->header('Cache-Control','private, no-store');
    }
}
