<?php
if(PHP_SAPI!=='cli'||getenv('AGENDA_V2_ROUND4_FIXTURES')!=='APPEND_SYNTHETIC_ONLY')throw new RuntimeException('Explicit fictional append required.');
require dirname(__DIR__,2).'/vendor/autoload.php';
\Tests\Support\IsolatedAgendaV2Qa::boot(true);
$out=storage_path('app/qa-v2/round4/resume');if(!is_dir($out))mkdir($out,0777,true);
if(is_file($out.'/fixtures.json')){echo "Resume fixtures already present; nothing changed.\n";exit;}
$f=json_decode(file_get_contents(storage_path('app/qa-v2/round4/fixtures.json')),true,512,JSON_THROW_ON_ERROR);
$builder=new class {use \Tests\Concerns\BuildsAgendaLifecycleData;
 public function append($f):array{
  $admin=\App\Models\User::findOrFail($f['users']['admin']['id']);\Illuminate\Support\Facades\Auth::login($admin);
  $date=\Carbon\Carbon::parse($f['date']);$q=new \App\Support\Scheduling\AgendaQuery([$f['doctor_id']],$date,$date,$f['site_id']);
  $days=app(\App\Services\Scheduling\DoctorAvailabilityService::class)->forRange($q);
  $slot=$days->first()->slots()->filter(fn($s)=>$s->isAvailable())->last();if(!$slot)throw new RuntimeException('No free fake slot for append.');
  $time=$slot->range()->start()->format('H:i');$ids=[];
  foreach(['bank','postop','wound'] as $key){
   $patient=$this->createPatient($admin,['nombre'=>'NOMBRE LARGO DE PACIENTE EXCLUSIVAMENTE FICTICIO '.$key,'apellido_paterno'=>'QA','apellido_materno'=>'LOCAL','numero_identidad'=>(string)(79700000+\App\Models\Patient::count()),'historia_clinica'=>'R4-RESUME-'.\Illuminate\Support\Str::uuid()]);
   $data=['request_key'=>(string)\Illuminate\Support\Str::uuid(),'mode'=>'CONFIRM','booking_type'=>$key==='bank'?'REGULAR':'ADICIONAL','patient_id'=>$patient->id,'doctor_id'=>$f['doctor_id'],'service_id'=>$f['service_id'],'site_id'=>$f['site_id'],'fecha_cita'=>$f['date'],'hora_cita'=>$time,'duracion_cita'=>30,'motivo_consulta'=>'Caso ficticio de etiquetas y reportes de continuación','attendance_mode'=>'PRESENCIAL','is_postoperative'=>$key==='postop','has_diabetic_wound'=>$key==='wound','payment'=>$key==='bank'?['amount'=>'12.34','method'=>'TRANSFERENCIA','origin'=>'BCP','operation'=>'00001984']:['amount'=>'100','method'=>'EFECTIVO']];
   $a=app(\App\Services\Scheduling\OperationalRegistrationService::class)->register($data,$admin);$ids[$key]=$a->id;
   if($key==='bank')app(\App\Services\Scheduling\ReservationPaymentService::class)->submit($a->id,$admin,['request_key'=>(string)\Illuminate\Support\Str::uuid(),'payment'=>['amount'=>'7.66','method'=>'TRANSFERENCIA','origin'=>'BCP','operation'=>'00002004']]);
  }
  return ['appointments'=>$ids,'bank_operations'=>['00001984','00002004'],'date'=>$f['date'],'doctor_id'=>$f['doctor_id']];
 }
};
$r=\Illuminate\Support\Facades\DB::transaction(fn()=>$builder->append($f));file_put_contents($out.'/fixtures.json',json_encode($r,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo "Three fictitious appointments appended with existing registration and payment services. No prior rows changed.\n";
