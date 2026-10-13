<?php
namespace App\Http\Controllers\Scheduling;
use App\Http\Controllers\Controller;
use App\Services\Scheduling\ReevaluationIndicationService;
use Illuminate\Http\Request;
class ReevaluationIndicationController extends Controller {
    public function store(Request $r,int $appointmentId,ReevaluationIndicationService $service) {
        if($r->has('payload')){$payload=json_decode((string)$r->input('payload'),true);abort_unless(is_array($payload),422);$r->merge($payload);}
        $data=$r->validate(['request_key'=>'required|uuid','evidence_document_id'=>'nullable|required_without:file|integer|min:1',
            'file'=>['nullable','required_without:evidence_document_id','file',new \App\Rules\PrivateAppointmentFile(),'max:8192'],
            'indicated_on'=>'required|date_format:Y-m-d','evidence_type'=>'required|in:WRITTEN_ORDER,PRESCRIPTION,REPORT,AUTHENTICATED_DOCTOR_RECORD',
            'instruction_reference'=>'required|string|max:120','doctor_order_attested'=>'required|accepted','doctor_id'=>'prohibited','bound_appointment_id'=>'prohibited']);
        $file=$r->file('file');unset($data['file']);
        abort_if($file && !empty($data['evidence_document_id']),422,'Usa un documento existente o un archivo nuevo.');
        $i=$service->record($appointmentId,$r->user(),$data,$file);
        return response()->json(['indication_id'=>$i->id,'bound_appointment_id'=>$i->bound_appointment_id],201);
    }
}
