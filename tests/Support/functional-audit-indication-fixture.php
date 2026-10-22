<?php
/** Test fixture for the read-side event contract. Never authenticates or grants a medical identity. */
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__,2).'/vendor/autoload.php';
\Tests\Support\RescheduleVisualQa::boot();
$dir=dirname(__DIR__,2).'/storage/app/qa-reschedule-visual';$f=json_decode(file_get_contents($dir.'/browser-fixture.json'),true);
$id=$f['audit_ids']['PROCEDIMIENTOS_PENDIENTES'];$a=\App\Models\Appointment::findOrFail($id);
if($a->estado_cita!=='EN_ATENCION'||isset($f['pending_contract_event_id']))throw new RuntimeException('Expected unused fictional read-contract cohort.');
$author=\App\Models\User::create(['name'=>'AUTOR SINTETICO DE EVENTO; NO ACCESO MEDICO','email'=>'contract-author@example.invalid','password'=>\Illuminate\Support\Facades\Hash::make(bin2hex(random_bytes(32)))]);
$event=app(\App\Services\Scheduling\AppointmentHistory::class)->record($a,\App\Services\Scheduling\AppointmentReevaluationService::INDICATED,$author->id,
 ['metadata'=>['version'=>1,'source'=>'MEDICAL_RETURN_SAME_APPOINTMENT_V1','doctor_id'=>$a->doctor_id]]);
$f['pending_contract_event_id']=$event->id;file_put_contents($dir.'/browser-fixture.json',json_encode($f,JSON_PRETTY_PRINT));
echo "Synthetic future-writer event contract recorded in guarded local QA only. No medical endpoint enabled.\n";
