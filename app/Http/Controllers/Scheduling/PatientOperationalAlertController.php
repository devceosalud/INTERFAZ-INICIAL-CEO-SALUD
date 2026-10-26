<?php
namespace App\Http\Controllers\Scheduling;
use App\Http\Controllers\Controller;
use App\Services\Scheduling\PatientOperationalAlertService as Alerts;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
final class PatientOperationalAlertController extends Controller {
    public function show(Request $r,int $appointmentId,Alerts $service){return response()->json($service->detail($appointmentId,$r->user()))->header('Cache-Control','private, no-store')->header('Referrer-Policy','no-referrer');}
    public function update(Request $r,int $appointmentId,Alerts $service){
        $policy=\App\Support\Scheduling\AdmissionRolePolicy::class;
        abort_unless($policy::operational($r->user())||($r->input('action')==='resolve'&&$policy::administrator($r->user())),403);
        $data=$r->validate(['request_key'=>'required|uuid','expected_version'=>'required|integer|min:0','expected_patient_id'=>'required|integer|min:1','action'=>['required',Rule::in(['save','resolve'])],
            'confirmed'=>'required|accepted','facts_confirmed'=>'required_if:action,save|accepted','reason'=>'required|string|min:1|max:1000',
            'category'=>['required_if:action,save',Rule::in(array_keys(Alerts::CATEGORIES))],'incident_on'=>'required_if:action,save|date_format:Y-m-d|before_or_equal:today']);
        $data['expected_version']=(int)$data['expected_version'];$service->mutate($appointmentId,$r->user(),$data);
        return response()->json(['message'=>'Alerta registrada con auditoría.'])->header('Cache-Control','private, no-store');
    }
}
