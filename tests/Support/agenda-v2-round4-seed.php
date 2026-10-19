<?php
if(PHP_SAPI!=='cli'||getenv('AGENDA_V2_ROUND4_FIXTURES')!=='APPEND_SYNTHETIC_ONLY')throw new RuntimeException('Explicit isolated synthetic append required.');
require dirname(__DIR__,2).'/vendor/autoload.php';
\Tests\Support\IsolatedAgendaV2Qa::boot(true);
$out=storage_path('app/qa-v2/round4');if(!is_dir($out))mkdir($out,0777,true);
if(is_file($out.'/fixtures.json')){echo "Round4 fixtures already exist; no reset performed.\n";exit;}
$secret=json_decode(file_get_contents(storage_path('app/qa-v2/browser-private.json')),true,512,JSON_THROW_ON_ERROR);
$builder=new class {use \Tests\Concerns\BuildsAgendaLifecycleData;
 public function append(string $password):array {
  $users=[];foreach(['admin'=>'ADMINISTRADOR','admission'=>'ADMISION','commercial'=>'COMERCIAL'] as $key=>$role){
   $u=$this->agendaReader($role);$u->forceFill(['name'=>strtoupper($key).' RONDA CUATRO FICTICIO','email'=>'agenda-r4-'.$key.'@example.invalid','password'=>\Illuminate\Support\Facades\Hash::make($password)])->save();
   foreach($key==='commercial'?[]:\App\Support\Scheduling\MvpPermissionBootstrap::REQUIRED as $cap)$u->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate($cap,'web'));
   if($key==='admin')foreach([\App\Support\Scheduling\SchedulingCapability::CREATE_ADDITIONAL,\App\Support\Scheduling\SchedulingCapability::APPROVE_ZERO_COST,\App\Support\Scheduling\SchedulingCapability::CANCEL,\App\Support\Scheduling\SchedulingCapability::WITHDRAW,\App\Support\Scheduling\SchedulingCapability::MARK_NO_SHOW,\App\Support\Scheduling\SchedulingCapability::VIEW_AUDIT] as $cap)$u->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate($cap,'web'));
   $cashier=\App\Models\Cashier::create(['nombre'=>'Caja R4 ficticia '.$key,'estado'=>'ACTIVO']);
   \App\Models\CashierShift::create(['cashier_id'=>$cashier->id,'user_id'=>$u->id,'monto_apertura'=>0,'abierto_en'=>now(),'estado'=>'ABIERTO']);
   \App\Models\VoucherSerie::create(['cashier_id'=>$cashier->id,'tipo_comprobante'=>'TICKET','serie'=>'Q'.str_pad($u->id,3,'0',STR_PAD_LEFT),'correlativo_actual'=>0,'estado'=>'ACTIVO']);$users[$key]=['id'=>$u->id,'email'=>$u->email];
  }
  $admin=\App\Models\User::findOrFail($users['admin']['id']);$catalog=$this->createAppointmentCatalog();$date=now('America/Lima')->toDateString();
  $site=$this->createSite(['codigo'=>'R4QA','nombre'=>'SEDE RONDA CUATRO FICTICIA']);
  $catalog['doctor']->update(['nombre'=>'MÉDICO RONDA CUATRO FICTICIO']);$catalog['service']->update(['nombre'=>'CONSULTA RONDA CUATRO FICTICIA','service_type'=>'CONSULTATION','pricing_policy'=>'PROFESSIONAL']);
  $catalog['rate']->update(['nombre'=>'TARIFA R4 FICTICIA','tipo_tarifa'=>'MONTO_FIJO']);$catalog['schedule']->update(['fecha_cita'=>$date,'hora_inicio'=>'08:00','hora_fin'=>'18:00','site_id'=>$site->id]);
  $second=\App\Models\Doctor::create(['nombre'=>'PROFESIONAL FAVORITO R4 FICTICIO','specialty_id'=>$catalog['specialty']->id,'estado'=>'ACTIVO']);
  $appointments=[];$patients=[];
  foreach(['tele_unpaid','tele_paid','tele_additional','presential','exempt','zero','custom','cancel','withdraw'] as $i=>$key){
   $patient=$this->createPatient($admin,['nombre'=>'PACIENTE R4 '.strtoupper($key).' FICTICIO','apellido_paterno'=>'QA','apellido_materno'=>'LOCAL','numero_identidad'=>(string)(79620000+$i),'historia_clinica'=>'R4-FICTICIO-'.$key]);$patients[$key]=['id'=>$patient->id,'document'=>$patient->numero_identidad];
   $time=['08:00','08:30','08:30','10:00','11:00','12:00','13:00','14:00','15:00'][$i];
   if(in_array($key,['tele_unpaid','tele_paid','tele_additional','exempt'],true)){
    $data=['request_key'=>(string)\Illuminate\Support\Str::uuid(),'mode'=>in_array($key,['exempt','tele_additional'],true)?'CONFIRM':'RESERVE','booking_type'=>$key==='tele_additional'?'ADICIONAL':'REGULAR',
     'patient_id'=>$patient->id,'doctor_id'=>$catalog['doctor']->id,'service_id'=>$catalog['service']->id,'site_id'=>$site->id,'fecha_cita'=>$date,'hora_cita'=>$time,'duracion_cita'=>30,
     'motivo_consulta'=>'Motivo exclusivamente ficticio de cuarta ronda','attendance_mode'=>$key==='exempt'?'PRESENCIAL':'TELECONSULTA','is_postoperative'=>$key==='tele_additional','has_diabetic_wound'=>$key==='tele_additional',
     'payment'=>['amount'=>in_array($key,['tele_paid','tele_additional'],true)?'30':'0','method'=>'EFECTIVO']];
    if($key==='exempt')$data+=['es_exonerado'=>true,'autorizado_por'=>'Autorización QA R4','motivo_exoneracion'=>'Exoneración exclusivamente ficticia'];
    $a=app(\App\Services\Scheduling\OperationalRegistrationService::class)->register($data,$admin);
   }else $a=$this->lifecycleAppointment($admin,$catalog,['fecha_cita'=>$date,'hora_cita'=>$time,'site_id'=>$site->id,'estado_agenda'=>'CONFIRMADA','tipo_agendamiento'=>'REGULAR',
       'precio_programado'=>$key==='zero'?0:100,'motivo_consulta'=>'Motivo exclusivamente ficticio R4','hora_llegada'=>$key==='withdraw'?now():null],$patient);
   $appointments[$key]=$a->id;
  }
  return ['date'=>$date,'users'=>$users,'doctor_id'=>$catalog['doctor']->id,'second_doctor_id'=>$second->id,'service_id'=>$catalog['service']->id,'site_id'=>$site->id,'specialty_id'=>$catalog['specialty']->id,'appointments'=>$appointments,'patients'=>$patients];
 }
};
$f=\Illuminate\Support\Facades\DB::transaction(fn()=>$builder->append($secret['password']));
file_put_contents($out.'/fixtures.json',json_encode($f,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));echo "Round4 synthetic fixtures appended; all existing appointments preserved.\n";
