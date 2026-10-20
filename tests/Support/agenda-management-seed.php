<?php
if(PHP_SAPI!=='cli'||getenv('AGENDA_MANAGEMENT_QA')!=='APPEND_FICTIONAL_ONLY')throw new RuntimeException('Fictional QA append only.');
require dirname(__DIR__,2).'/vendor/autoload.php';
Tests\Support\IsolatedAgendaV2Qa::boot(true);
$f=json_decode(file_get_contents(storage_path('app/qa-v2/round4/fixtures.json')),true,512,JSON_THROW_ON_ERROR);
$admin=App\Models\User::findOrFail($f['users']['admin']['id']);Illuminate\Support\Facades\Auth::login($admin);
$date=Carbon\Carbon::parse($f['date'])->addDays(14);while(App\Models\DoctorSchedule::where('doctor_id',$f['doctor_id'])->whereDate('fecha_cita',$date)->exists())$date->addDay();
foreach([$date,$date->copy()->addDay()] as $day)App\Models\DoctorSchedule::create(['doctor_id'=>$f['doctor_id'],'site_id'=>$f['site_id'],'fecha_cita'=>$day->toDateString(),'dia_semana'=>$day->dayOfWeekIso,'hora_inicio'=>'08:00','hora_fin'=>'12:00','duracion_cita'=>30,'estado'=>'ACTIVO']);
$patient=App\Models\Patient::create(['user_id'=>$admin->id,'nombre'=>'PACIENTE GESTOR FICTICIO','apellido_paterno'=>'QA','apellido_materno'=>'LOCAL','numero_identidad'=>(string)(79800000+App\Models\Patient::count()),'tipo_identificacion'=>'DNI','historia_clinica'=>'QA-MANAGE-'.Illuminate\Support\Str::uuid(),'genero'=>'MUJER','estado'=>'ACTIVO','fecha_registro'=>now()->toDateString()]);
$a=app(App\Services\Scheduling\OperationalRegistrationService::class)->register(['request_key'=>(string)Illuminate\Support\Str::uuid(),'mode'=>'RESERVE','booking_type'=>'REGULAR','patient_id'=>$patient->id,'doctor_id'=>$f['doctor_id'],'service_id'=>$f['service_id'],'site_id'=>$f['site_id'],'fecha_cita'=>$date->toDateString(),'hora_cita'=>'08:00','duracion_cita'=>30,'motivo_consulta'=>'Paciente exclusivamente ficticio para gestionar y reprogramar','attendance_mode'=>'PRESENCIAL','is_postoperative'=>true,'has_diabetic_wound'=>true,'payment'=>['amount'=>'20','method'=>'EFECTIVO']],$admin);
app(App\Services\Scheduling\ReservationPaymentService::class)->submit($a->id,$admin,['request_key'=>(string)Illuminate\Support\Str::uuid(),'payment'=>['amount'=>'10','method'=>'YAPE','operation'=>'001'.str_pad((string)$a->id,8,'0',STR_PAD_LEFT)]]);
$result=['date'=>$date->toDateString(),'next_date'=>$date->copy()->addDay()->toDateString(),'appointment_id'=>$a->id,'patient_id'=>$patient->id,'doctor_id'=>$f['doctor_id'],'service_id'=>$f['service_id'],'site_id'=>$f['site_id']];
file_put_contents(storage_path('app/qa-v2/management/fixtures.json'),json_encode($result,JSON_PRETTY_PRINT));echo json_encode($result),PHP_EOL;
