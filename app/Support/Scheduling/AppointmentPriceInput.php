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
        return ['reevaluation_indication_id'=>'nullable|integer|min:1',
            'referral'=>'sometimes|array:origin,attested', 'referral.origin'=>'required_with:referral|string|max:255',
            'referral.attested'=>'exclude_without:referral|required|accepted', 'pricing_rule'=>'prohibited',
            'discount'=>'sometimes|array:type,value,reason',
            'discount.type'=>'required_with:discount|in:FIXED,PERCENT',
            'discount.value'=>'required_with:discount|numeric|min:0|max:99999999.99|regex:/\A[0-9]+(?:\.[0-9]{1,2})?\z/',
            'discount.reason'=>'nullable|string|max:500',
            'is_postoperative'=>'sometimes|boolean',
            'motivo_exoneracion'=>'nullable|string|max:2000'];
    }
}
