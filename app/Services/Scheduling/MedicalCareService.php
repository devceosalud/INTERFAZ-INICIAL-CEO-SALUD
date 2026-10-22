<?php
namespace App\Services\Scheduling;

use App\Models\{Appointment,User};
use App\Support\Scheduling\{ClinicalAccess,ClinicalCapability as C};
use Illuminate\Support\Facades\DB;

final class MedicalCareService
{
    public static function capability(string $action): string
    {
        return match($action){'start'=>C::START,'finish'=>C::FINISH,'indicate'=>C::INDICATE,'withdraw'=>C::WITHDRAW,default=>abort(422)};
    }

    public static function revision(Appointment $a): string
    {
        return hash('sha256',json_encode([$a->only(['id','doctor_id','fecha_cita','hora_cita','estado_cita','estado_agenda',
            'attendance_mode','hora_llegada','hora_atencion','hora_atendido','hora_salida']),
            $a->events()->max('id'),DB::table('appointment_operational_reviews')->where('appointment_id',$a->id)->value('version')]));
    }

    public function perform(int $id,User $actor,string $action,array $data): Appointment
    {
        $cap=self::capability($action);
        $fresh=ClinicalAccess::actor($actor,$cap);
        $original=Appointment::findOrFail($id);
        ClinicalAccess::appointment($fresh,$original);
        return DB::transaction(function()use($id,$actor,$action,$data,$cap){
            [$u,$a]=ClinicalAccess::lock($actor,$id,$cap);
            $hash=hash('sha256',json_encode(['MEDICAL_CARE_V1',$id,$action,$data]));
            DB::table('appointment_operations')->upsert([['actor_user_id'=>$u->id,'request_key'=>$data['request_key'],
                'payload_hash'=>$hash,'created_at'=>now(),'updated_at'=>now()]],['actor_user_id','request_key'],['request_key']);
            $op=DB::table('appointment_operations')->where('actor_user_id',$u->id)->where('request_key',$data['request_key'])->lockForUpdate()->first();
            abort_unless(hash_equals($op->payload_hash,$hash),409,'El identificador corresponde a otra operación.');
            if($op->appointment_id)return $a;
            abort_unless($data['confirmed'],422);
            abort_unless($a->estado_cita===$data['expected_state'] && hash_equals(self::revision($a),$data['revision']),409,
                'La cita o sus procedimientos cambiaron. Revisa nuevamente antes de confirmar.');
            ClinicalAccess::activeCare($a);
            $before=$a->only(['estado_cita','hora_atencion','hora_atendido','hora_salida']);
            $metadata=['version'=>1,'doctor_id'=>(int)$u->doctor_id,'operation_id'=>(int)$op->id];
            if($action==='start'){
                abort_unless(in_array($a->estado_cita,['EN_ESPERA','LLAMANDO'],true) && !$a->hora_atencion,422,
                    'Admisión debe registrar llegada antes de iniciar la atención.');
                $a->forceFill(['estado_cita'=>'EN_ATENCION','hora_atencion'=>now(),'updated_by_user_id'=>$u->id])->save();
                $type='ATENCION_MEDICA_INICIADA';
            }elseif($action==='finish'){
                abort_unless($a->estado_cita==='EN_ATENCION',422,'La atención debe estar iniciada.');
                $a->forceFill(['estado_cita'=>'ATENDIDO','hora_atendido'=>now(),'updated_by_user_id'=>$u->id])->save();
                $type='ATENCION_MEDICA_FINALIZADA';
            }else{
                abort_unless($a->estado_cita==='EN_ATENCION',422,'La indicación corresponde a una atención iniciada.');
                abort_unless(mb_strlen(trim($data['reason']??''))>=3,422,'Indica el motivo de la indicación o retirada.');
                app(ManualReevaluationService::class)->load(collect([$a]));
                $a->load('operationalReview');
                $latest=$a->events()->whereIn('event_type',[AppointmentReevaluationService::INDICATED,AppointmentReevaluationService::WITHDRAWN])
                    ->orderByDesc('id')->lockForUpdate()->first();
                $a->setRelation('medicalReevaluationEvent',$latest);
                $indicated=AppointmentReevaluationService::indication($a)['indicated'];
                if($action==='indicate'){
                    abort_if($indicated,409,'La indicación médica ya está activa.');
                    abort_if(AppointmentReevaluationService::ready($a),422,'El paciente ya está listo para reevaluación. No se inicia otro ciclo automáticamente.');
                    $type=AppointmentReevaluationService::INDICATED;
                }else{
                    abort_unless($indicated,409,'No hay una indicación activa para retirar.');
                    $type=AppointmentReevaluationService::WITHDRAWN;
                }
                $metadata+=['source'=>'MEDICAL_RETURN_SAME_APPOINTMENT_V1','previous_event_id'=>$latest?->id];
            }
            app(AppointmentHistory::class)->record($a,$type,$u->id,[
                'motivo'=>trim($data['reason']??'')?:null,'metadata'=>$metadata+[
                    'before'=>$before,'after'=>$a->only(array_keys($before))]]);
            DB::table('appointment_operations')->where('id',$op->id)->update(['appointment_id'=>$a->id,'updated_at'=>now()]);
            return $a;
        },3);
    }
}
