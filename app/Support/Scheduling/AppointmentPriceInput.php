<?php
namespace App\Support\Scheduling;

/** Common validation for operational and legacy quote/registration entries. */
final class AppointmentPriceInput
{
    public static function normalize(\Illuminate\Http\Request $request): void
    {
        if ($request->has('discount') && !$request->input('discount.type')
            && preg_match('/\A0*(?:\.0{1,2})?\z/',(string)$request->input('discount.value',''))) {
            $request->merge(['discount'=>[]]);
        }
    }
    public static function rules(): array
    {
        return ['price_modality'=>'sometimes|in:CONSULTATION,REEVALUATION',
            'commercial_service_id'=>'required_if:price_modality,REEVALUATION|nullable|integer|min:1',
            'reevaluation_indication_id'=>'required_if:price_modality,REEVALUATION|prohibited_if:price_modality,CONSULTATION|nullable|integer|min:1',
            'referral'=>'sometimes|array:origin,attested', 'referral.origin'=>'required_with:referral|string|max:255',
            'referral.attested'=>'exclude_without:referral|required|accepted', 'pricing_rule'=>'prohibited',
            'discount'=>'sometimes|array:type,value,reason',
            'discount.type'=>'required_with:discount|in:FIXED,PERCENT',
            'discount.value'=>'required_with:discount|numeric|min:0|max:99999999.99|regex:/\A[0-9]+(?:\.[0-9]{1,2})?\z/',
            'discount.reason'=>'nullable|string|max:500',
            'is_postoperative'=>'sometimes|boolean', 'has_diabetic_wound'=>'sometimes|boolean',
            'motivo_exoneracion'=>'nullable|string|max:2000'];
    }

    public static function assertCommercialService(array $data,int $canonicalId,bool $lock=false):?int
    {
        $id=$data['commercial_service_id']??null;$mode=$data['price_modality']??null;
        if($mode==='REEVALUATION'&&(!$id||empty($data['reevaluation_indication_id'])))
            throw \Illuminate\Validation\ValidationException::withMessages(['reevaluation_indication_id'=>'Selecciona una indicación médica acreditada para la modalidad de reevaluación.']);
        if($mode==='CONSULTATION'&&($id||!empty($data['reevaluation_indication_id'])))
            throw \Illuminate\Validation\ValidationException::withMessages(['price_modality'=>'La consulta normal no utiliza una indicación ni un servicio comercial de reevaluación.']);
        if(!$id)return null;
        $query=\App\Models\Service::whereKey($id)->where('estado','ACTIVO')->where('service_type','REEVALUATION')->where('canonical_service_id',$canonicalId);
        if($lock)$query->lockForUpdate();
        $alias=$query->first();
        if(!$alias||empty($data['reevaluation_indication_id']))
            throw \Illuminate\Validation\ValidationException::withMessages(['commercial_service_id'=>'La modalidad comercial no corresponde a esta consulta e indicación.']);
        $date=$data['fecha_cita']??null;
        if($date && (($alias->price_valid_from&&substr($alias->price_valid_from,0,10)>$date)||($alias->price_valid_until&&substr($alias->price_valid_until,0,10)<$date)))
            throw \Illuminate\Validation\ValidationException::withMessages(['commercial_service_id'=>'La modalidad comercial no está vigente para la fecha de atención.']);
        return (int)$alias->id;
    }
}
