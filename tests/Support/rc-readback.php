<?php
require dirname(__DIR__,2).'/vendor/autoload.php';
$app=\Tests\Support\IsolatedRcQa::boot();
use Illuminate\Support\Facades\DB;
DB::statement('SET TRANSACTION READ ONLY');DB::beginTransaction();
try{
    $historical=json_decode(file_get_contents(storage_path('app/qa-rc/historical-before.json')),true,512,JSON_THROW_ON_ERROR);
    $current=\App\Models\Appointment::findOrFail($historical['id'])->getAttributes();
    $report=['counts'=>[],'historical_intact'=>$historical===$current,
        'appointments'=>DB::table('appointments')->orderBy('id')->get(['id','user_id','responsible_user_id','patient_id','doctor_id','service_id','site_id','fecha_cita','hora_cita','duracion_cita','duration_slots','estado_agenda','estado_cita','tipo_agendamiento','precio_programado','total_pagado','saldo_pendiente','estado_pagado','es_exonerado','pricing_rule']),
        'payments'=>DB::table('payments')->orderBy('id')->get(['id','voucher_id','monto','metodo_pago','user_id']),
        'proofs'=>DB::table('appointment_documents')->orderBy('id')->get(['id','appointment_id','payment_id','type','purpose','reviewed_at','reviewed_by_user_id']),
        'worker_access'=>DB::table('users')->orderBy('id')->get(['id','is_active','auth_version']),
        'schedule_versions'=>DB::table('doctor_schedules')->orderBy('id')->get(['id','doctor_id','fecha_cita','estado','lock_version'])];
    foreach(['users','appointments','payments','vouchers','voucher_items','appointment_documents','appointment_operations','appointment_events','user_account_events','doctor_schedule_events'] as $table)$report['counts'][$table]=DB::table($table)->count();
    $report['payment_total']=(string)DB::table('payments')->sum('monto');
    $report['duplicate_bank_keys']=(int)DB::selectOne('SELECT COUNT(*) total FROM (SELECT bank_identity_key FROM payments WHERE bank_identity_key IS NOT NULL GROUP BY bank_identity_key HAVING COUNT(*)>1) d')->total;
    $report['orphan_appointments']=(int)DB::selectOne('SELECT COUNT(*) total FROM appointments a LEFT JOIN patients p ON p.id=a.patient_id LEFT JOIN doctors d ON d.id=a.doctor_id LEFT JOIN services s ON s.id=a.service_id WHERE p.id IS NULL OR d.id IS NULL OR s.id IS NULL')->total;
    $report['orphan_payments']=(int)DB::selectOne('SELECT COUNT(*) total FROM payments p LEFT JOIN vouchers v ON v.id=p.voucher_id WHERE v.id IS NULL')->total;
    $report['orphan_proofs']=(int)DB::selectOne('SELECT COUNT(*) total FROM appointment_documents d LEFT JOIN appointments a ON a.id=d.appointment_id LEFT JOIN payments p ON p.id=d.payment_id WHERE a.id IS NULL OR (d.payment_id IS NOT NULL AND p.id IS NULL)')->total;
    $report['schedule_audit']=DB::table('doctor_schedule_events')->orderBy('id')->get(['id','doctor_schedule_id','actor_user_id','event_type']);
    echo json_encode($report,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
}finally{DB::rollBack();}
