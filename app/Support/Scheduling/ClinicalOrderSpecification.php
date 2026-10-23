<?php

namespace App\Support\Scheduling;

/** Content transcribed from the user's order image/PDF, not inferred from tariffs. */
final class ClinicalOrderSpecification
{
    public static function entries(): array
    {
        $items = [];
        $add = function ($key, $label, $family, $group, $sides = [], $load = false, $finger = false, $projections = []) use (&$items) {
            $items[$key] = compact('label', 'family', 'group', 'sides', 'load', 'finger', 'projections');
        };
        foreach (['cervical'=>'Cervical', 'dorsal'=>'Dorsal', 'lumbar'=>'Lumbar / lumbosacra'] as $key=>$name) {
            $add('rx_'.$key, 'Radiografía '.$name, 'radiography', 'Radiografías · Columna', [], false, false, ['AP','LAT','OBLIQUE']);
        }
        foreach (['shoulder'=>'Hombro','arm'=>'Brazo','elbow'=>'Codo','forearm'=>'Antebrazo','wrist'=>'Muñeca','hand'=>'Mano'] as $key=>$name) {
            $add('rx_'.$key, 'Radiografía de '.$name, 'radiography', 'Radiografías · Miembros superiores', ['D','I'], false, $key==='hand', ['AP','LAT','OBLIQUE']);
        }
        foreach (['pelvis'=>'Pelvis','hip'=>'Cadera','femur'=>'Muslo / fémur','knee'=>'Rodilla','patella'=>'Rótula / axial','leg'=>'Pierna (tibia / peroné)','ankle'=>'Tobillo','foot'=>'Pie'] as $key=>$name) {
            $add('rx_'.$key, 'Radiografía de '.$name, 'radiography', 'Radiografías · Pelvis y miembros inferiores', $key==='pelvis'?[]:['D','I'], in_array($key,['pelvis','hip','knee','ankle','foot'],true), false, ['AP','LAT','OBLIQUE']);
        }
        $add('rx_mensurative','Mensurativa comp. MMI','radiography','Radiografías · Complementarios');
        $add('rx_infant_hip','Pelvis / cadera infante','radiography','Radiografías · Complementarios');
        foreach (['shoulder'=>'Hombro','elbow'=>'Codo','arm_forearm'=>'Brazo / antebrazo','wrist_hand'=>'Muñeca / mano','thigh'=>'Muslo','knee'=>'Rodilla','leg'=>'Pierna (gemelos / pantorrilla)','ankle_achilles'=>'Tobillo / tendón de Aquiles','foot'=>'Pie'] as $key=>$name) {
            $add('us_'.$key,'Ecografía de '.$name,'other','Ecografía',['D','I']);
        }
        $add('us_infant_hip','Ecografía de cadera lactante','other','Ecografía');
        $add('us_doppler_arterial','Doppler arterial','other','Ecografía',['D','I','B']);
        $add('us_doppler_venous','Doppler venoso','other','Ecografía',['D','I','B']);
        foreach (['adant'=>'Adant 98/4.90 ml','pronolis'=>'Pronolis HD 2.5','ostenil_mini'=>'Ostenil mini 1.0%','ostenil_tendon'=>'Ostenil tendon 2.0%','material_kit'=>'Kit material','ultrasound_guided'=>'Ecoguiada','triamcicort'=>'Triamcicort 50 mg/5 ml','prp_biocells'=>'PRP Biocells','arthrocentesis'=>'Artrocentesis'] as $key=>$name) {
            $add('order_'.$key,$name,'other','Procedimientos / infiltraciones');
        }
        foreach (['vsg'=>'VSG','pcr'=>'PCR','blood_count'=>'Hemograma completo','urea'=>'Urea','glucose'=>'Glucosa','creatinine'=>'Creatinina','culture'=>'Cultivo','lipid'=>'Perfil lipídico','hepatic'=>'Perfil hepático','hba1c'=>'HbA1c (hemoglobina glicosilada)','urine_albumin'=>'Albúmina orina simp.'] as $key=>$name) {
            $add('lab_'.$key,$name,'laboratory','Pie diabético · Laboratorio');
        }
        foreach (['tpn'=>'TPN','offloading_shoe'=>'Zapato de descarga','walker'=>'Bota Walker','orthotic_insole'=>'Plantilla ortopédica','prosthesis'=>'Prótesis'] as $key=>$name) {
            $add('device_'.$key,$name,'wound_care','Pie diabético · Curación y dispositivos');
        }
        foreach (['genacol'=>'Colágeno GENACOL','cledium'=>'Colágeno Cledium (diabéticos)','ascorbic'=>'Ascorbit-C inyectable','knee_prosthesis'=>'Prótesis · Rodilla','hip_prosthesis'=>'Prótesis · Cadera','leg_prosthesis'=>'Prótesis · Pierna ortopédica'] as $key=>$name) {
            $add('other_'.$key,$name,'other','Otros');
        }
        return $items;
    }

    public static function revision(): string
    {
        return hash('sha256', json_encode(['version'=>1,'entries'=>self::entries(),'diagnosis_max'=>500,'observations_max'=>600]));
    }

    public static function details(string $key, array $values): array
    {
        $spec = self::entries()[$key] ?? [];
        abort_if(array_diff(array_keys($values), ['sides','with_load','projections','finger']),422,'Datos de procedimiento no reconocidos.');
        $result = [];
        foreach (['sides','projections'] as $field) {
            $selection = $values[$field] ?? [];
            abort_unless(is_array($selection) && !array_diff($selection,$spec[$field]??[]),422,'La especificación no corresponde al procedimiento seleccionado.');
            $selection = array_values(array_unique($selection));sort($selection);
            if ($selection) $result[$field] = $selection;
        }
        if (!empty($values['with_load'])) {
            abort_unless($spec['load']??false,422,'Este procedimiento no tiene opción con carga en la referencia.');
            $result['with_load'] = true;
        }
        if (isset($values['finger']) && $values['finger'] !== '') {
            abort_unless(($spec['finger']??false) && filter_var($values['finger'],FILTER_VALIDATE_INT)!==false && $values['finger']>=1 && $values['finger']<=5,422,'El dedo debe estar entre1 y5 y corresponder a radiografía de mano.');
            $result['finger'] = (int)$values['finger'];
        }
        return $result;
    }

    public static function description(array $values): string
    {
        $parts=[];
        foreach ($values['sides']??[] as $side) $parts[]=['D'=>'Derecho','I'=>'Izquierdo','B'=>'Bilateral'][$side]??'';
        if (!empty($values['with_load'])) $parts[]='Con carga';
        foreach ($values['projections']??[] as $view) $parts[]=['AP'=>'AP','LAT'=>'Lateral','OBLIQUE'=>'Oblicua'][$view]??'';
        if (isset($values['finger'])) $parts[]='Dedo '.$values['finger'];
        return implode(' · ',array_filter($parts));
    }
}
