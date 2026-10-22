<?php
/** Synthetic browser cohort only; original bootstrap refuses .env and a pre-existing database. */
require __DIR__.'/reschedule-visual-browser-seed.php';
$actor=$data['actor'];$catalog=$data['catalog'];
$actor->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate(\App\Support\Scheduling\SchedulingCapability::UPDATE,'web'));
$maker=new class {use \Tests\Concerns\BuildsAgendaLifecycleData;public function add($actor,$catalog,$attributes){return $this->lifecycleAppointment($actor,$catalog,$attributes);}
 public function admin(){return $this->agendaReader('ADMINISTRADOR');}};
$states=['SIN_PAGO'=>['PROGRAMADO','10:00'],'PRESENTE'=>['EN_ESPERA','10:20'],'INICIO_ADMIN_AUDIT'=>['EN_ESPERA','10:40'],
 'ATENDIDO'=>['ATENDIDO','11:00'],'SALIDA'=>['ATENDIDO','11:20'],'CANCELADA'=>['CANCELADO','11:40'],
 'LISTO'=>['EN_ATENCION','12:00'],'PROCEDIMIENTOS_PENDIENTES'=>['EN_ATENCION','13:20']];$ids=[];
foreach($states as $label=>[$state,$time]){
 $a=$maker->add($actor,$catalog,['fecha_cita'=>'2026-10-21','hora_cita'=>$time,'duracion_cita'=>20,'estado_cita'=>$state,
  'estado_agenda'=>'CONFIRMADA','tipo_agendamiento'=>'REGULAR','precio_programado'=>120,'catalog_price'=>120,'saldo_pendiente'=>120,
  'hora_llegada'=>in_array($state,['EN_ESPERA','EN_ATENCION','ATENDIDO'])?now():null,
  'hora_atencion'=>in_array($state,['EN_ATENCION','ATENDIDO'])?now():null,'hora_atendido'=>$state==='ATENDIDO'?now():null,
  'hora_salida'=>null]);
 $a->patient->update(['nombre'=>'PACIENTE FICTICIO '.$label,'apellido_paterno'=>'AUDITORIA','apellido_materno'=>'LOCAL']);$ids[$label]=$a->id;
}
$admin=$maker->admin();$service=app(\App\Services\Scheduling\OperationalProcedureService::class);
foreach(['LISTO','PROCEDIMIENTOS_PENDIENTES'] as $label){
 $service->mutate($ids[$label],$admin,'selection',['procedures'=>['laboratory'],'expected_version'=>0,'request_key'=>(string)\Illuminate\Support\Str::uuid()]);
 if($label==='LISTO')$service->mutate($ids[$label],$actor,'completion',['procedure'=>'laboratory','completed'=>true,'expected_version'=>1,'request_key'=>(string)\Illuminate\Support\Str::uuid()]);
}
$f=json_decode(file_get_contents($dir.'/browser-fixture.json'),true);$f['audit_ids']=$ids;
file_put_contents($dir.'/browser-fixture.json',json_encode($f,JSON_PRETTY_PRINT));
echo "Isolated functional audit cohort created. No physician identity was invented.\n";
