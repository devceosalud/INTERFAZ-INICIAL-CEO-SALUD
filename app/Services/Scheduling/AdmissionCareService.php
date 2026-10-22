<?php
namespace App\Services\Scheduling;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\User;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Administrative care actions delegate existing legacy authorization/financial guards. */
class AdmissionCareService
{
    /** Narrow arrival permission: never gives COMERCIAL general appointment.update. */
    public static function canRegisterArrival(Appointment $a, User $actor): bool
    {
        return (bool)$actor->is_active && $actor->hasAnyRole(['ADMISION','COMERCIAL'])
            && $actor->can(C::MVP_ACCESS) && $actor->can(C::VIEW)
            && ($a->attendance_mode ?? 'PRESENCIAL') === 'PRESENCIAL'
            && in_array($a->estado_agenda,['LEGADO','CONFIRMADA'],true)
            && in_array($a->estado_cita,['PROGRAMADO','CONFIRMADO','PACIENTE_LLEGO'],true)
            && !$a->hora_llegada && !$a->hora_atencion && !$a->hora_atendido && !$a->hora_salida;
    }

    /** Registers actual arrival without inventing payment, medical work or an additional visit. */
    public function registerArrival(int $id, User $actor, array $data): Appointment
    {
        abort_unless($actor->is_active && $actor->hasAnyRole(['ADMISION','COMERCIAL'])
            && $actor->can(C::MVP_ACCESS) && $actor->can(C::VIEW),403);
        $original=Appointment::visibleToAgendaUser($actor->id)->whereKey($id)->firstOr(fn()=>abort(404));
        $hash=hash('sha256',json_encode(['OPERATIONAL_ARRIVAL_V1',$id,$data]));
        return AppointmentReevaluationService::transaction(function()use($id,$actor,$original,$data,$hash){
            DB::table('appointment_operations')->upsert([[
                'actor_user_id'=>$actor->id,'request_key'=>$data['request_key'],
                'payload_hash'=>$hash,'created_at'=>now(),'updated_at'=>now()
            ]],['actor_user_id','request_key'],['request_key']);
            $op=DB::table('appointment_operations')->where('actor_user_id',$actor->id)
                ->where('request_key',$data['request_key'])->lockForUpdate()->first();
            abort_unless(hash_equals($op->payload_hash,$hash),409,'Operación duplicada con datos distintos.');
            if($op->appointment_id) return Appointment::visibleToAgendaUser($actor->id)
                ->whereKey($op->appointment_id)->firstOr(fn()=>abort(404));
            Doctor::whereKey($original->doctor_id)->lockForUpdate()->firstOrFail();
            $a=Appointment::visibleToAgendaUser($actor->id)->whereKey($id)->lockForUpdate()->firstOr(fn()=>abort(404));
            abort_unless((int)$a->doctor_id===(int)$original->doctor_id
                && $a->estado_cita===$data['expected_state'],409,'La cita cambió. Actualiza.');
            abort_unless(self::canRegisterArrival($a,$actor),422,
                'Registrar llegada solo corresponde a una cita presencial aún no iniciada.');
            $previous=$a->only(['hora_llegada','hora_llamado','hora_atencion','hora_atendido','hora_salida']);
            $a->forceFill(['estado_cita'=>'EN_ESPERA','hora_llegada'=>now(),
                'updated_by_user_id'=>$actor->id])->save();
            app(AppointmentHistory::class)->record($a,'ADMISION_ESTADO_ACTUALIZADO',$actor->id,[
                'metadata'=>['from'=>$data['expected_state'],'to'=>'EN_ESPERA',
                    'timestamp_field'=>'hora_llegada','previous_timestamps'=>$previous,
                    'source'=>'ARRIVAL_ADMISSION_OR_COMMERCIAL_V1','operation_id'=>$op->id]]);
            DB::table('appointment_operations')->where('id',$op->id)
                ->update(['appointment_id'=>$a->id,'updated_at'=>now()]);
            return $a;
        },3);
    }

    public function reversalOffer(Appointment $a, User $actor): ?array
    {
        if (($a->attendance_mode ?? 'PRESENCIAL')==='TELECONSULTA') return null;
        if (!$actor->is_active || !$actor->hasAnyRole(['ADMINISTRADOR','ADMISION']) || !$actor->can(C::UPDATE)
            || !in_array($a->estado_agenda,['LEGADO','CONFIRMADA'],true) || $a->hora_atendido || $a->hora_salida
            || !in_array($a->estado_cita,['EN_ESPERA','LLAMANDO'],true)) return null;
        // Any later event invalidates the offer, including an edit that returns to the same state (ABA).
        $last = $a->events()->orderByDesc('id')->first();
        if (!$last || $last->event_type !== 'ADMISION_ESTADO_ACTUALIZADO' || !isset($last->metadata['previous_timestamps'])
            || ($last->metadata['to'] ?? null) !== $a->estado_cita
            || !in_array($last->metadata['from'] ?? null,['PROGRAMADO','CONFIRMADO','PACIENTE_LLEGO','EN_ESPERA','LLAMANDO'],true)) return null;
        return ['event_id'=>$last->id,'from'=>$a->estado_cita,'to'=>$last->metadata['from']];
    }

    public function reverse(int $id, User $actor, array $data): Appointment
    {
        abort_unless($actor->is_active && $actor->hasAnyRole(['ADMINISTRADOR','ADMISION']) && $actor->can(C::MVP_ACCESS)
            && $actor->can(C::VIEW) && $actor->can(C::UPDATE),403);
        abort_unless(mb_strlen(trim($data['motivo']))>=3,422,'Indica el motivo de corrección.');
        $original=Appointment::visibleToAgendaUser($actor->id)->whereKey($id)->firstOr(fn()=>abort(404));
        return AppointmentReevaluationService::transaction(function() use($id,$actor,$data,$original) {
            $hash=hash('sha256',json_encode(['CARE_REVERSAL',$id,$data]));
            DB::table('appointment_operations')->upsert([['actor_user_id'=>$actor->id,'request_key'=>$data['request_key'],
                'payload_hash'=>$hash,'created_at'=>now(),'updated_at'=>now()]],['actor_user_id','request_key'],['request_key']);
            $op=DB::table('appointment_operations')->where('actor_user_id',$actor->id)->where('request_key',$data['request_key'])->lockForUpdate()->first();
            abort_unless(hash_equals($op->payload_hash,$hash),409);
            Doctor::whereKey($original->doctor_id)->lockForUpdate()->firstOrFail();
            $a=Appointment::visibleToAgendaUser($actor->id)->whereKey($id)->lockForUpdate()->firstOr(fn()=>abort(404));
            if($op->appointment_id)return $a;
            abort_unless((int)$a->doctor_id===(int)$original->doctor_id && $a->estado_cita===$data['expected_state'],409,'La cita cambió. Actualiza antes de retroceder.');
            $offer=$this->reversalOffer($a,$actor);
            abort_unless($offer && (int)$offer['event_id']===(int)$data['expected_event_id'],409,'La última transición cambió o tiene efectos que impiden una reversión simple.');
            $before=$a->only(['hora_llegada','hora_llamado','hora_atencion','hora_atendido','hora_salida']);
            $event=$a->events()->findOrFail($offer['event_id']);
            $field=$event->metadata['timestamp_field'];
            $a->forceFill(['estado_cita'=>$offer['to'],'updated_by_user_id'=>$actor->id,
                $field=>$event->metadata['previous_timestamps'][$field] ?? null])->save();
            app(AppointmentHistory::class)->record($a,'ADMISION_ESTADO_REVERTIDO',$actor->id,
                ['motivo'=>trim($data['motivo']),'metadata'=>['from'=>$offer['from'],'to'=>$offer['to'],
                    'reversed_event_id'=>$offer['event_id'],'operation_id'=>$op->id,'preserved_timestamps'=>$before]]);
            DB::table('appointment_operations')->where('id',$op->id)->update(['appointment_id'=>$id,'updated_at'=>now()]);
            return $a;
        },3);
    }

    public function transition(int $id, User $actor, array $data): Appointment
    {
        abort_unless($actor->is_active,403);
        $original = Appointment::visibleToAgendaUser($actor->id)->whereKey($id)->firstOr(fn () => abort(404));
        $clinical=in_array($data['state'],['EN_ATENCION','ATENDIDO'],true);
        $verified=AppointmentReevaluationService::physicianCanAct($actor,$original);
        if($clinical && !$verified){
            abort_unless(UnlinkedDoctorAdmissionFallback::eligible($original,$actor),403);
            abort_unless(($data['confirmed']??false) && mb_strlen(trim($data['motivo']??''))>=3,422,'Confirma la contingencia e indica un motivo.');
        }elseif(!$clinical)abort_unless($actor->can(C::MVP_ACCESS) && $actor->can(C::VIEW) && $actor->can(C::UPDATE)
            && $actor->hasAnyRole(['ADMISION','ADMINISTRADOR']),403);
        return AppointmentReevaluationService::transaction(function () use ($id,$actor,$data,$original,$clinical,$verified) {
            $hash = hash('sha256',json_encode(['ADMISSION_CARE',$id,$data]));
            DB::table('appointment_operations')->upsert([['actor_user_id'=>$actor->id,'request_key'=>$data['request_key'],
                'payload_hash'=>$hash,'created_at'=>now(),'updated_at'=>now()]],['actor_user_id','request_key'],['request_key']);
            $op = DB::table('appointment_operations')->where('actor_user_id',$actor->id)->where('request_key',$data['request_key'])->lockForUpdate()->first();
            abort_unless(hash_equals($op->payload_hash,$hash),409,'El identificador ya corresponde a otra operación.');
            if ($op->appointment_id) return Appointment::visibleToAgendaUser($actor->id)->whereKey($op->appointment_id)->firstOr(fn () => abort(404));
            if($clinical && $verified){
                [$actor,$locked]=\App\Support\Scheduling\ClinicalAccess::lock($actor,$id,
                    $data['state']==='EN_ATENCION'?\App\Support\Scheduling\ClinicalCapability::START:\App\Support\Scheduling\ClinicalCapability::FINISH);
            }
            Doctor::whereKey($original->doctor_id)->lockForUpdate()->firstOrFail();
            $a = Appointment::visibleToAgendaUser($actor->id)->whereKey($id)->lockForUpdate()->firstOr(fn () => abort(404));
            abort_if((int)$a->doctor_id !== (int)$original->doctor_id || $a->estado_cita !== $data['expected_state'],409,'La cita cambió. Actualiza antes de continuar.');
            abort_if($data['state']==='SALIDA_REGISTRADA' && $a->hora_salida,409,'La salida ya fue registrada. Actualiza la cita.');
            if($clinical && !$verified)abort_unless(UnlinkedDoctorAdmissionFallback::eligible($a,$actor),403);
            if($data['state']==='EN_ATENCION')abort_unless($a->hora_llegada && !$a->hora_atencion,422,'Registra la llegada antes de iniciar atención.');
            if($data['state']==='ATENDIDO'){
                $a->setRelation('operationalReview',$a->operationalReview()->lockForUpdate()->first());
                app(ManualReevaluationService::class)->load(collect([$a]));
                abort_if(AppointmentReevaluationService::indicated($a)
                    && !AppointmentReevaluationService::resumed($a),422,
                    'La reevaluación requiere confirmación de Admisión, retorno y segunda atención médica antes de finalizar.');
                // Once a physician has signed an indication, closing that
                // clinical episode requires the same linked physician.
                abort_if(AppointmentReevaluationService::indicated($a)
                    && !AppointmentReevaluationService::physicianCanAct($actor,$a),403,
                    'La atención con reevaluación debe finalizarla el médico responsable.');
            }
            // A linked MEDICO must use their own account for clinical start/end.
            // Doctors without an account retain the pre-existing operational
            // contingency; no new role or permission is granted.
            if(in_array($data['state'],['EN_ATENCION','ATENDIDO'],true)
                && AppointmentReevaluationService::linkedPhysicianExists($a)){
                abort_unless(AppointmentReevaluationService::physicianCanAct($actor,$a),403,
                    'La atención corresponde a la cuenta vinculada del médico responsable.');
            }
            abort_unless(in_array($data['state'],app(AdmissionBoardService::class)->careActions($a,$actor),true),422,'La transición no está disponible. Confirma la reserva en Agenda antes de registrar atención.');
            if ($data['state'] === 'SALIDA_REGISTRADA') {
                $a->forceFill(['hora_salida'=>now(), 'updated_by_user_id'=>$actor->id])->save();
                app(AppointmentHistory::class)->record($a,'ADMISION_SALIDA_REGISTRADA',$actor->id,
                    ['motivo'=>$data['motivo']??null, 'metadata'=>['state'=>'ATENDIDO','timestamp_field'=>'hora_salida','operation_id'=>$op->id]]);
                DB::table('appointment_operations')->where('id',$op->id)->update(['appointment_id'=>$a->id,'updated_at'=>now()]);
                return $a;
            }
            $beforeTimes=$a->only(['hora_llegada','hora_llamado','hora_atencion','hora_atendido','hora_salida']);
            $request = Request::create('/','POST',['appointment_id'=>$id,'estado_cita'=>$data['state'],'request_key'=>$data['request_key']]);
            $request->setUserResolver(fn () => $actor);
            if($clinical){
                $a->forceFill(['estado_cita'=>$data['state'],'updated_by_user_id'=>$actor->id])->save();
            }else $a = app(LegacyAppointmentMutation::class)->update($request);
            $time = match($data['state']) {'EN_ESPERA'=>'hora_llegada','LLAMANDO'=>'hora_llamado','EN_ATENCION'=>'hora_atencion','ATENDIDO'=>'hora_atendido'};
            // Record only the action just performed; never synthesize missing previous timestamps.
            if (!$a->{$time}) $a->forceFill([$time=>now()])->save();
            app(AppointmentHistory::class)->record($a,'ADMISION_ESTADO_ACTUALIZADO',$actor->id,
                ['metadata'=>['from'=>$data['expected_state'],'to'=>$data['state'],'timestamp_field'=>$time,
                    'previous_timestamps'=>$beforeTimes,'operation_id'=>$op->id,
                    'physician_signed'=>$clinical && $verified,'source'=>$clinical?($verified?'VERIFIED_PHYSICIAN_CARE_V1':'ADMISION_FALLBACK_UNLINKED_DOCTOR_V1'):'ADMISSION_CARE_V1']]);
            DB::table('appointment_operations')->where('id',$op->id)->update(['appointment_id'=>$a->id,'updated_at'=>now()]);
            return $a;
        },3);
    }
}
