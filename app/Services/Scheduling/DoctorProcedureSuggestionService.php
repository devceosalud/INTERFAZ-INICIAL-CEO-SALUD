<?php

namespace App\Services\Scheduling;

use App\Models\{Appointment,User};
use App\Support\Scheduling\{ClinicalAccess,ClinicalCapability,ProcedureCatalog};
use Illuminate\Support\Facades\{Crypt,DB};
use Illuminate\Support\Str;

/** Private names only: no patient, clinical narrative, price or official promotion. */
final class DoctorProcedureSuggestionService
{
    public static function normalize(string $name): string
    {
        $value=mb_strtolower(Str::ascii(preg_replace('/\s+/u',' ',trim($name))));
        return ['operacion'=>'cirugia','rm'=>'resonancia magnetica','rmn'=>'resonancia magnetica','resonancia'=>'resonancia magnetica'][$value]??$value;
    }

    public function list(User $actor,?string $search=null): array
    {
        if(!\App\Support\Scheduling\ClinicalCapability::isMedical($actor))return [];
        $u=ClinicalAccess::actor($actor,ClinicalCapability::PROCEDURES);
        $query=DB::table('doctor_procedure_suggestions')->where('user_id',$u->id)->where('doctor_id',$u->doctor_id)->orderByDesc('updated_at');
        $rows=$search===null?$query->limit(200)->get():$query->cursor();$result=[];$term=$search===null?null:self::normalize($search);
        foreach($rows as $p){$label=Crypt::decryptString($p->label_ciphertext);if($term!==null&&!str_contains(self::normalize($label),$term))continue;$result[]=['id'=>$p->id,'label'=>$label,'family'=>$p->procedure_family,'personal'=>true];if($term!==null&&count($result)>=50)break;}
        return $result;
    }

    /** Called only inside the existing selection transaction, after clinical identity locks. */
    public function canonicalize(array $items,User $actor,Appointment $a,$existing): array
    {
        if(!\App\Support\Scheduling\ClinicalCapability::isMedical($actor)) {foreach($items as $item)abort_if(!empty($item['reusable_id']),403);return $items;}
        $u=ClinicalAccess::actor($actor,ClinicalCapability::PROCEDURES);ClinicalAccess::appointment($u,$a);$a->loadMissing('patient');$seen=[];$result=[];
        foreach($items as $item){
            $label=trim($item['label']);$normal=self::normalize($label);$family=$item['family']??'other';
            abort_unless(isset(ProcedureCatalog::FAMILIES[$family]),422,'Elige una categoría clínica válida.');
            abort_if(preg_match('/@|\b\d{7,}\b/u',$label),422,'Escribe solo el procedimiento, sin nombres, documentos o datos de contacto del paciente.');
            foreach(array_filter([$a->patient?->numero_identidad,trim(($a->patient?->apellido_paterno??'').' '.($a->patient?->apellido_materno??'').' '.($a->patient?->nombre??''))])as $identity)
                abort_if(mb_strlen($identity)>=6&&str_contains($normal,self::normalize($identity)),422,'El catálogo personal no admite identificación del paciente.');
            $hash=hash('sha256',$normal);abort_if(isset($seen[$hash]),422,'La selección contiene procedimientos personalizados equivalentes.');$seen[$hash]=true;
            $p=DB::table('doctor_procedure_suggestions')->where('user_id',$u->id)->where('doctor_id',$u->doctor_id)->where('normalized_hash',$hash)->lockForUpdate()->first();
            if(!empty($item['reusable_id']))abort_unless($p&&$p->id===$item['reusable_id'],403,'La sugerencia personal no pertenece a tu ámbito autorizado.');
            if($p){abort_unless($p->procedure_family===$family,409,'La sugerencia existente tiene otra categoría. No se cambia una identidad clínica confirmada.');$label=Crypt::decryptString($p->label_ciphertext);}
            else {
                $legacy=isset($existing['c:'.$item['id']]);
                if(!$legacy)foreach(ProcedureCatalog::labels()as $official)abort_if(self::normalize($official)===$normal,422,'Ese procedimiento ya existe en el catálogo. Selecciona la opción existente.');
                $identity=DB::table('doctor_procedure_suggestions')->where('id',$item['id'])->first();
                if($identity){abort_unless((int)$identity->user_id===(int)$u->id&&(int)$identity->doctor_id===(int)$u->doctor_id,403,'La referencia personal no está disponible.');abort(409,'El nombre de una sugerencia confirmada se conserva. Retírala y agrega otra solicitud para corregirla.');}
                DB::table('doctor_procedure_suggestions')->insert(['id'=>$item['id'],'user_id'=>$u->id,'doctor_id'=>$u->doctor_id,'normalized_hash'=>$hash,'label_ciphertext'=>Crypt::encryptString($label),'procedure_family'=>$family,'created_at'=>now(),'updated_at'=>now()]);
                $p=(object)['id'=>$item['id']];
            }
            $result[]=['id'=>$p->id,'label'=>$label,'family'=>$family,'reusable_id'=>$p->id];
        }
        return $result;
    }
}
