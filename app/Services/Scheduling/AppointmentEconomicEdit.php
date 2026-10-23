<?php
namespace App\Services\Scheduling;

use App\Models\{Appointment,AppointmentEvent,CashierShift,Doctor,Payment,User,Voucher,VoucherItem};
use App\Services\Billing\AppointmentEconomicPosition;
use App\Support\Billing\{BankPaymentIdentity,Money};
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Support\Facades\{DB,Validator};
use Illuminate\Validation\{Rule,ValidationException};

/** Direct, reviewed edits. Reuses existing operation keys and append-only appointment events. */
final class AppointmentEconomicEdit
{
    public const EVENT='ECONOMIA_CITA_EDITADA';

    public static function allowed(User $actor,string $kind):bool
    {
        $actor=$actor->fresh();
        // The permission alone is not sufficient: economic corrections belong only to ADMINISTRADOR.
        return $actor && $actor->is_active && $actor->hasRole('ADMINISTRADOR')
            && $actor->can(C::MVP_ACCESS) && $actor->can(C::VIEW)
            && $actor->can($kind==='PAYMENT'?C::EDIT_PAYMENT:C::EDIT_PRICING);
    }

    private function find(int $id,User $actor):Appointment
    {
        abort_unless($actor->fresh()?->is_active && $actor->fresh()->can(C::MVP_ACCESS) && $actor->fresh()->can(C::VIEW),403);
        return Appointment::visibleToAgendaUser($actor->id)->whereKey($id)->firstOr(fn()=>abort(404));
    }

    private function authorize(int $id,User $actor,string $kind):Appointment
    {
        abort_unless(self::allowed($actor,$kind),403);
        return $this->find($id,$actor);
    }

    private function validate(array $input):array
    {
        $d=Validator::make($input,[
            'kind'=>['required',Rule::in(['PAYMENT','PRICING'])],'expected_revision'=>'required|string|size:64',
            'reason'=>'required|string|min:5|max:2000','payment_id'=>'required_if:kind,PAYMENT|prohibited_unless:kind,PAYMENT|nullable|integer|min:1',
            'error_type'=>['required_if:kind,PAYMENT','prohibited_unless:kind,PAYMENT',Rule::in(['amount','reference','both'])],
            'amount'=>'required_if:error_type,amount,both|prohibited_unless:kind,PAYMENT|nullable|numeric|min:0.01|max:99999999.99|regex:/\A[0-9]+(?:\.[0-9]{1,2})?\z/',
            'operation'=>'required_if:error_type,reference,both|prohibited_unless:kind,PAYMENT|nullable|string|max:120',
            'pricing_action'=>'required_if:kind,PRICING|prohibited_unless:kind,PRICING|in:CONTRACT,DISCOUNT,SURCHARGE',
            'base_price'=>'prohibited_unless:kind,PRICING|nullable|numeric|min:0.01|max:99999999.99|regex:/\A[0-9]+(?:\.[0-9]{1,2})?\z/',
            'additional_rate_id'=>'prohibited_unless:kind,PRICING|nullable|integer|min:1','discount'=>'prohibited_unless:kind,PRICING|nullable|array:type,value,reason',
            'discount.type'=>'nullable|in:FIXED,PERCENT','discount.value'=>'nullable|numeric|min:0|max:99999999.99',
            'discount.reason'=>'nullable|string|max:500',
        ])->validate();
        $d['reason']=\App\Support\Scheduling\OperationalText::trim($d['reason']);
        if(mb_strlen($d['reason'])<5)throw ValidationException::withMessages(['reason'=>'Registra un motivo suficiente.']);
        return $d;
    }

    private function proposal(Appointment $a,User $actor,array $d,array $snapshot,bool $lock=false):array
    {
        if($d['kind']==='PRICING')return app(ExistingAppointmentPricing::class)->build($a,$d,$lock);
        $payment=Payment::whereKey($d['payment_id'])->whereIn('id',$snapshot['position']['payment_ids'])
            ->whereHas('voucher',fn($q)=>$q->visibleToAgendaUser($actor->id))
            ->firstOr(fn()=>abort(404));
        // Record visibility is checked for the editing actor by authorize; blockers reject shared vouchers.
        if($payment->metodo_pago==='EFECTIVO' && $d['error_type']!=='amount')throw ValidationException::withMessages(['operation'=>'Un pago en efectivo no tiene referencia bancaria.']);
        $out=['error_type'=>$d['error_type'],'payment_id'=>(int)$payment->id,
            'amount'=>in_array($d['error_type'],['amount','both'],true)?Money::decimal(Money::cents($d['amount'])):Money::decimal(Money::cents($payment->monto)),
            'operation'=>in_array($d['error_type'],['reference','both'],true)?trim($d['operation']):$payment->numero_operacion];
        $this->assertReference($payment,$out['operation']);
        if(Money::cents($out['amount'])===Money::cents($payment->monto) && $out['operation']===$payment->numero_operacion)
            throw ValidationException::withMessages(['amount'=>'No hay cambios propuestos.']);
        return $out;
    }

    private function comparison(array $s,string $kind,array $proposal):array
    {
        $price=$s['position']['price_cents'];$paid=$s['position']['paid_cents'];
        $before=['price'=>Money::decimal($price),'paid'=>Money::decimal($paid),'balance'=>Money::decimal($s['position']['balance_cents'])];
        if($kind==='PRICING')$price=Money::cents($proposal['price']);
        else{
            $payment=collect($s['payments'])->firstWhere('id',$proposal['payment_id']);
            $before+=['payment_amount'=>$payment['monto'],'operation'=>$payment['numero_operacion']];
            $paid=$paid-Money::cents($payment['monto'])+Money::cents($proposal['amount']);
        }
        return ['before'=>$before,'after'=>['price'=>Money::decimal($price),'paid'=>Money::decimal($paid),
            'balance'=>Money::decimal(max(0,$price-$paid)),'excess'=>Money::decimal(max(0,$paid-$price))]];
    }

    private function token(int $id,int $actor,array $d,array $proposal,string $hash,int $expires):string
    {
        return hash_hmac('sha256',json_encode(['DIRECT_ECONOMY_V1',$id,$actor,$d,$proposal,$hash,$expires],JSON_THROW_ON_ERROR),config('app.key'));
    }

    public function review(int $id,User $actor,array $input):array
    {
        $d=$this->validate($input);$a=$this->authorize($id,$actor,$d['kind']);$s=$this->snapshot($a);
        abort_unless(hash_equals(AppointmentManagement::revision($a),$d['expected_revision']),409,'La cita cambió. Actualiza antes de revisar.');
        $proposal=$this->proposal($a,$actor,$d,$s);$hash=EconomicCorrectionSnapshot::hash($s);$expires=now()->timestamp+600;
        $blocked=$this->blockers($a,$d['kind'],$proposal,$d['payment_id']??null,$s);
        return $this->comparison($s,$d['kind'],$proposal)+['proposal'=>$proposal,'blockers'=>$blocked,'can_save'=>!$blocked,
            'snapshot_hash'=>$hash,'expires_at'=>$expires,'review_token'=>$this->token($id,$actor->id,$d,$proposal,$hash,$expires)];
    }

    public function save(int $id,User $actor,array $input):array
    {
        $d=$this->validate($input);$original=$this->authorize($id,$actor,$d['kind']);
        $protocol=Validator::make($input,['request_key'=>'required|uuid','confirmed'=>'required|accepted',
            'snapshot_hash'=>'required|string|size:64','review_token'=>'required|string|size:64','expires_at'=>'required|integer'])->validate();
        $hash=EconomicCorrectionSnapshot::hash(['DIRECT_ECONOMY_V1',$id,$d,$protocol]);
        try{return DB::transaction(function()use($id,$actor,$d,$protocol,$original,$hash){
            DB::table('appointment_operations')->upsert([['actor_user_id'=>$actor->id,'request_key'=>$protocol['request_key'],
                'payload_hash'=>$hash,'created_at'=>now(),'updated_at'=>now()]],['actor_user_id','request_key'],['request_key']);
            $op=DB::table('appointment_operations')->where('actor_user_id',$actor->id)->where('request_key',$protocol['request_key'])->lockForUpdate()->first();
            abort_unless(hash_equals($op->payload_hash,$hash),409,'La clave ya corresponde a otra operación.');
            if($op->appointment_id){
                abort_unless((int)$op->appointment_id===$id,409);
                $event=AppointmentEvent::where('appointment_id',$id)->where('event_type',self::EVENT)->where('metadata->operation_id',$op->id)->firstOr(fn()=>abort(409));
                return ['event_id'=>$event->id,'operation_id'=>$op->id,'replayed'=>true];
            }
            $a=$this->lockContext($original,$actor);abort_unless(self::allowed($actor,$d['kind']),403);
            $before=$this->snapshot($a);$currentHash=EconomicCorrectionSnapshot::hash($before);
            abort_unless(hash_equals(AppointmentManagement::revision($a),$d['expected_revision']) && hash_equals($protocol['snapshot_hash'],$currentHash),409,'Los datos financieros cambiaron. Revisa de nuevo; no se guardó el cambio.');
            abort_if(now()->timestamp>$protocol['expires_at'],409,'La revisión venció. Vuelve a revisar los cambios.');
            $proposal=$this->proposal($a,$actor,$d,$before,true);
            abort_unless(hash_equals($protocol['review_token'],$this->token($id,$actor->id,$d,$proposal,$currentHash,$protocol['expires_at'])),409,'La comparación ya no coincide con los cambios. Revisa de nuevo.');
            $blocked=$this->blockers($a,$d['kind'],$proposal,$d['payment_id']??null,$before);
            if($blocked)throw ValidationException::withMessages(['economy'=>implode(' ',$blocked)]);
            if($d['kind']==='PAYMENT'){
                $payment=Payment::findOrFail($d['payment_id']);$this->assertReference($payment,$proposal['operation']);
                $payment->update(['monto'=>$proposal['amount'],'numero_operacion'=>$proposal['operation']]);
            }else{
                $discount=$proposal['discount'];$price=Money::cents($proposal['price']);$base=intdiv($price*100+59,118);$igv=$price-$base;
                $a->update(['precio_programado'=>$proposal['price'],'catalog_price'=>$proposal['gross_price'],
                    'additional_rate_id'=>$proposal['additional_rate_id'],'discount_type'=>$discount['type'],'discount_value'=>$discount['value'],
                    'discount_amount'=>Money::decimal($discount['amount_cents']),'discount_reason'=>$discount['reason'],
                    'discount_actor_user_id'=>$discount['amount_cents']?$actor->id:null]);
                if($before['vouchers']){
                    VoucherItem::findOrFail($before['items'][0]['id'])->update(['precio_unitario'=>Money::decimal($price),'total'=>Money::decimal($price),'igv_monto'=>Money::decimal($igv)]);
                    Voucher::findOrFail($before['vouchers'][0]['id'])->update(['subtotal'=>Money::decimal($base),'total_gravado'=>Money::decimal($base),'igv'=>Money::decimal($igv),'total'=>Money::decimal($price)]);
                }
            }
            $p=app(AppointmentEconomicPosition::class)->forAppointment($a->fresh(),true);
            $state=$p['paid_cents']===0?'PENDIENTE':($p['balance_cents']===0?'PAGADO':'PARCIAL');
            $a->update(['total_pagado'=>Money::decimal($p['paid_cents']),'saldo_pendiente'=>Money::decimal($p['balance_cents']),'estado_pagado'=>$state,'updated_by_user_id'=>$actor->id]);
            foreach($before['vouchers'] as $v)Voucher::findOrFail($v['id'])->update(['estado'=>$state]);
            $event=app(AppointmentHistory::class)->record($a,self::EVENT,$actor->id,['motivo'=>$d['reason']]);
            $after=$this->snapshot($a->fresh());
            // The event's final payload is completed before commit. No committed history is overwritten.
            $event->update(['metadata'=>['version'=>1,'operation_id'=>$op->id,'request_key'=>$protocol['request_key'],'kind'=>$d['kind'],
                'payment_id'=>$d['payment_id']??null,'proposal'=>$proposal,'before'=>$before,'after'=>$after,
                'before_hash'=>$currentHash,'after_hash'=>EconomicCorrectionSnapshot::hash($after)]]);
            DB::table('appointment_operations')->where('id',$op->id)->update(['appointment_id'=>$id,'updated_at'=>now()]);
            return ['event_id'=>$event->id,'operation_id'=>$op->id,'replayed'=>false];
        },3);}catch(\Illuminate\Database\DeadlockException $e){
            abort(409,'Coincidió otra operación. Reintenta con la misma clave o revisa de nuevo.');
        }catch(\Illuminate\Database\QueryException $e){
            if(in_array((int)($e->errorInfo[1]??0),[1020,1205,1213],true))abort(409,'Coincidió otra operación. Reintenta con la misma clave o revisa de nuevo.');
            throw $e;
        }
    }

    public function payload(int $id,User $actor,?int $before=null):array
    {
        $a=$this->find($id,$actor);$pay=self::allowed($actor,'PAYMENT');$price=self::allowed($actor,'PRICING');
        abort_unless($pay||$price||$actor->fresh()->can(C::VIEW_AUDIT),403);
        $s=$this->snapshot($a);
        $events=AppointmentEvent::where('appointment_id',$id)->where('event_type',self::EVENT)->when($before,fn($q)=>$q->where('id','<',$before))
            ->when(!$actor->can(C::VIEW_AUDIT),fn($q)=>$q->whereIn('metadata->kind',array_values(array_filter([$pay?'PAYMENT':null,$price?'PRICING':null]))))
            ->orderByDesc('id')->limit(31)->get();
        return ['revision'=>AppointmentManagement::revision($a),'can_payment'=>$pay,'can_pricing'=>$price,
            'pricing_context'=>app(ExistingAppointmentPricing::class)->context($a),
            'price'=>Money::decimal($s['position']['price_cents']),'paid'=>Money::decimal($s['position']['paid_cents']),
            'balance'=>Money::decimal($s['position']['balance_cents']),'gross_price'=>$a->catalog_price??$a->precio_programado,
            'discount'=>$a->only(['discount_type','discount_value','discount_reason']),'additional_rate_id'=>$a->additional_rate_id,
            'payments'=>$pay?Payment::whereIn('id',$s['position']['payment_ids'])->whereHas('voucher',fn($q)=>$q->visibleToAgendaUser($actor->id))->get()
                ->map(fn($p)=>['id'=>$p->id,'amount'=>$p->monto,'operation'=>$p->numero_operacion,'method'=>$p->metodo_pago])->values()->all():[],
            'rates'=>\App\Models\AdditionalRate::where('estado','ACTIVO')->orderBy('nombre')->get(['id','nombre','tipo_tarifa','tarifa']),
            'history'=>$events->take(30)->map(fn($e)=>['id'=>$e->id,'actor_user_id'=>$e->actor_user_id,'at'=>$e->occurred_at->toISOString(),
                'reason'=>$e->motivo,'kind'=>$e->metadata['kind'],'payment_id'=>$e->metadata['payment_id'],'operation_id'=>$e->metadata['operation_id'],
                'proposal'=>$e->metadata['proposal'],'comparison'=>$this->comparison($e->metadata['before'],$e->metadata['kind'],$e->metadata['proposal'])])->values(),
            'history_more'=>$events->count()>30,'history_before'=>$events->count()>30?$events[29]->id:null];
    }
    private function lockContext(Appointment $original, User $actor): Appointment
    {
        $ids=VoucherItem::whereIn('item_type',['cita',Appointment::class])->where('item_id',$original->id)->pluck('voucher_id');
        $vouchers=Voucher::whereIn('id',$ids)->orWhereIn('parent_voucher_id',$ids)->get();
        $shifts=$vouchers->pluck('cashier_shift_id')->merge(Payment::whereIn('voucher_id',$vouchers->pluck('id'))->pluck('cashier_shift_id'))->unique()->sort();
        CashierShift::whereIn('id',$shifts)->orderBy('id')->lockForUpdate()->get();
        Doctor::whereKey($original->doctor_id)->lockForUpdate()->firstOrFail();
        $a=Appointment::visibleToAgendaUser($actor->id)->whereKey($original->id)->lockForUpdate()->firstOr(fn()=>abort(404));
        abort_unless((int)$original->doctor_id===(int)$a->doctor_id,409,'La cita cambió. Actualiza la cita.');
        Voucher::whereIn('id',$vouchers->pluck('id'))->orderBy('id')->lockForUpdate()->get();
        VoucherItem::whereIn('voucher_id',$vouchers->pluck('id'))->orderBy('id')->lockForUpdate()->get();
        Payment::whereIn('voucher_id',$vouchers->pluck('id'))->orderBy('id')->lockForUpdate()->get();
        return $a;
    }

    private function snapshot(Appointment $a): array
    {
        $lock=DB::transactionLevel()>0;
        $ids=VoucherItem::whereIn('item_type',['cita',Appointment::class])->where('item_id',$a->id)->when($lock,fn($q)=>$q->lockForUpdate())->pluck('voucher_id');
        $vouchers=DB::table('vouchers')->whereIn('id',$ids)->orWhereIn('parent_voucher_id',$ids)->orderBy('id')->when($lock,fn($q)=>$q->lockForUpdate())->get();
        $payments=DB::table('payments')->whereIn('voucher_id',$vouchers->pluck('id'))->orderBy('id')->when($lock,fn($q)=>$q->lockForUpdate())->get();
        $raw = [
            // Detect any intervening appointment/history edit without duplicating clinical notes in financial audit.
            'appointment_revision'=>AppointmentManagement::revision($a),
            'appointment'=>array_intersect_key($a->getRawOriginal(),array_flip(['id','patient_id','doctor_id','service_id','site_id',
                'fecha_cita','hora_cita','estado_cita','estado_agenda','economic_source','precio_programado','total_pagado','saldo_pendiente','estado_pagado',
                'catalog_price','discount_type','discount_value','discount_amount','discount_reason','discount_actor_user_id','additional_rate_id',
                'pricing_rule','commercial_service_id','referral_origin','es_exonerado','autorizado_por','numero_operacion','updated_at'])),
            'vouchers'=>$vouchers->map(fn($v)=>(array)$v)->all(),
            'items'=>DB::table('voucher_items')->whereIn('voucher_id',$vouchers->pluck('id'))->orderBy('id')->when($lock,fn($q)=>$q->lockForUpdate())->get()->map(fn($v)=>(array)$v)->all(),
            'payments'=>$payments->map(fn($v)=>(array)$v)->all(),
            'shifts'=>DB::table('cashier_shifts')->whereIn('id',$vouchers->pluck('cashier_shift_id')->merge($payments->pluck('cashier_shift_id')))->orderBy('id')->when($lock,fn($q)=>$q->lockForUpdate())->get()->map(fn($v)=>(array)$v)->all(),
            'credits'=>DB::table('appointment_credit_applications')->where(fn($q)=>$q->where('source_appointment_id',$a->id)->orWhere('destination_appointment_id',$a->id))->orderBy('id')->when($lock,fn($q)=>$q->lockForUpdate())->get()->all(),
            'refunds'=>DB::table('appointment_refund_requests')->where('appointment_id',$a->id)->orderBy('id')->when($lock,fn($q)=>$q->lockForUpdate())->get()->all(),
            'documents'=>DB::table('appointment_documents')->where('appointment_id',$a->id)->orderBy('id')->when($lock,fn($q)=>$q->lockForUpdate())->get()->all(),
            'position'=>app(AppointmentEconomicPosition::class)->forAppointment($a,$lock)];
        return app(EconomicCorrectionSnapshot::class)->minimize($raw);
    }

    private function assertReference(Payment $payment, ?string $operation): void
    {
        $key=BankPaymentIdentity::key($payment->metodo_pago,$payment->entidad_origen,$operation);
        if($key===null)return;
        $others=Payment::where('id','<>',$payment->id)->where(fn($q)=>$q->where('bank_identity_key',$key)
            ->orWhere(fn($legacy)=>$legacy->whereNull('bank_identity_key')->whereRaw('UPPER(TRIM(numero_operacion)) = ?',[BankPaymentIdentity::normalized($operation)])))->get();
        foreach($others as $other){
            if($other->bank_identity_key===$key || !in_array($other->metodo_pago,BankPaymentIdentity::METHODS,true)
                || ($other->metodo_pago===$payment->metodo_pago && (BankPaymentIdentity::entity($other->metodo_pago,$other->entidad_origen)===null
                    || BankPaymentIdentity::entity($other->metodo_pago,$other->entidad_origen)===BankPaymentIdentity::entity($payment->metodo_pago,$payment->entidad_origen))))throw BankPaymentIdentity::duplicate();
        }
    }

    private function blockers(Appointment $a, string $kind, array $proposal, ?int $paymentId, array $s): array
    {
        $b=[];$p=$s['position'];
        if($p['price_cents']<=0 || collect($s['payments'])->contains(fn($payment)=>Money::cents($payment['monto'])<=0))$b[]='Precio o pagos no positivos: revisar la conciliación antes de modificar.';
        if($p['allocation_required'] || ($p['authority']!=='PAYMENTS' && $p['paid_cents']>0))$b[]='Atribución ambigua o pago histórico sin comprobante: conciliar en Caja.';
        if(in_array($a->estado_cita,['CANCELADO','RETIRO','NO_ASISTIO','ATENDIDO','EN_ATENCION'],true))$b[]='La cita está anulada, retirada o no asistió.';
        if($a->hora_atencion || $a->hora_atendido || $a->hora_salida)$b[]='La atención ya comenzó o terminó; utiliza el flujo financiero correspondiente.';
        if($s['credits'] || $s['refunds'])$b[]='Existen créditos o devoluciones; se requiere su flujo financiero.';
        if($a->es_exonerado || $a->commercial_service_id || $a->referral_origin || ($a->pricing_rule && $a->pricing_rule!=='NORMAL')
           )$b[]='Contrato especial o regla clínica: utiliza su flujo económico específico.';
        foreach($s['documents'] as $document){
            $doc=(array)$document;
            // A reviewed receipt image is evidence, not a derived fiscal document. Preserve it intact.
            $receipt=$doc['type']==='PAYMENT_PROOF' && in_array((int)($doc['payment_id']??0),array_map('intval',array_column($s['payments'],'id')),true)
                && !empty($doc['reviewed_by_user_id']) && !empty($doc['reviewed_at']) && !empty($doc['payment_proof_hash'])
                && empty($doc['deleted_at']) && empty($doc['replaces_document_id']);
            if(!$receipt)$b[]='Existen documentos de la cita; revisar su efecto económico antes de aplicar.';
            elseif(!$doc['integrity_valid'])$b[]='El comprobante original adjunto está ausente o modificado; requiere revisión.';
        }
        if(count($s['vouchers'])>1)$b[]='Varios comprobantes o documentos derivados: requiere Caja/Contabilidad.';
        foreach($s['shifts'] as $shift)if($shift['estado']!=='ABIERTO' || $shift['cerrado_en'] || $shift['monto_sistema']!==null || $shift['monto_contado']!==null)$b[]='Turno cerrado o en conciliación: no se puede modificar automáticamente.';
        foreach($s['vouchers'] as $v){
            if($v['tipo_comprobante']!=='TICKET' || $v['estado']==='ANULADO' || $v['parent_voucher_id'] || $v['requiere_sunat']
                || $v['estado_sunat']!=='NO_APLICA' || $v['has_fiscal_artifacts']
                || $v['aplica_detraccion'] || Money::cents($v['total_exonerado']) || Money::cents($v['total_inafecto']))$b[]='Comprobante fiscal, anulado o tratamiento contable no soportado.';
            $items=array_values(array_filter($s['items'],fn($i)=>(int)$i['voucher_id']===(int)$v['id']));
            if(count($items)!==1 || !in_array($items[0]['item_type'],['cita',Appointment::class],true) || (int)$items[0]['item_id']!==(int)$a->id
                || (float)$items[0]['cantidad']!==1.0 || $items[0]['afectacion_igv']!=='10' || (float)$items[0]['comision_porcentaje']!==0.0 || (float)$items[0]['comision_monto']!==0.0
                || Money::cents($items[0]['total'])!==$p['price_cents'] || Money::cents($items[0]['precio_unitario'])!==$p['price_cents']
                || Money::cents($v['total'])!==$p['price_cents'] || (int)$v['patient_id']!==(int)$a->patient_id)$b[]='Las líneas, el paciente o los importes del ticket no corresponden al contrato.';
            $base=(int)round($p['price_cents']/1.18);
            if(Money::cents($v['subtotal'])!==$base || Money::cents($v['total_gravado'])!==$base || Money::cents($v['igv'])!==$p['price_cents']-$base
                || (count($items)===1 && Money::cents($items[0]['igv_monto'])!==$p['price_cents']-$base))$b[]='El tratamiento de IGV del ticket necesita conciliación.';
        }
        if(count($s['shifts'])!==($s['vouchers']?1:0))$b[]='No se verifica un único turno de origen para ticket y pagos.';
        if(Money::cents($a->total_pagado??0)!==$p['paid_cents'] || Money::cents($a->saldo_pendiente??0)!==$p['balance_cents'])$b[]='Los acumulados históricos no coinciden con los pagos reales.';
        if($kind==='PAYMENT'){
            $payment=collect($s['payments'])->firstWhere('id',$paymentId);
            if(!$payment || !in_array((int)$paymentId,$p['payment_ids'],true))$b[]='No se pudo atribuir individualmente el pago a la cita.';
            else{
                if($payment['metodo_pago']!=='EFECTIVO' && !in_array($payment['metodo_pago'],BankPaymentIdentity::METHODS,true))$b[]='Medio de pago heredado sin identidad verificable.';
                $paid=$p['paid_cents']-Money::cents($payment['monto'])+Money::cents($proposal['amount']);
                if($paid>$p['price_cents'])$b[]='El pago corregido superaría el precio: requiere flujo de excedentes, créditos o devolución.';
            }
        }elseif(Money::cents($proposal['price'])<$p['paid_cents'])$b[]='El precio propuesto es inferior al monto pagado: requiere flujo de excedentes, créditos o devolución.';
        return array_values(array_unique($b));
    }

}
