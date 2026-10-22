<?php
if(PHP_SAPI!=='cli')throw new RuntimeException('CLI only');
require dirname(__DIR__,2).'/vendor/autoload.php';
\Tests\Support\RescheduleVisualQa::boot();
$f=json_decode(file_get_contents(storage_path('app/qa-reschedule-visual/browser-fixture.json')),true);
$a=\App\Models\Appointment::findOrFail($f['appointment_id']);
$u=\App\Models\User::findOrFail($f['medical_id']);
$procedures=\Illuminate\Support\Facades\DB::table('appointment_requested_procedures')->where('appointment_id',$a->id)->get();
$result=['appointments'=>\App\Models\Appointment::count(),'payments'=>\App\Models\Payment::count(),
    'paid'=>number_format(\App\Models\Payment::sum('monto'),2,'.',''),'price'=>number_format($a->precio_programado,2,'.',''),
    'balance'=>number_format($a->saldo_pendiente,2,'.',''),'state'=>$a->estado_cita,
    'arrival_recorded'=>(bool)$a->hora_llegada,'started_recorded'=>(bool)$a->hora_atencion,
    'finished_recorded'=>(bool)$a->hora_atendido,'departure_recorded'=>(bool)$a->hora_salida,
    'same_doctor_link'=>(int)$u->doctor_id===(int)$a->doctor_id,'roles_preserved'=>$u->hasAllRoles(['ADMINISTRADOR','MEDICO']),
    'requested'=>$procedures->count(),'completed'=>$procedures->whereNotNull('completed_at')->count(),
    'requested_by_medical'=>$procedures->where('requested_by_user_id',$u->id)->count(),
    'verified_by_commercial'=>$procedures->where('verified_by_user_id',$f['commercial_id'])->count(),
    'clinical_events'=>$a->events()->whereIn('event_type',['ATENCION_MEDICA_INICIADA','REEVALUACION_MEDICA_INDICADA','ATENCION_MEDICA_FINALIZADA'])->count()];
$expected=['appointments'=>1,'payments'=>1,'paid'=>'60.00','price'=>'120.00','balance'=>'60.00','state'=>'ATENDIDO',
    'arrival_recorded'=>true,'started_recorded'=>true,'finished_recorded'=>true,'departure_recorded'=>true,
    'same_doctor_link'=>true,'roles_preserved'=>true,'requested'=>3,'completed'=>3,'requested_by_medical'=>3,'verified_by_commercial'=>3,'clinical_events'=>3];
if($result!==$expected)throw new RuntimeException('Browser database invariants failed: '.json_encode($result));
echo json_encode(['result'=>'PASS','invariants'=>$result],JSON_PRETTY_PRINT).PHP_EOL;
