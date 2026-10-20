<?php
namespace App\Services\Scheduling;

use App\Models\{Appointment,Doctor,Patient,User,VoucherItem};
use App\Services\Catalog\ActiveDoctorServiceResolver;
use App\Support\Billing\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Narrow edits of an unencumbered booking. No contract, payments or clinical records are rewritten. */
final class AppointmentQuickEdit
{
    public function find(int $id, User $actor): Appointment
    {
        abort_unless($actor->is_active,403);
        return Appointment::visibleToOperationalUser($actor)->whereKey($id)->firstOr(fn()=>abort(404));
    }

    public function blockers(Appointment $a, User $actor): array
    {
        $reasons=[];
        if(!AppointmentManagement::canEdit($a,$actor)) $reasons[]=['code'=>'PERMISSION','message'=>'Tu acceso permite consultar esta cita, pero no editarla.'];
        if(!in_array($a->estado_cita,['PROGRAMADO','CONFIRMADO'],true)
            || collect(['hora_llegada','hora_llamado','hora_atencion','hora_atendido','hora_salida'])->contains(fn($f)=>(bool)$a->$f))
            $reasons[]=['code'=>'CARE','message'=>'La cita ya tiene atención o presencia registrada, o un estado que impide la edición rápida.'];
        $voucherIds=VoucherItem::whereIn('item_type',['cita',Appointment::class])->where('item_id',$a->id)->pluck('voucher_id');
        $family=DB::table('vouchers')->whereIn('id',$voucherIds)->orWhereIn('parent_voucher_id',$voucherIds)->pluck('id');
        if(DB::table('payments')->whereIn('voucher_id',$family)->exists() || Money::cents($a->total_pagado??0)!==0)
            $reasons[]=['code'=>'PAYMENT','message'=>'Esta cita tiene un adelanto o pago registrado. Debe resolverse primero su vínculo económico.'];
        if(trim((string)$a->numero_operacion)!=='' || $a->events()->whereIn('event_type',['PAGO_REGISTRADO','DEVOLUCION_SOLICITADA','CREADA_DESDE_RETIRO','REPROGRAMACION_RETIRO'])->exists())
            $reasons[]=['code'=>'FINANCIAL_HISTORY','message'=>'La cita conserva una referencia bancaria o historial financiero que requiere revisión antes de cambiar paciente o servicio.'];
        if($voucherIds->isNotEmpty()) $reasons[]=['code'=>'VOUCHER','message'=>'No se puede realizar esta edición rápida porque existe un comprobante asociado, incluso si fue anulado.'];
        if(DB::table('appointment_documents')->where('appointment_id',$a->id)->exists())
            $reasons[]=['code'=>'DOCUMENT','message'=>'La cita tiene documentos o versiones documentales que deben conservar su paciente y servicio originales.'];
        if(DB::table('appointment_requested_procedures')->where('appointment_id',$a->id)->exists()
            || DB::table('appointment_procedure_events')->where('appointment_id',$a->id)->exists()
            || DB::table('appointment_operational_reviews')->where('appointment_id',$a->id)->where(fn($q)=>$q->where('version','>',0)->orWhereNotNull('origin'))->exists())
            $reasons[]=['code'=>'PROCEDURE','message'=>'La cita tiene solicitudes, realizaciones o historial de procedimientos/reevaluación.'];
        if(DB::table('appointment_reevaluation_indications')->where('source_appointment_id',$a->id)->orWhere('bound_appointment_id',$a->id)->exists())
            $reasons[]=['code'=>'CLINICAL','message'=>'La cita está vinculada a una indicación de reevaluación clínica.'];
        if(DB::table('appointment_admission_tasks')->where('appointment_id',$a->id)->exists()
            || $a->events()->where(fn($q)=>$q->where('event_type','like','ADMISION_%')->orWhere('event_type','like','REEVALUACION_%'))->exists())
            $reasons[]=['code'=>'HISTORY','message'=>'La cita tiene actividad de atención o reevaluación que impide transferirla mediante edición rápida.'];
        if(DB::table('appointment_credit_applications')->where('source_appointment_id',$a->id)->orWhere('destination_appointment_id',$a->id)->exists()
            || DB::table('appointment_refund_requests')->where('appointment_id',$a->id)->exists())
            $reasons[]=['code'=>'CREDIT_REFUND','message'=>'La cita está vinculada a créditos o devoluciones.'];
        if(Money::cents($a->precio_programado)<=0 || Money::cents($a->saldo_pendiente??0)!==Money::cents($a->precio_programado)
            || !in_array($a->estado_pagado,[null,'PENDIENTE'],true))
            $reasons[]=['code'=>'ECONOMIC_REVIEW','message'=>'El saldo o precio almacenado requiere revisión económica antes de esta edición.'];
        if($a->es_exonerado || $a->referral_origin || $a->discount_type || $a->commercial_service_id
            || ($a->pricing_rule && $a->pricing_rule!=='NORMAL') || ($a->additional_rate_id && (float)$a->additionalRate?->tarifa!==0.0))
            $reasons[]=['code'=>'SPECIAL_CONTRACT','message'=>'La cita tiene una exoneración, tarifa, descuento o contrato especial que requiere su flujo autorizado.'];
        return $reasons;
    }

    public function inspect(Appointment $a, User $actor, ?int $serviceId=null, bool $lock=false): array
    {
        $blocked=$this->blockers($a,$actor);
        $options=app(ActiveDoctorServiceResolver::class)->query()->with('service:id,nombre')->where('doctor_id',$a->doctor_id)
            ->get()->groupBy('service_id')->filter(fn($rows)=>$rows->count()===1)->map(fn($rows)=>[
                'id'=>(int)$rows->first()->service_id,'name'=>$rows->first()->service->nombre])->values();
        $out=['revision'=>AppointmentManagement::revision($a),'patient_id'=>(int)$a->patient_id,
            'service_id'=>(int)$a->service_id,'current_price'=>Money::decimal(Money::cents($a->precio_programado)),
            'current_duration'=>(int)$a->duracion_cita,'blockers'=>$blocked,'services'=>$options];
        if($serviceId!==null){
            $proposal=['service_id'=>$serviceId,'price'=>null,'duration'=>(int)$a->duracion_cita,'available'=>false,'blockers'=>$blocked];
            try{
                $assignment=app(ActiveDoctorServiceResolver::class)->resolve((int)$a->doctor_id,$serviceId);
                if(($a->service->service_type??'CONSULTATION')!==($assignment->service->service_type??'CONSULTATION'))
                    $proposal['blockers'][]=['code'=>'SERVICE_KIND','message'=>'El cambio entre clases de servicio requiere revisar su alcance clínico fuera de la edición rápida.'];
                $quote=app(AppointmentPriceResolver::class)->resolve($assignment,(int)$a->patient_id,(int)$actor->id,substr($a->fecha_cita,0,10),null,[],null,[],(int)$a->id,$lock);
                $proposal['price']=$quote['precio_programado'];
                // Equality is checked against the stored contract, not just today's old catalogue price.
                if($quote['price_cents']!==Money::cents($a->precio_programado)
                    || ($a->catalog_price!==null && Money::cents($a->catalog_price)!==Money::cents($quote['catalog_price'])))
                    $proposal['blockers'][]=['code'=>'PRICE_DIFFERENCE','message'=>'El nuevo servicio cambia el precio. La recotización de citas existentes todavía no está autorizada.'];
                $this->assertSlot($a);$proposal['available']=true;
            }catch(ValidationException $e){$proposal['blockers'][]=['code'=>'CATALOG','message'=>collect($e->errors())->flatten()->first()];}
            catch(\App\Exceptions\Scheduling\AppointmentSlotUnavailableException $e){$proposal['blockers'][]=['code'=>'AVAILABILITY','message'=>$e->getMessage()];}
            $out['proposal']=$proposal;
        }
        return $out;
    }

    private function assertSlot(Appointment $a): void
    {
        $v=app(AppointmentSlotValidator::class);$date=substr($a->fecha_cita,0,10);$time=substr($a->hora_cita,0,5);
        abort_unless((int)$a->duracion_cita>0,422,'Falta una duración válida. Revisa el horario antes de cambiar de servicio.');
        if($a->tipo_agendamiento==='FUERA_HORARIO'){
            $v->assertOutsideHours($a->doctor_id,$date,$time,$a->duracion_cita);
            $v->assertUnoccupied($a->doctor_id,$date,$time,$a->duracion_cita,$a->id);
        }else $v->assertValid($a->doctor_id,$date,$time,$a->duracion_cita,$a->site_id,$a->id,$a->tipo_agendamiento==='ADICIONAL',true);
    }

    public function change(int $id, User $actor, array $data): Appointment
    {
        $original=$this->find($id,$actor);abort_unless(AppointmentManagement::canEdit($original,$actor),403);
        return DB::transaction(function()use($id,$actor,$data,$original){
            $hash=hash('sha256',json_encode(['QUICK_EDIT',$id,$data]));
            DB::table('appointment_operations')->upsert([['actor_user_id'=>$actor->id,'request_key'=>$data['request_key'],
                'payload_hash'=>$hash,'created_at'=>now(),'updated_at'=>now()]],['actor_user_id','request_key'],['request_key']);
            $op=DB::table('appointment_operations')->where('actor_user_id',$actor->id)->where('request_key',$data['request_key'])->lockForUpdate()->first();
            abort_unless(hash_equals($op->payload_hash,$hash),409,'La solicitud ya corresponde a otra operación.');
            Doctor::whereKey($original->doctor_id)->lockForUpdate()->firstOrFail();
            $a=Appointment::visibleToAgendaUser($actor->id)->whereKey($id)->lockForUpdate()->firstOr(fn()=>abort(404));
            abort_unless((int)$a->doctor_id===(int)$original->doctor_id,409,'La cita cambió de profesional.');
            if($op->appointment_id)return $a;
            abort_unless(hash_equals(AppointmentManagement::revision($a),$data['expected_revision']),409,'La cita cambió en otra sesión. Se conservan tus cambios; actualiza antes de confirmar.');
            $blocked=$this->blockers($a,$actor);
            if($blocked)throw ValidationException::withMessages(['appointment'=>array_column($blocked,'message')]);
            if($data['action']==='patient'){
                $patient=Patient::whereKey($data['patient_id'])->where('estado','ACTIVO')->lockForUpdate()->firstOr(fn()=>abort(422,'Selecciona un paciente activo registrado.'));
                abort_if((int)$patient->id===(int)$a->patient_id,422,'Selecciona un paciente diferente.');
                if($patient->fecha_nacimiento && \Carbon\Carbon::parse($patient->fecha_nacimiento)->age<18
                    && !$patient->responsibles()->where('estado','ACTIVO')->exists())
                    throw ValidationException::withMessages(['patient_id'=>'Completa el responsable adulto del paciente menor de edad antes de asignarlo.']);
                // Archive in the existing protected event metadata. Never copy the former patient's clinical text or flags.
                $from=$a->only(['patient_id','motivo_consulta','observaciones','is_postoperative','has_diabetic_wound']);
                $a->update(['patient_id'=>$patient->id,'motivo_consulta'=>$data['motivo_consulta'],'observaciones'=>null,
                    'is_postoperative'=>false,'has_diabetic_wound'=>false,'updated_by_user_id'=>$actor->id]);
                app(AppointmentHistory::class)->record($a,'PACIENTE_REASIGNADO',$actor->id,['motivo'=>$data['motivo'],
                    'metadata'=>['from'=>$from,'to'=>['patient_id'=>$patient->id],'operation_id'=>$op->id]]);
            }else{
                abort_if((int)$a->service_id===(int)$data['service_id'],422,'Selecciona un servicio diferente.');
                $proposal=$this->inspect($a,$actor,(int)$data['service_id'],true)['proposal'];
                if($proposal['blockers'])throw ValidationException::withMessages(['service_id'=>array_column($proposal['blockers'],'message')]);
                $from=$a->only(['service_id','duracion_cita','precio_programado']);
                $a->update(['service_id'=>$data['service_id'],'updated_by_user_id'=>$actor->id]);
                app(AppointmentHistory::class)->record($a,'SERVICIO_CORREGIDO',$actor->id,['motivo'=>$data['motivo'],
                    'metadata'=>['from'=>$from,'to'=>$a->only(['service_id','duracion_cita','precio_programado']),'operation_id'=>$op->id]]);
            }
            DB::table('appointment_operations')->where('id',$op->id)->update(['appointment_id'=>$id,'updated_at'=>now()]);
            return $a->fresh();
        },3);
    }
}
