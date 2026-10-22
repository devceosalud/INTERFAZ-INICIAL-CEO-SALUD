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
    /** Fail closed until a verified User-to-Doctor association is approved and implemented. */
    public static function authorizeState(User $actor,string $state): void
    {
        // Preserve the legacy permission checks and status semantics for every
        // existing caller. Commercial arrival has a separate, narrower endpoint.
        abort_unless($actor->is_active && $actor->can(C::MVP_ACCESS) && $actor->can(C::VIEW) && $actor->can(C::UPDATE),403);
        abort_if(in_array($state,['EN_ATENCION','ATENDIDO','REEVALUACION'],true),403,
            'La acción médica requiere una vinculación verificable entre usuario y médico responsable; todavía no está habilitada.');
        if(in_array($state,['PACIENTE_LLEGO','EN_ESPERA','LLAMANDO','SALIDA_REGISTRADA'],true))
            abort_unless($actor->hasAnyRole(['ADMISION','ADMINISTRADOR']),403);
    }

    public static function commercialArrival(User $actor,string $state): bool
    {
        return $state==='EN_ESPERA' && $actor->is_active
            && $actor->hasRole('COMERCIAL') && !$actor->hasAnyRole(['ADMISION','ADMINISTRADOR'])
            && $actor->can(C::MVP_ACCESS) && $actor->can(C::VIEW);
    }

    public static function commercialDeparture(User $actor,string $state): bool
    {
        return $state==='SALIDA_REGISTRADA' && self::commercialArrival($actor,'EN_ESPERA');
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
        return DB::transaction(function() use($id,$actor,$data,$original) {
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

    public function transition(int $id, User $actor, array $data, bool $commercialCheckIn=false,bool $commercialDeparture=false): Appointment
    {
        $fallback=in_array($data['state'],['EN_ATENCION','ATENDIDO'],true);
        if($commercialCheckIn)abort_unless(self::commercialArrival($actor,$data['state']),403);
        elseif($commercialDeparture)abort_unless(self::commercialDeparture($actor,$data['state']),403);
        elseif (!$fallback) self::authorizeState($actor,$data['state']);
        $original = Appointment::visibleToAgendaUser($actor->id)->whereKey($id)->firstOr(fn () => abort(404));
        if ($fallback) {
            abort_unless(UnlinkedDoctorAdmissionFallback::eligible($original,$actor),403,
                'La contingencia solo corresponde a un médico sin cuenta vinculada.');
            abort_unless(($data['confirmed']??false) && mb_strlen(trim($data['motivo']??''))>=3
                && mb_strlen(trim($data['motivo']))<=500,422,
                'Confirma e indica un motivo de al menos tres caracteres para la contingencia.');
        }
        return DB::transaction(function () use ($id,$actor,$data,$original,$fallback,$commercialCheckIn,$commercialDeparture) {
            $hash = hash('sha256',json_encode(['ADMISSION_CARE',$id,$data]));
            DB::table('appointment_operations')->upsert([['actor_user_id'=>$actor->id,'request_key'=>$data['request_key'],
                'payload_hash'=>$hash,'created_at'=>now(),'updated_at'=>now()]],['actor_user_id','request_key'],['request_key']);
            $op = DB::table('appointment_operations')->where('actor_user_id',$actor->id)->where('request_key',$data['request_key'])->lockForUpdate()->first();
            abort_unless(hash_equals($op->payload_hash,$hash),409,'El identificador ya corresponde a otra operación.');
            if ($op->appointment_id) return Appointment::visibleToAgendaUser($actor->id)->whereKey($op->appointment_id)->firstOr(fn () => abort(404));
            Doctor::whereKey($original->doctor_id)->lockForUpdate()->firstOrFail();
            $a = Appointment::visibleToAgendaUser($actor->id)->whereKey($id)->lockForUpdate()->firstOr(fn () => abort(404));
            abort_if((int)$a->doctor_id !== (int)$original->doctor_id || $a->estado_cita !== $data['expected_state'],409,'La cita cambió. Actualiza antes de continuar.');
            abort_if($data['state']==='SALIDA_REGISTRADA' && $a->hora_salida,409,'La salida ya fue registrada. Actualiza la cita.');
            // Re-evaluate linkage under the locked doctor row: UserDoctorLink locks the same
            // doctor before committing a new association, so late links close this path.
            if($fallback)abort_unless(UnlinkedDoctorAdmissionFallback::eligible($a,$actor),403,
                'Este médico ya tiene una cuenta vinculada o la contingencia fue desactivada.');
            abort_unless(in_array($data['state'],app(AdmissionBoardService::class)->careActions($a,$actor),true),422,'La transición no está disponible. Confirma la reserva en Agenda antes de registrar atención.');
            if($fallback){
                abort_unless(UnlinkedDoctorAdmissionFallback::allowedState($a)===$data['state'],422,
                    'El inicio o fin requiere el recorrido de atención anterior.');
                if($data['state']==='ATENDIDO'){
                    $a->setRelation('medicalReevaluationEvent',$a->events()->whereIn('event_type',[
                        AppointmentReevaluationService::INDICATED,AppointmentReevaluationService::WITHDRAWN])->orderByDesc('id')->lockForUpdate()->first());
                    $a->setRelation('manualReevaluationEvent',$a->events()->whereIn('event_type',[
                        ManualReevaluationService::MARKED,ManualReevaluationService::CLEARED])->orderByDesc('id')->lockForUpdate()->first());
                    $a->setRelation('operationalReview',$a->operationalReview()->lockForUpdate()->first());
                    abort_if(AppointmentReevaluationService::pending($a),422,
                        'No se puede finalizar con una reevaluación pendiente.');
                }
                $field=$data['state']==='EN_ATENCION'?'hora_atencion':'hora_atendido';
                $before=$a->only(['estado_cita','hora_atencion','hora_atendido','hora_salida']);
                $a->forceFill(['estado_cita'=>$data['state'],$field=>now(),'updated_by_user_id'=>$actor->id])->save();
                app(AppointmentHistory::class)->record($a,$data['state']==='EN_ATENCION'
                    ? UnlinkedDoctorAdmissionFallback::START : UnlinkedDoctorAdmissionFallback::FINISH,
                    $actor->id,['motivo'=>trim($data['motivo']),
                        'metadata'=>['version'=>1,'source'=>'ADMISION_FALLBACK_UNLINKED_DOCTOR_V1',
                            'doctor_id'=>(int)$a->doctor_id,'actor_role'=>'ADMISION_O_ADMINISTRADOR',
                            'physician_signed'=>false,'operation_id'=>$op->id,
                            'before'=>$before,'after'=>$a->only(array_keys($before))]]);
                DB::table('appointment_operations')->where('id',$op->id)
                    ->update(['appointment_id'=>$a->id,'updated_at'=>now()]);
                return $a;
            }
            if($commercialCheckIn){
                abort_unless(self::commercialArrival($actor,$data['state']),403);
                // ARRIVAL ONLY: intentionally avoid LegacyAppointmentMutation, which
                // requires the broad appointment.update permission. Keep the same
                // transactional state, timestamp and audit contract as Admisión.
                $beforeTimes=$a->only(['hora_llegada','hora_llamado','hora_atencion','hora_atendido','hora_salida']);
                abort_if($a->hora_llegada || $a->hora_atencion || $a->hora_atendido || $a->hora_salida,422,
                    'La llegada ya está registrada o la atención está avanzada.');
                $a->forceFill(['estado_cita'=>'EN_ESPERA','hora_llegada'=>now(),'updated_by_user_id'=>$actor->id])->save();
                app(AppointmentHistory::class)->record($a,'ADMISION_ESTADO_ACTUALIZADO',$actor->id,
                    ['metadata'=>['from'=>$data['expected_state'],'to'=>'EN_ESPERA',
                        'timestamp_field'=>'hora_llegada','previous_timestamps'=>$beforeTimes,
                        'operation_id'=>$op->id,'source'=>'COMERCIAL_PATIENT_ARRIVAL_V1']]);
                DB::table('appointment_operations')->where('id',$op->id)
                    ->update(['appointment_id'=>$a->id,'updated_at'=>now()]);
                return $a;
            }
            if ($data['state'] === 'SALIDA_REGISTRADA') {
                if($commercialDeparture)abort_unless(self::commercialDeparture($actor,$data['state']),403);
                abort_unless($a->estado_cita==='ATENDIDO',422,'Finaliza la atención antes de registrar salida.');
                $a->forceFill(['hora_salida'=>now(), 'updated_by_user_id'=>$actor->id])->save();
                app(AppointmentHistory::class)->record($a,'ADMISION_SALIDA_REGISTRADA',$actor->id,
                    ['motivo'=>$data['motivo']??null, 'metadata'=>['state'=>'ATENDIDO','timestamp_field'=>'hora_salida','operation_id'=>$op->id]]);
                DB::table('appointment_operations')->where('id',$op->id)->update(['appointment_id'=>$a->id,'updated_at'=>now()]);
                return $a;
            }
            $beforeTimes=$a->only(['hora_llegada','hora_llamado','hora_atencion','hora_atendido','hora_salida']);
            $request = Request::create('/','POST',['appointment_id'=>$id,'estado_cita'=>$data['state'],'request_key'=>$data['request_key']]);
            $request->setUserResolver(fn () => $actor);
            $a = app(LegacyAppointmentMutation::class)->update($request);
            $time = match($data['state']) {'EN_ESPERA'=>'hora_llegada','LLAMANDO'=>'hora_llamado','EN_ATENCION'=>'hora_atencion','ATENDIDO'=>'hora_atendido'};
            // Record only the action just performed; never synthesize missing previous timestamps.
            if (!$a->{$time}) $a->forceFill([$time=>now()])->save();
            app(AppointmentHistory::class)->record($a,'ADMISION_ESTADO_ACTUALIZADO',$actor->id,
                ['metadata'=>['from'=>$data['expected_state'],'to'=>$data['state'],'timestamp_field'=>$time,
                    'previous_timestamps'=>$beforeTimes,'operation_id'=>$op->id]]);
            DB::table('appointment_operations')->where('id',$op->id)->update(['appointment_id'=>$a->id,'updated_at'=>now()]);
            return $a;
        },3);
    }
}
