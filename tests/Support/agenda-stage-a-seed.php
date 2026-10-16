<?php
require dirname(__DIR__,2).'/vendor/autoload.php';
$app=\Tests\Support\IsolatedAgendaStageAQa::boot();
$password=getenv('AGENDA_STAGE_A_PASSWORD');
if(PHP_SAPI!=='cli'||getenv('AGENDA_STAGE_A_RESET')!=='YES_OWNED_STAGE_A'||!$password||strlen($password)<24)throw new RuntimeException('Explicit owned synthetic reset required');
config(['scheduling.pilot_payment_without_manual_cash_shift'=>true]);
\Illuminate\Support\Facades\Artisan::call('migrate:fresh',['--force'=>true]);
$fixture=new class {use \Tests\Concerns\BuildsAgendaLifecycleData;
 public function seed($password):array{
  $users=[];foreach(['admin'=>'ADMINISTRADOR','commercialA'=>'COMERCIAL','commercialB'=>'COMERCIAL','nicole'=>'ADMISION'] as $key=>$role){
   $u=$this->agendaReader($role);$u->forceFill(['name'=>strtoupper($key).' FICTICIO QA','email'=>'agenda-a-'.$key.'@example.invalid','password'=>\Illuminate\Support\Facades\Hash::make($password)])->save();
   foreach(\App\Support\Scheduling\MvpPermissionBootstrap::REQUIRED as $cap)$u->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate($cap,'web'));
   $users[$key]=['id'=>$u->id,'email'=>$u->email];
  }
  $admin=\App\Models\User::findOrFail($users['admin']['id']);$owner=\App\Models\User::findOrFail($users['commercialA']['id']);
  $admin->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('appointment.responsible.assign','web'));
  $catalog=$this->createAppointmentCatalog();$catalog['doctor']->update(['nombre'=>'MEDICO ETAPA A QA']);$catalog['service']->update(['nombre'=>'CONSULTA OPERATIVA FICTICIA']);$catalog['rate']->update(['nombre'=>'TARIFA ESTANDAR','tipo_tarifa'=>'MONTO_FIJO']);
  $catalog['schedule']->update(['hora_fin'=>'18:00']);$date=$catalog['schedule']->fecha_cita;
  $site=$this->createSite(['codigo'=>'QA-A','nombre'=>'Magdalena del Mar']);$catalog['schedule']->update(['site_id'=>$site->id]);
  $patient=$this->createPatient($owner,['nombre'=>'PACIENTE OPCION HERIDA FICTICIO','numero_identidad'=>'73000001','historia_clinica'=>'QA-A-LOOKUP']);
  $ids=[];$register=function($key,$time,$amount,$confirm=false,$flags=[])use(&$ids,$owner,$admin,$catalog,$date,$site){
   $p=$this->createPatient($owner,['nombre'=>'PACIENTE '.strtoupper($key).' FICTICIO QA','numero_identidad'=>(string)(73000100+\App\Models\Patient::count()),'historia_clinica'=>'QA-A-'.$key]);
   $data=array_replace(['request_key'=>(string)\Illuminate\Support\Str::uuid(),'mode'=>$confirm?'CONFIRM':'RESERVE','booking_type'=>'REGULAR',
    'site_id'=>$site->id,'responsible_user_id'=>$owner->id,'patient_id'=>$p->id,'doctor_id'=>$catalog['doctor']->id,'service_id'=>$catalog['service']->id,'fecha_cita'=>$date,'hora_cita'=>$time,'duracion_cita'=>30,'payment'=>['amount'=>(string)$amount,'method'=>'EFECTIVO']],$flags);
   $a=app(\App\Services\Scheduling\OperationalRegistrationService::class)->register($data,$key==='paid'?$admin:$owner);$ids[$key]=$a->id;return $a;
  };
  $register('paid','09:00',50,true,['has_diabetic_wound'=>true,'is_postoperative'=>true]);$register('unpaid','10:00',0);$register('wound','11:00',0,false,['has_diabetic_wound'=>true]);
  $register('postop','12:00',0,false,['is_postoperative'=>true]);$register('partial','13:00',20);$register('compound','13:30',20,false,['has_diabetic_wound'=>true,'is_postoperative'=>true]);
  foreach(['cancel'=>['14:00','CANCELADO'],'present'=>['15:00','EN_ESPERA'],'attended'=>['16:00','ATENDIDO'],'departureRace'=>['16:30','ATENDIDO']] as $key=>[$time,$state]){
   $a=$this->lifecycleAppointment($admin,$catalog,['fecha_cita'=>$date,'hora_cita'=>$time,'estado_cita'=>$state,'site_id'=>$site->id]);
   $a->forceFill(['hora_llegada'=>now(),'has_diabetic_wound'=>true,'is_postoperative'=>true]+($state==='ATENDIDO'?['hora_atendido'=>now()]:[]))->save();$ids[$key]=$a->id;
  }
  return ['date'=>$date,'users'=>$users,'ids'=>$ids,'doctor_id'=>$catalog['doctor']->id,'service_id'=>$catalog['service']->id,'patient_id'=>$patient->id,'document'=>'73000001'];
 }
};
file_put_contents(storage_path('app/qa-stage-a/fixtures.json'),json_encode($fixture->seed($password),JSON_PRETTY_PRINT));
echo 'Seeded only owned stage A synthetic fixtures'.PHP_EOL;