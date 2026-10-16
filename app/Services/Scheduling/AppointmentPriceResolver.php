<?php
namespace App\Services\Scheduling;

use App\Models\AdditionalRate;
use App\Models\Appointment;
use App\Models\DoctorService;
use App\Support\Billing\Money;
use App\Support\Scheduling\AppointmentAgendaLifecycle;
use App\Support\Scheduling\StandardAdditionalRateResolver;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/** One catalogue policy for quotes and new appointments; never reprices persisted appointments. */
class AppointmentPriceResolver
{
    public function resolve(DoctorService $service, int $patientId, int $actorId, string $date, ?int $rateId = null, array $discount = [], ?int $indicationId = null, array $referral = [], ?int $existing = null, bool $lock = false): array
    {
        if($lock){
            \App\Models\Doctor::whereKey($service->doctor_id)->lockForUpdate()->firstOrFail();
            $service=DoctorService::whereKey($service->id)->lockForUpdate()->firstOrFail();
            \App\Models\Service::whereKey($service->service_id)->lockForUpdate()->firstOrFail();
        }
        app(\App\Services\Catalog\ServiceSpecialtyEligibility::class)->assertAllowed($service,$lock);
        // No selected surcharge means no adjustment, not a dependency on a mutable catalogue row.
        $rate = $rateId === null ? null : AdditionalRate::whereKey($rateId)->where('estado', 'ACTIVO')->first();
        if ($rateId !== null && (!$rate || ($rate->fecha_inicio && substr($rate->fecha_inicio, 0, 10) > $date)
            || ($rate->fecha_fin && substr($rate->fecha_fin, 0, 10) < $date)
            || !in_array($rate->tipo_tarifa, ['MONTO_FIJO', 'PORCENTAJE'], true) || !is_numeric($rate->tarifa) || $rate->tarifa < 0)) {
            throw ValidationException::withMessages(['additional_rate_id' => 'Selecciona una tarifa activa, válida y vigente para la fecha de la cita.']);
        }
        if ($rate && strtoupper(trim($rate->nombre)) === StandardAdditionalRateResolver::STANDARD_NAME) {
            // Explicit legacy standard selections remain checked; absence never blocks normal pricing.
            try { app(StandardAdditionalRateResolver::class)->resolve($date); }
            catch (\RuntimeException $e) { throw new \App\Exceptions\Scheduling\AppointmentConfigurationException('La tarifa estándar seleccionada requiere revisión.', 0, $e); }
        }
        $type = 'PRIMERA_CONSULTA';
        $price = null; $source = null;
        $rule='NORMAL'; $indication=null; $days=null;
        if($indicationId) {
            $eligibility=app(ReevaluationIndicationService::class)->eligible($indicationId,$patientId,(int)$service->service_id,$date,$actorId,$existing,$lock,(int)$service->doctor_id);
            $indication=$eligibility['indication']; $days=$eligibility['days'];
            if($days<=15) {
                $type='RECONSULTA'; $rule=$days===0?'SAME_DAY_REEVALUATION':'INDICATED_REEVALUATION';
            }
            $kind=\App\Models\Service::findOrFail($service->service_id)->service_type;
            if($kind!==null && $kind!=='CONSULTATION')throw ValidationException::withMessages(['reevaluation_indication_id'=>'La reevaluación corresponde al mismo servicio de consulta, no a procedimientos o documentos.']);
        }
        $cfg=config('scheduling.quiroz_referral',[]);
        $quirozService=!empty($cfg['doctor_id']) && (int)$service->doctor_id===(int)$cfg['doctor_id'] && in_array((int)$service->service_id,$cfg['service_ids']??[],true);
        $knownReferralRates=array_filter(array_map('intval',array_values($cfg['additional_rate_ids']??[])));
        $referred=!empty($referral);
        if(!$referred && $quirozService && $rate && in_array((int)$rate->id,$knownReferralRates,true)) {
            throw ValidationException::withMessages(['referral.origin'=>'Para utilizar esta tarifa identifica y acredita expresamente el origen del referido.']);
        }
        if($referred) {
            $actor=\App\Models\User::findOrFail($actorId);
            abort_unless($actor->can(\App\Support\Scheduling\SchedulingCapability::CREATE) && $actor->can(\App\Support\Scheduling\SchedulingCapability::VIEW),403);
            if(empty($cfg['doctor_id']) || (int)$service->doctor_id!==(int)$cfg['doctor_id']
                || !in_array((int)$service->service_id,$cfg['service_ids'] ?? [],true)
                || trim((string)($referral['origin'] ?? ''))==='' || empty($referral['attested'])) {
                throw ValidationException::withMessages(['referral'=>'La tarifa requiere referido acreditado y médico/servicio autorizados.']);
            }
            $price=$days===0?0:($days!==null && $days<=15?22000:25000);
            $source=$days===0?'CLINICAL_SAME_DAY':'QUIROZ_REFERRAL';
            $legacyRate=$cfg['additional_rate_ids'][(int)$service->service_id] ?? null;
            if($rate && Money::cents($rate->tarifa ?? 0)!==0 && (int)$rate->id!==(int)$legacyRate) {
                throw ValidationException::withMessages(['additional_rate_id'=>'No combines la tarifa referida con otro recargo. Usa tarifa estándar o el referido de este servicio.']);
            }
        }
        if(!$referred) {
            if($rule==='SAME_DAY_REEVALUATION'){$price=0;$source='CLINICAL_SAME_DAY';}
            else {$base=app(\App\Services\Catalog\ServicePriceResolver::class)->resolve($service,$date,$type==='RECONSULTA',$lock);$price=$base['price_cents'];$source=$base['source'];}
        }
        if ($price <= 0 && $rule !== 'SAME_DAY_REEVALUATION') {
            throw ValidationException::withMessages(['service_id' => 'El servicio no tiene un precio de catálogo válido.']);
        }
        if($rate && !$referred && $rule!=='SAME_DAY_REEVALUATION') {
            $price += $rate->tipo_tarifa === 'MONTO_FIJO' ? Money::cents($rate->tarifa ?? 0)
                : (int) round($price * (float) $rate->tarifa / 100);
        }
        if (($price <= 0 && $rule!=='SAME_DAY_REEVALUATION') || $price > 9999999999) {
            throw ValidationException::withMessages(['additional_rate_id' => 'La tarifa no produce un precio válido. El costo cero requiere exoneración autorizada.']);
        }
        $adjustment = app(AppointmentDiscount::class)->calculate($price, $discount);
        if ($adjustment['amount_cents'] > 0) {
            $actor = \App\Models\User::findOrFail($actorId);
            abort_unless($actor->can(\App\Support\Scheduling\SchedulingCapability::CREATE)
                && $actor->can(\App\Support\Scheduling\SchedulingCapability::VIEW),403);
        }
        return ['price_cents'=>$adjustment['net_cents'],'precio_programado'=>Money::decimal($adjustment['net_cents']),
            'catalog_price'=>Money::decimal($price),'discount'=>$adjustment,'tipo'=>$type,
            'additional_rate_id'=>$rate?(int)$rate->id:null,'price_source'=>$source,'pricing_rule'=>$rule,'indication'=>$indication,'referral_origin'=>$referred?trim($referral['origin']):null];
    }
}
