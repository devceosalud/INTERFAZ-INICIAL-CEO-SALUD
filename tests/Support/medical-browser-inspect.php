<?php
if(PHP_SAPI!=='cli')throw new RuntimeException('CLI only');
require dirname(__DIR__,2).'/vendor/autoload.php';
$app=\Tests\Support\RescheduleVisualQa::boot();
$f=json_decode(file_get_contents(dirname(__DIR__,2).'/storage/app/qa-reschedule-visual/browser-fixture.json'),true);
$a=\App\Models\Appointment::findOrFail($f['appointment_id']);$u=\App\Models\User::findOrFail($f['existing_id']);
echo json_encode(['users'=>\App\Models\User::count(),'initial_users'=>$f['initial_users'],'medical_user_id'=>$u->id,'doctor_id'=>$u->doctor_id,
    'role_names'=>$u->getRoleNames(),'epoch'=>$u->auth_version,'appointments'=>\App\Models\Appointment::count(),
    'payments'=>\App\Models\Payment::count(),'paid'=>number_format(\App\Models\Payment::sum('monto'),2,'.',''),
    'price'=>number_format($a->precio_programado,2,'.',''),'balance'=>number_format($a->saldo_pendiente,2,'.',''),
    'state'=>$a->estado_cita,'arrival'=>$a->hora_llegada,'started'=>$a->hora_atencion,'finished'=>$a->hora_atendido,'departure'=>$a->hora_salida,
    'medical_events'=>$a->events()->whereIn('event_type',['ATENCION_MEDICA_INICIADA','ATENCION_MEDICA_FINALIZADA','REEVALUACION_MEDICA_INDICADA','REEVALUACION_MEDICA_RETIRADA'])
        ->get(['event_type','actor_user_id','metadata']),
    'link_events'=>\App\Models\UserAccountEvent::where('target_user_id',$u->id)->where('event_type','DOCTOR_LINK_CHANGED')->count(),
    'procedures'=>\Illuminate\Support\Facades\DB::table('appointment_requested_procedures')->where('appointment_id',$a->id)
        ->get(['procedure_key','selected','requested_by_user_id','verified_by_user_id','completed_at'])],JSON_PRETTY_PRINT);
