<?php
namespace App\Services\Scheduling;

/** Minimal financial audit. Digests detect changes without copying addresses, notes, XML or private paths. */
final class EconomicCorrectionSnapshot
{
    private const FIELDS = [
        'appointment'=>['id','patient_id','doctor_id','service_id','site_id','fecha_cita','hora_cita','estado_cita','estado_agenda','economic_source',
            'precio_programado','total_pagado','saldo_pendiente','estado_pagado','catalog_price','discount_type','discount_value','discount_amount',
            'discount_reason','discount_actor_user_id','additional_rate_id','pricing_rule','commercial_service_id','es_exonerado','updated_at'],
        'vouchers'=>['id','tipo_comprobante','serie','correlativo','patient_id','total','subtotal','igv','total_gravado','total_exonerado','total_inafecto',
            'estado','parent_voucher_id','cashier_shift_id','requiere_sunat','estado_sunat','aplica_detraccion'],
        'items'=>['id','voucher_id','item_type','item_id','cantidad','precio_unitario','total','afectacion_igv','igv_monto','comision_porcentaje','comision_monto'],
        'payments'=>['id','voucher_id','monto','metodo_pago','numero_operacion','bank_identity_key','cashier_shift_id','user_id','created_at','updated_at'],
        'shifts'=>['id','cashier_id','user_id','estado','cerrado_en','monto_sistema','monto_contado'],
        'credits'=>['id','source_appointment_id','destination_appointment_id','voucher_id','amount','operation_id'],
        'refunds'=>['id','appointment_id','voucher_id','amount','status','operation_id'],
        'documents'=>['id','type','payment_id','reviewed_by_user_id','reviewed_at','payment_proof_hash','deleted_at','replaces_document_id'],
        'position'=>['price_cents','paid_cents','credit_cents','outgoing_credit_cents','refund_reserved_cents','effective_cents','balance_cents','authority','allocation_required'],
    ];

    public static function hash(array $data): string { return hash('sha256',json_encode($data,JSON_THROW_ON_ERROR)); }

    /** Accept v1 snapshots for comparison without rewriting their original audit. */
    public function minimize(array $raw, bool $historical=false): array
    {
        if(($raw['version']??1)===2)return $raw;
        $out=['version'=>2,'appointment_revision'=>$raw['appointment_revision'],
            'appointment'=>$this->pick($raw['appointment'],self::FIELDS['appointment'])];
        foreach(['vouchers','items','payments','shifts','credits','refunds','documents'] as $kind){
            $out[$kind]=array_map(function($row)use($kind,$historical){
                $r=(array)$row;$selected=$this->pick($r,self::FIELDS[$kind]);
                $selected['source_digest']=self::hash($r);
                if($kind==='vouchers')$selected['has_fiscal_artifacts']=!empty($r['sunat_respuesta'])||!empty($r['sunat_hash'])||!empty($r['xml_path'])||!empty($r['cdr_path'])||!empty($r['sunat_enviado_en']);
                if($kind==='documents'){
                    $path=$r['private_path']??null;$hash=$r['payment_proof_hash']??null;
                    $selected['integrity_valid']=$historical?(bool)($path&&$hash):($path&&$hash
                        && \Illuminate\Support\Facades\Storage::disk('local')->exists($path)
                        && hash_equals($hash,hash_file('sha256',\Illuminate\Support\Facades\Storage::disk('local')->path($path))));
                }
                return $selected;
            },$raw[$kind]);
        }
        $out['position']=$this->pick($raw['position'],self::FIELDS['position']);
        $out['position']['payment_ids']=array_map('intval',array_column($raw['position']['payment_details'],'id'));
        return $out;
    }

    private function pick(array $row,array $keys): array
    {
        $out=[];foreach($keys as $key)if(array_key_exists($key,$row))$out[$key]=$row[$key];return $out;
    }
}
