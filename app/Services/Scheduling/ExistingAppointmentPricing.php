<?php
namespace App\Services\Scheduling;

use App\Models\{Appointment,AppointmentEvent};
use App\Support\Billing\Money;
use Illuminate\Validation\ValidationException;

/** Changes one appointment's contract; never quotes a new booking or changes the global catalog. */
final class ExistingAppointmentPricing
{
    public function context(Appointment $a):array
    {
        $event=AppointmentEvent::where('appointment_id',$a->id)->where('event_type',AppointmentEconomicEdit::EVENT)
            ->where('metadata->kind','PRICING')->orderByDesc('id')->first();
        $proposal=$event?->metadata['proposal']??null;
        if($proposal && Money::cents($proposal['gross_price'])===Money::cents($a->catalog_price??$a->precio_programado)
            && (int)$proposal['additional_rate_id']===(int)$a->additional_rate_id)
            return ['contract_base'=>$proposal['contract_base'],'surcharge'=>$proposal['surcharge'],'base_known'=>$proposal['contract_base']!==null];
        $rate=$a->additionalRate;
        $known=!$a->additional_rate_id || ($rate && Money::cents($rate->tarifa)===0);
        return ['contract_base'=>$known?Money::decimal(Money::cents($a->catalog_price??$a->precio_programado)):null,
            'surcharge'=>null,'base_known'=>$known];
    }

    public function build(Appointment $a,array $d,bool $lock=false):array
    {
        $action=$d['pricing_action'];$context=$this->context($a);
        $discount=$d['discount']??($a->discount_type?['type'=>$a->discount_type,'value'=>$a->discount_value,'reason'=>$a->discount_reason]:[]);
        if($action==='DISCOUNT'){
            $base=$context['contract_base'];$gross=Money::cents($a->catalog_price??$a->precio_programado);
            $rateId=$a->additional_rate_id;$surcharge=$context['surcharge'];
            if(isset($d['base_price']) || array_key_exists('additional_rate_id',$d))
                throw ValidationException::withMessages(['pricing_action'=>'El cambio de descuento conserva la tarifa y el recargo actuales.']);
        }else{
            if($action==='CONTRACT'){
                if(!isset($d['base_price']))throw ValidationException::withMessages(['base_price'=>'Indica la tarifa base sin recargo.']);
                $base=Money::decimal(Money::cents($d['base_price']));
            }else{
                if(!$context['base_known'])throw ValidationException::withMessages(['pricing_action'=>'El recargo anterior no tiene desglose verificable. Usa corregir tarifa e indica expresamente la base y el recargo correctos.']);
                $base=$context['contract_base'];
                if(isset($d['base_price']) && Money::cents($d['base_price'])!==Money::cents($base))
                    throw ValidationException::withMessages(['base_price'=>'Modificar recargo conserva la base; utiliza corregir tarifa para cambiarla.']);
            }
            $rateId=$d['additional_rate_id']??null;$surcharge=null;$gross=Money::cents($base);
            if($rateId){
                $rate=app(AdditionalRateCharge::class)->forDate((int)$rateId,substr($a->fecha_cita,0,10),$lock);
                $amount=app(AdditionalRateCharge::class)->cents($gross,$rate);$gross+=$amount;
                $surcharge=['id'=>(int)$rate->id,'label'=>$rate->nombre,'type'=>$rate->tipo_tarifa,'value'=>(string)$rate->tarifa,
                    'amount'=>Money::decimal($amount),'source_digest'=>EconomicCorrectionSnapshot::hash($rate->getRawOriginal())];
            }
        }
        if($gross<1 || $gross>9999999999)throw ValidationException::withMessages(['base_price'=>'La tarifa debe ser positiva y válida.']);
        $calculated=app(AppointmentDiscount::class)->calculate($gross,$discount);
        if($calculated['amount_cents']>0 && trim((string)$calculated['reason'])==='')throw ValidationException::withMessages(['discount.reason'=>'Fundamenta el descuento.']);
        if($calculated['net_cents']===Money::cents($a->precio_programado) && $gross===Money::cents($a->catalog_price??$a->precio_programado)
            && (int)$rateId===(int)$a->additional_rate_id && $calculated['type']===$a->discount_type
            && Money::cents($calculated['value']??0)===Money::cents($a->discount_value??0) && $calculated['reason']===$a->discount_reason)
            throw ValidationException::withMessages(['base_price'=>'No hay cambios económicos propuestos.']);
        return ['action'=>$action,'contract_base'=>$base,'gross_price'=>Money::decimal($gross),'additional_rate_id'=>$rateId?(int)$rateId:null,
            'surcharge'=>$surcharge,'discount'=>$calculated,'price'=>Money::decimal($calculated['net_cents'])];
    }
}
