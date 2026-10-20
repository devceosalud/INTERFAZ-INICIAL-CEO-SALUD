<?php
namespace App\Http\Controllers\Scheduling;

use App\Http\Controllers\Controller;
use App\Services\Scheduling\AppointmentQuickEdit;
use App\Support\Scheduling\OperationalText;
use Illuminate\Http\Request;

final class AppointmentQuickEditController extends Controller
{
    public function show(Request $request,int $appointmentId,AppointmentQuickEdit $service)
    {
        $a=$service->find($appointmentId,$request->user());
        $data=$request->validate(['service_id'=>'sometimes|required|integer|min:1']);
        return response()->json($service->inspect($a,$request->user(),$data['service_id']??null));
    }
    public function update(Request $request,int $appointmentId,AppointmentQuickEdit $service)
    {
        // Reject extra monetary/clinical fields instead of silently accepting a forged form.
        abort_if(array_diff(array_keys($request->all()),['action','patient_id','service_id','motivo','motivo_consulta','request_key','expected_revision','confirmed','_token']),422,
            'Esta edición no modifica pagos, precios ni otros datos clínicos.');
        foreach(['motivo','motivo_consulta']as$key)if(is_string($request->input($key)))$request->merge([$key=>OperationalText::trim($request->input($key))]);
        $data=$request->validate(['action'=>'required|in:patient,service','patient_id'=>'required_if:action,patient|integer|min:1',
            'service_id'=>'required_if:action,service|integer|min:1','motivo'=>'required|string|max:2000',
            'motivo_consulta'=>'required_if:action,patient|string|max:2000','request_key'=>'required|uuid',
            'expected_revision'=>'required|string|size:64|regex:/^[a-f0-9]+$/','confirmed'=>'required|accepted']);
        try{$a=$service->change($appointmentId,$request->user(),$data);}
        catch(\App\Exceptions\Scheduling\AppointmentSlotUnavailableException $e){return response()->json(['message'=>$e->getMessage()],409);}
        return response()->json(['message'=>'Cita actualizada. Los importes y pagos se conservaron.','appointment_id'=>$a->id]);
    }
}
