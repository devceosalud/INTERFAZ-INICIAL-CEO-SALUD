<?php
namespace App\Services\Scheduling;
use App\Models\Appointment;
use App\Models\AppointmentDocument;
use App\Models\Doctor;
use App\Models\User;
use App\Support\Scheduling\AppointmentDocumentAccess as Access;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
class ReplaceAppointmentDocument
{
    public static function version(AppointmentDocument $d):string{return hash_hmac('sha256',json_encode($d->only(['id','appointment_id','type','purpose','document_kind','private_path','url','label','updated_at','deleted_at'])),config('app.key'));}
    public function replace(int $appointmentId,int $documentId,User $actor,array $data,UploadedFile $file):AppointmentDocument{
        abort_unless(Access::write($actor),403);
        $visible=Appointment::visibleToAgendaUser($actor->id)->whereKey($appointmentId)->firstOr(fn()=>abort(404));$paths=[];
        try{return DB::transaction(function()use($appointmentId,$documentId,$actor,$data,$file,$visible,&$paths){
            if($paths){Storage::disk('local')->delete($paths);$paths=[];}
            $hash=hash('sha256',json_encode(['REPLACE_PRIVATE_DOCUMENT',$appointmentId,$documentId,$data]).hash_file('sha256',$file->getRealPath()));
            DB::table('appointment_operations')->upsert([['actor_user_id'=>$actor->id,'request_key'=>$data['request_key'],'payload_hash'=>$hash,'created_at'=>now(),'updated_at'=>now()]],['actor_user_id','request_key'],['request_key']);
            $op=DB::table('appointment_operations')->where('actor_user_id',$actor->id)->where('request_key',$data['request_key'])->lockForUpdate()->first();abort_unless(hash_equals($op->payload_hash,$hash),409,'El identificador pertenece a otra operación.');
            if($op->document_id){abort_unless(Access::write($actor->fresh()),403);return AppointmentDocument::where('appointment_id',$appointmentId)->whereKey($op->document_id)->firstOr(fn()=>abort(409,'La versión resultante ya no está vigente.'));}
            Doctor::whereKey($visible->doctor_id)->lockForUpdate()->firstOrFail();$a=Appointment::visibleToAgendaUser($actor->id)->whereKey($appointmentId)->lockForUpdate()->firstOr(fn()=>abort(404));
            abort_unless(Access::write($actor->fresh()),403);abort_unless((int)$a->doctor_id===(int)$visible->doctor_id,409,'La cita cambió. Actualiza.');
            $old=$a->documents()->whereKey($documentId)->lockForUpdate()->firstOr(fn()=>abort(404));abort_unless(Access::read($actor,$old),404);
            abort_if($old->payment_id||$old->purpose==='REEVALUATION_ORDER'||\App\Models\AppointmentReevaluationIndication::where('evidence_document_id',$old->id)->exists(),422,'El comprobante de pago o la evidencia médica acreditada se conserva íntegra; no admite reemplazo.');
            abort_unless(hash_equals(self::version($old),$data['expected_version']),409,'El documento cambió. Actualiza antes de reemplazar.');
            $new=app(AppointmentProofStorage::class)->store($a,$actor->id,$file,$data['label']);$paths[]=$new->private_path;
            $new->update(['type'=>'OTHER','document_kind'=>$data['document_kind'],'replaces_document_id'=>$old->id,'replacement_reason'=>$data['reason']]);$old->delete();
            app(AppointmentHistory::class)->record($a,'DOCUMENTO_PRIVADO_REEMPLAZADO',$actor->id,['metadata'=>['previous_document_id'=>$old->id,'document_id'=>$new->id,'document_kind'=>$new->document_kind]]);
            DB::table('appointment_operations')->where('id',$op->id)->update(['appointment_id'=>$a->id,'document_id'=>$new->id,'updated_at'=>now()]);return $new;
        },3);}catch(\Throwable $e){if($paths)Storage::disk('local')->delete($paths);throw $e;}
    }
}
