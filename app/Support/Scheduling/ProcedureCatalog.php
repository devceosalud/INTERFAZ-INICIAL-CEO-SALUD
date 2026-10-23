<?php
namespace App\Support\Scheduling;

/** Explicit clinical families; no prices and no inference from a service tariff. */
final class ProcedureCatalog
{
    public const FAMILIES = [
        'radiography'=>'Radiografías', 'mri'=>'Resonancia magnética', 'laboratory'=>'Laboratorio',
        'wound_care'=>'Curaciones', 'surgery'=>'Cirugías', 'other'=>'Otros procedimientos',
    ];
    public static function entries(): array
    {
        $result=[];
        foreach (\App\Services\Scheduling\OperationalProcedureService::CATALOG as $key=>$label) {
            $family=array_key_exists($key,self::FAMILIES)?$key:'other';
            $result[$key]=['label'=>$label,'family'=>$family,'category'=>self::FAMILIES[$family],'followup'=>self::requiresFollowup($family,$key)];
        }
        // Deployment-reviewed approved extensions only. No user-editable catalog/prices here.
        foreach (config('operational_procedures.approved',[]) as $key=>$item) {
            if (!is_string($key)||!preg_match('/\A[a-z][a-z0-9_]{0,39}\z/',$key)||isset($result[$key])) continue;
            if (!isset($item['label'],$item['family'])||!is_string($item['label'])||mb_strlen($item['label'])>500||!isset(self::FAMILIES[$item['family']])) continue;
            $result[$key]=['label'=>$item['label'],'family'=>$item['family'],'category'=>self::FAMILIES[$item['family']],'followup'=>self::requiresFollowup($item['family'],$key)];
        }
        return $result;
    }
    public static function labels(): array {return array_map(fn($item)=>$item['label'],self::entries());}
    public static function requiresFollowup(string $family,?string $key=null): bool {return in_array($family,config('operational_procedures.followup_families',['mri','surgery']),true)||($key!==null&&in_array($key,config('operational_procedures.followup_keys',[]),true));}
}
