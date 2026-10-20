<?php
namespace App\Services\Scheduling;
use App\Models\Appointment;
use App\Models\AppointmentDocument;
use App\Models\User;
use App\Support\Scheduling\AppointmentDocumentAccess as Access;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
/** Uses the existing private store and operation ledger. No new document table or patient record. */
class PatientAppointmentDocumentUpload {
 public function store(Appointment $visible,User $actor,array $data,UploadedFile $file):AppointmentDocument {
  abort_unless(Access::patientUpload($actor),403);$paths=[];
  try{return DB::transaction(function()use($visible,$actor,$data,$file,&$paths){
   if($paths){Storage::disk('local')->delete($paths);$paths=[];}
   $hash=hash('sha256',json_encode(['PATIENT_DOCUMENT_UPLOAD',$visible->id,$data]).hash_file('sha256',$file->getRealPath()));
   DB::table('appointment_operations')->upsert([['actor_user_id'=>$actor->id,'request_key'=>$data['request_key'],'payload_hash'=>$hash,'created_at'=>now(),'updated_at'=>now()]],['actor_user_id','request_key'],['request_key']);
   $op=DB::table('appointment_operations')->where('actor_user_id',$actor->id)->where('request_key',$data['request_key'])->lockForUpdate()->first();
   abort_unless(hash_equals($op->payload_hash,$hash),409,'El identificador pertenece a otra carga.');
   $fresh=$actor->fresh();abort_unless(Access::patientUpload($fresh),403);
   $a=Appointment::visibleToOperationalUser($fresh)->whereKey($visible->id)->lockForUpdate()->firstOr(fn()=>abort(404));
   abort_if((int)$a->patient_id!==(int)$visible->patient_id || (int)$a->service_id!==(int)$visible->service_id,409,'La cita cambió de paciente o servicio. Revisa la cita antes de adjuntar.');
   if($op->document_id)return $a->documents()->whereKey($op->document_id)->firstOr(fn()=>abort(409,'El documento ya no está vigente.'));
   $d=app(AppointmentProofStorage::class)->store($a,$actor->id,$file,$data['label']);$paths[]=$d->private_path;
   $d->update(['type'=>'OTHER','purpose'=>Access::PATIENT_PURPOSE,'document_kind'=>$data['document_kind']]);
   app(AppointmentHistory::class)->record($a,'DOCUMENTO_PACIENTE_ADJUNTADO',$actor->id,['metadata'=>['document_id'=>$d->id,'document_kind'=>$d->document_kind]]);
   DB::table('appointment_operations')->where('id',$op->id)->update(['appointment_id'=>$a->id,'document_id'=>$d->id,'updated_at'=>now()]);return $d;
  },3);}catch(\Throwable $e){if($paths)Storage::disk('local')->delete($paths);throw $e;}
 }
}
