<?php
namespace App\Services\Scheduling;
use App\Models\Appointment;
use App\Models\AppointmentDocument;
use App\Models\AppointmentReevaluationIndication as Indication;
use App\Models\User;
use App\Support\Scheduling\AppointmentAgendaLifecycle as L;
use App\Support\Scheduling\SchedulingCapability as C;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** An administrative attestation of the treating doctor's written order, never a patient self-request. */
final class ReevaluationIndicationService {
    public const CAPABILITY='appointment.reevaluation.indication.record';
    public function record(int $sourceId, User $actor, array $data, ?\Illuminate\Http\UploadedFile $file=null): Indication {
        abort_unless($actor->can(self::CAPABILITY) && $actor->can(C::VIEW),403);
        $visibleSource=Appointment::visibleToAgendaUser($actor->id)->whereKey($sourceId)->first();abort_unless($visibleSource,404);
        $paths=[];
        try {
        $result=DB::transaction(function() use($sourceId,$actor,$data,$file,&$paths,$visibleSource) {
            $hash=hash('sha256',json_encode(['kind'=>'REEVALUATION_INDICATION','source'=>$sourceId,'data'=>$data,'file_hash'=>$file?hash_file('sha256',$file->getRealPath()):null]));
            DB::table('appointment_operations')->upsert([['actor_user_id'=>$actor->id,'request_key'=>$data['request_key'],'payload_hash'=>$hash,'created_at'=>now(),'updated_at'=>now()]],['actor_user_id','request_key'],['request_key']);
            $op=DB::table('appointment_operations')->where('actor_user_id',$actor->id)->where('request_key',$data['request_key'])->lockForUpdate()->first();
            abort_unless(hash_equals($op->payload_hash,$hash),409);
            \App\Models\Doctor::whereKey($visibleSource->doctor_id)->lockForUpdate()->firstOrFail();
            $source=Appointment::visibleToAgendaUser($actor->id)->lockForUpdate()->find($sourceId);
            abort_unless($source,404);abort_unless((int)$source->doctor_id===(int)$visibleSource->doctor_id,409);
            if($existing=Indication::where('record_operation_id',$op->id)->first()) return $existing;
            $this->validAttendance($source);
            $document=$file?app(AppointmentProofStorage::class)->store($source,$actor->id,$file,'Indicación médica escrita')
                :AppointmentDocument::where('appointment_id',$sourceId)->whereKey($data['evidence_document_id'])->first();
            if($file) $paths[]=$document->private_path;
            abort_unless($document && $document->private_path && Storage::disk('local')->exists($document->private_path),404);
            $date=$data['indicated_on'];
            if($date<substr($source->fecha_cita,0,10) || $date>now(config('scheduling.clinic_timezone'))->toDateString()) {
                throw ValidationException::withMessages(['indicated_on'=>'La fecha de indicación debe estar entre la atención origen y hoy.']);
            }
            $evidenceHash=hash_file('sha256',Storage::disk('local')->path($document->private_path));
            $reference=trim($data['instruction_reference']);
            $instructionKey=hash('sha256',$sourceId.'|'.$evidenceHash.'|'.mb_strtoupper(preg_replace('/\s+/u',' ',$reference)));
            if(Indication::where('instruction_key',$instructionKey)->exists()) {
                throw ValidationException::withMessages(['instruction_reference'=>'Esta indicación del documento ya fue registrada. Reutiliza su número o acredita una indicación diferenciada del médico.']);
            }
            $document->update(['purpose'=>'REEVALUATION_ORDER']);
            $i=Indication::create(['source_appointment_id'=>$sourceId,'patient_id'=>$source->patient_id,'service_id'=>$source->service_id,'doctor_id'=>$source->doctor_id,'recorded_by_user_id'=>$actor->id,
                'record_operation_id'=>$op->id,'evidence_document_id'=>$document->id,'evidence_type'=>$data['evidence_type'],
                'evidence_hash'=>$evidenceHash,'instruction_reference'=>$reference,'instruction_key'=>$instructionKey,
                'indicated_on'=>$data['indicated_on'],'attended_on'=>substr($source->fecha_cita,0,10)]);
            DB::table('appointment_operations')->where('id',$op->id)->update(['appointment_id'=>$sourceId,'updated_at'=>now()]);
            app(AppointmentHistory::class)->record($source,'INDICACION_REEVALUACION_REGISTRADA',$actor->id,
                ['metadata'=>['indication_id'=>$i->id,'doctor_id'=>$source->doctor_id,'evidence_document_id'=>$document->id,'evidence_type'=>$i->evidence_type]]);
            return $i;
        },3);
        $retained=AppointmentDocument::withTrashed()->findOrFail($result->evidence_document_id)->private_path;
        foreach($paths as $p) if($p!==$retained) Storage::disk('local')->delete($p);
        return $result;
        }catch(\Throwable $e){foreach($paths as $p) Storage::disk('local')->delete($p);throw $e;}
    }
    public function assertVisibleOrigin(int $id,int $patient,int $service,int $actor):void {
        $i=Indication::find($id);abort_unless($i && (int)$i->patient_id===$patient && (int)$i->service_id===$service,404);
        $source=Appointment::visibleToAgendaUser($actor)->find($i->source_appointment_id);
        abort_unless($source && (int)$source->patient_id===$patient && (int)$source->service_id===$service
            && (int)$source->doctor_id===(int)$i->doctor_id && substr($source->fecha_cita,0,10)===$i->attended_on->toDateString(),404);
        $this->validAttendance($source);
    }
    public function eligible(int $id,int $patient,int $service,string $date,int $actor,?int $existing=null,bool $lock=false,?int $targetDoctor=null): array {
        $query=Indication::whereKey($id);
        if($lock) $query->lockForUpdate();
        $i=$query->first(); abort_unless($i,404);
        abort_unless((int)$i->patient_id===$patient && (int)$i->service_id===$service,404);
        if(!$lock) {
        $source=Appointment::visibleToAgendaUser($actor)->find($i->source_appointment_id);
        abort_unless($source && (int)$i->patient_id===$patient && (int)$i->service_id===$service
            && (int)$source->patient_id===$patient && (int)$source->service_id===$service
            && (int)$source->doctor_id===(int)$i->doctor_id
            && substr($source->fecha_cita,0,10)===$i->attended_on->toDateString(),404);
        $this->validAttendance($source);
        }
        if($i->bound_appointment_id && (int)$i->bound_appointment_id!==$existing) {
            throw ValidationException::withMessages(['reevaluation_indication_id'=>'La indicación no está disponible para otra cita.']);
        }
        if($lock) {
            \App\Models\Doctor::whereIn('id',array_unique([$i->doctor_id,$targetDoctor??$i->doctor_id]))->orderBy('id')->lockForUpdate()->get();
            $fresh=Appointment::visibleToAgendaUser($actor)->lockForUpdate()->find($i->source_appointment_id);
            abort_unless($fresh && (int)$fresh->patient_id===(int)$i->patient_id && (int)$fresh->service_id===(int)$i->service_id
                && (int)$fresh->doctor_id===(int)$i->doctor_id && substr($fresh->fecha_cita,0,10)===$i->attended_on->toDateString(),404);
            $this->validAttendance($fresh);
        }
        if($existing && $i->bound_appointment_id) {
            $bound=Appointment::find($existing);
            if(!$bound || !in_array($bound->estado_cita,['PROGRAMADO','CONFIRMADO'],true)) {
                throw ValidationException::withMessages(['reevaluation_indication_id'=>'La cita vinculada requiere revisión; no se libera la indicación automáticamente.']);
            }
        }
        $doc=AppointmentDocument::withTrashed()->find($i->evidence_document_id);
        if(!$doc || (int)$doc->appointment_id!==(int)$i->source_appointment_id || $doc->purpose!=='REEVALUATION_ORDER' || !$doc->private_path || !Storage::disk('local')->exists($doc->private_path)
            || !hash_equals($i->evidence_hash,hash_file('sha256',Storage::disk('local')->path($doc->private_path)))) {
            throw ValidationException::withMessages(['reevaluation_indication_id'=>'La evidencia de la indicación requiere revisión autorizada.']);
        }
        $previous=Carbon::parse($i->attended_on)->startOfDay(); $target=Carbon::parse($date)->startOfDay();
        if($target->lt($previous)) throw ValidationException::withMessages(['fecha_cita'=>'La reevaluación no puede preceder a la atención origen.']);
        return ['indication'=>$i,'days'=>$previous->diffInDays($target)];
    }
    private function validAttendance(Appointment $a): void {
        if(!$a->patient_id || !$a->service_id || !$a->doctor_id || $a->estado_cita!=='ATENDIDO' || !in_array($a->estado_agenda,[L::LEGACY,L::CONFIRMED],true)
            || substr($a->fecha_cita,0,10)>now(config('scheduling.clinic_timezone'))->toDateString()) {
            throw ValidationException::withMessages(['reevaluation_indication_id'=>'Selecciona una atención efectivamente realizada y consistente.']);
        }
    }
    public function bind(Indication $i,Appointment $a,int $actor): void {
        // eligible(...,lock:true) must already hold this row in the appointment transaction.
        abort_if($i->bound_appointment_id,409);
        $i->update(['bound_appointment_id'=>$a->id]);
        app(AppointmentHistory::class)->record($a,'INDICACION_REEVALUACION_VINCULADA',$actor,['metadata'=>['indication_id'=>$i->id]]);
    }
}
