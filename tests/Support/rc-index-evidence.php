<?php
require dirname(__DIR__,2).'/vendor/autoload.php';
$app=\Tests\Support\IsolatedRcSuiteQa::boot();
use Illuminate\Support\Facades\DB;
DB::statement('SET TRANSACTION READ ONLY');DB::beginTransaction();
try{
 $report=['environment'=>['mariadb'=>DB::selectOne('SELECT VERSION() version')->version,'schema'=>'ceosalud_qa_rc_suite','synthetic_only'=>true],'indexes'=>[],'plans'=>[]];
 foreach(['appointments','payments','voucher_items','appointment_operations','appointment_documents','doctor_schedules'] as $table)$report['indexes'][$table]=DB::select('SHOW INDEX FROM `'.$table.'`');
 $report['plans']['date_range']=DB::select("EXPLAIN SELECT id,doctor_id,patient_id,estado_agenda,estado_cita FROM appointments WHERE fecha_cita BETWEEN '2026-10-15' AND '2026-10-19'");
 $report['plans']['doctor_range']=DB::select("EXPLAIN SELECT id FROM appointments WHERE doctor_id=1 AND fecha_cita BETWEEN '2026-10-15' AND '2026-10-19'");
 $report['plans']['patient_history']=DB::select('EXPLAIN SELECT id FROM appointments WHERE patient_id=1 ORDER BY fecha_cita DESC LIMIT 20');
 echo json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
}finally{DB::rollBack();}