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
    public function reversalOffer(Appointment $a, User $actor): ?array
    {
        if (($a->attendance_mode ?? 'PRESENCIAL')==='TELECONSULTA') return null;
        if (!$actor->is_active || !$actor->hasAnyRole(['ADMINISTRADOR','ADMISION']) || !$actor->can(C::UPDATE)
            || !in_array($a->estado_agenda,['LEGADO','CONFIRMADA'],true) || $a->hora_atendido || $a->hora_salida
            || !in_array($a->estado_cita,['EN_ESPERA','LLAMANDO','EN_ATENCION'],true)) return null;
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

    public function transition(int $id, User $actor, array $data): Appointment
    {
        abort_unless($actor->can(C::VIEW) && $actor->can(C::UPDATE),403);
        $original = Appointment::visibleToAgendaUser($actor->id)->whereKey($id)->firstOr(fn () => abort(404));
        return DB::transaction(function () use ($id,$actor,$data,$original) {
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
            if($data['state']==='ATENDIDO'){
                $a->loadMissing('operationalReview');
                app(ManualReevaluationService::class)->load(collect([$a]));
                abort_if(AppointmentReevaluationService::pending($a),422,
                    'No puedes finalizar: la reevaluación médica continúa pendiente.');
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
