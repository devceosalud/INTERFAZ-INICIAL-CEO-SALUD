<?php
namespace App\Services\Scheduling;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\User;
use App\Services\Billing\AppointmentEconomicPosition;
use App\Support\Billing\Money;
use App\Support\Scheduling\SchedulingCapability as C;
use App\Exceptions\Scheduling\AppointmentSlotUnavailableException;
use Illuminate\Support\Facades\DB;

/** Explicit resolution of an existing private reservation. No new appointment or money. */
class AdditionalReservationConversion
{
    public function offer(Appointment $a,User $actor):array{
        $p=app(AppointmentEconomicPosition::class)->forAppointment($a);
        $snapshot=[$a->id,$a->doctor_id,$a->patient_id,$a->service_id,$a->site_id,$a->fecha_cita,$a->hora_cita,
            $a->duracion_cita,$a->duration_slots,$a->estado_cita,$a->estado_agenda,$a->tipo_agendamiento,
            $a->pricing_rule,$a->es_exonerado,$a->autorizado_por,$p['price_cents'],$p['effective_cents']];
        $offer=['available'=>false,'can_convert'=>false,'expected_version'=>hash_hmac('sha256',json_encode($snapshot),config('app.key')),
            'price'=>Money::decimal($p['price_cents']),'paid'=>Money::decimal($p['paid_cents']),
            'minimum_advance'=>Money::decimal(intdiv($p['price_cents']+1,2)),
            'reason'=>'La conversión solo resuelve una reserva regular pendiente cuyo intervalo fue ocupado.'];
        if($a->estado_agenda!=='PENDIENTE_CONFIRMACION'||$a->tipo_agendamiento!=='REGULAR'
            ||!in_array($a->estado_cita,['PROGRAMADO','CONFIRMADO'],true)||$p['allocation_required'])return $offer;
        if(!Doctor::whereKey($a->doctor_id)->where('estado','ACTIVO')->exists())return $offer;
        $slots=app(AppointmentSlotValidator::class);$date=substr($a->fecha_cita,0,10);$time=substr($a->hora_cita,0,5);
        try{$slots->assertValid($a->doctor_id,$date,$time,$a->duracion_cita,$a->site_id,$a->id,true,true);}
        catch(AppointmentSlotUnavailableException $e){$offer['reason']='El horario cambió. Reprograma la reserva; no se convertirá automáticamente.';return $offer;}
        try{$slots->assertUnoccupied($a->doctor_id,$date,$time,$a->duracion_cita,$a->id);return $offer;}
        catch(AppointmentSlotUnavailableException $e){/* Global conflict, without any other patient's identity. */}
        $offer['available']=true;$offer['can_convert']=$actor->can(C::CREATE_ADDITIONAL)&&$actor->can(C::UPDATE)&&$actor->can(C::CREATE)&&$actor->can(C::VIEW);
        $offer['reason']=$offer['can_convert']?'El intervalo fue ocupado. Puedes conservar esta misma cita como ADICIONAL mediante confirmación expresa.':'La reserva se conserva pendiente de resolución. Convertir requiere appointment.additional.create y appointment.update.';
        return $offer;
    }
    public function convert(int $id,User $actor,array $data):Appointment{
        $this->authorize($actor);
        $original=Appointment::visibleToAgendaUser($actor->id)->whereKey($id)->firstOr(fn()=>abort(404));
        return DB::transaction(function()use($id,$actor,$data,$original){
            $hash=hash('sha256',json_encode(['CONVERT_ADDITIONAL',$id,$data]));
            DB::table('appointment_operations')->upsert([['actor_user_id'=>$actor->id,'request_key'=>$data['request_key'],'payload_hash'=>$hash,
                'created_at'=>now(),'updated_at'=>now()]],['actor_user_id','request_key'],['request_key']);
            $op=DB::table('appointment_operations')->where('actor_user_id',$actor->id)->where('request_key',$data['request_key'])->lockForUpdate()->first();
            abort_unless(hash_equals($op->payload_hash,$hash),409,'Esta solicitud ya corresponde a otra operación.');
            if($op->appointment_id){$this->authorize($actor->fresh());return Appointment::visibleToAgendaUser($actor->id)->whereKey($op->appointment_id)->firstOr(fn()=>abort(404));}
            Doctor::whereKey($original->doctor_id)->lockForUpdate()->firstOrFail();
            $a=Appointment::visibleToAgendaUser($actor->id)->whereKey($id)->lockForUpdate()->firstOr(fn()=>abort(404));
            // First permission snapshot inside this transaction follows the financial doctor lock.
            $this->authorize(User::whereKey($actor->id)->lockForUpdate()->first());
            abort_unless((int)$a->doctor_id===(int)$original->doctor_id,409,'La cita cambió; actualiza antes de continuar.');
            $offer=$this->offer($a,$actor);
            abort_unless(hash_equals($offer['expected_version'],$data['expected_version']),409,'La reserva o su situación económica cambiaron. Revisa nuevamente las condiciones.');
            abort_unless($offer['available']&&$offer['can_convert'],409,$offer['reason']);
            $a->update(['tipo_agendamiento'=>'ADICIONAL','updated_by_user_id'=>$actor->id]);
            app(AppointmentHistory::class)->record($a,'RESERVA_CONVERTIDA_ADICIONAL',$actor->id,['metadata'=>[
                'operation_id'=>$op->id,'from'=>'REGULAR','to'=>'ADICIONAL','price_preserved'=>$offer['price'],
                'minimum_advance'=>$offer['minimum_advance'],'money_registered'=>false,'booking_confirmed'=>false]]);
            DB::table('appointment_operations')->where('id',$op->id)->update(['appointment_id'=>$a->id,'updated_at'=>now()]);
            return $a;
        },3);
    }
    private function authorize(?User $actor):void{abort_unless($actor&&$actor->can(C::VIEW)&&$actor->can(C::CREATE)&&$actor->can(C::UPDATE)&&$actor->can(C::CREATE_ADDITIONAL),403);}
}
