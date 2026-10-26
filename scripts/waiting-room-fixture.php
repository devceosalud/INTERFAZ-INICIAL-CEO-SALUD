<?php
/** Local-only fixture harness. Clinical actions call the existing authorized ERP services. */
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$database=str_replace('\\','/',(string)realpath((string)config('database.connections.sqlite.database')));
$runtime=str_replace('\\','/',(string)realpath(dirname(base_path()).'/runtime')).'/';
if(!app()->environment('integration')||config('database.default')!=='sqlite'||!str_contains($runtime,'/.local-integration/runtime/')||!str_starts_with(strtolower($database),strtolower($runtime))||!is_file($database)||!env('WR_FIXTURE_STATE')){fwrite(STDERR,"Isolated integration environment required.
");exit(2);}
$stateFile=env('WR_FIXTURE_STATE');
foreach([$stateFile,env('WR_FIXTURE_CREDENTIALS'),config('waiting_room.configuration_file')] as $file){$parent=str_replace('\\','/',(string)realpath(dirname((string)$file))).'/';if($parent!==$runtime){fwrite(STDERR,"Runtime files must remain in the isolated directory.\n");exit(2);}}
$action=$argv[1]??'';$index=(int)($argv[2]??0);
if($action==='seed'){
 if(\App\Models\Appointment::count()>0){fwrite(STDERR,"Already seeded. No destructive reset performed.
");exit(2);}
 $builder=new class {use \Tests\Concerns\BuildsAgendaLifecycleData;
 public function build():array {
  $actor=$this->agendaReader('ADMISION');$actor->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate(\App\Support\Scheduling\SchedulingCapability::UPDATE,'web'));
  $site=$this->createSite(['codigo'=>'FIXTURE_A','nombre'=>'Sede de integración ficticia']);$other=$this->createSite(['codigo'=>'FIXTURE_B','nombre'=>'Otra sede ficticia']);
  $people=[['María','López','Torres'],['Carlos','Ramírez','Díaz'],['Ana','Díaz','Rey']];$professionals=[['Dra. Valeria Torres','la doctora Valeria Torres'],['Dr. Andrés Medina','el doctor Andrés Medina'],['Dra. Lucía Ramos','la doctora Lucía Ramos']];
  $rows=[];$assignments=[];$patients=[];
  foreach($people as $i=>$parts){$catalog=$this->createAppointmentCatalog();$catalog['doctor']->update(['nombre'=>$professionals[$i][0]]);
   $physician=$this->createUserWithRole('MEDICO',['doctor_id'=>$catalog['doctor']->id]);foreach(\App\Support\Scheduling\ClinicalCapability::rolePermissions() as $cap)$physician->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate($cap,'web'));
   $p=$this->createPatient($actor,['nombre'=>$parts[0],'apellido_paterno'=>$parts[1],'apellido_materno'=>$parts[2],'numero_identidad'=>(string)(93123000+$i),'historia_clinica'=>'PRIVATE_FIXTURE_'.$i,'email'=>'private-fixture-'.$i.'@example.invalid']);$patients[]=$p->id;
   $a=$this->lifecycleAppointment($actor,$catalog,['fecha_cita'=>now('America/Lima')->toDateString(),'site_id'=>$site->id,'attendance_mode'=>'PRESENCIAL','observaciones'=>'PRIVATE_OBSERVATIONS_FIXTURE','motivo_consulta'=>'PRIVATE_REASON_FIXTURE'],$p);
   $rows[]=['appointment'=>$a->id,'physician'=>$physician->id];$assignments[]=['site_id'=>$site->id,'day'=>now('America/Lima')->toDateString(),'verified'=>true,'room_ref'=>'fixture-room-'.($i+1),'room_label'=>'0'.($i+1),'doctor_id'=>$catalog['doctor']->id,'professional_label'=>$professionals[$i][0],'professional_speech_label'=>$professionals[$i][1]];
  }
  return ['actor'=>$actor->id,'site'=>$site->id,'other_site'=>$other->id,'rows'=>$rows,'assignments'=>$assignments,'patients'=>$patients];
 }};
 $fixture=$builder->build();$credentials=json_decode(file_get_contents(env('WR_FIXTURE_CREDENTIALS')),true,16,JSON_THROW_ON_ERROR);
 $configuration=['installations'=>[$credentials['key_id']=>['active'=>true,'secret'=>$credentials['secret'],'site_id'=>$fixture['site'],'screen_ref'=>'fixture-screen-a']],
 'privacy'=>['mode'=>'fixtures-only','display_mode'=>'initial_two_surnames','speech_mode'=>'full_given_names','fixture_patient_ids'=>$fixture['patients']], 'assignments'=>$fixture['assignments']];
 file_put_contents(config('waiting_room.configuration_file'),json_encode($configuration,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));file_put_contents($stateFile,json_encode($fixture,JSON_PRETTY_PRINT));echo "Seeded three isolated fictional appointments.
";exit(0);
}
$fixture=json_decode(file_get_contents($stateFile),true,16,JSON_THROW_ON_ERROR);
if($action==='extra'){
 if(count($fixture['rows'])!==3){fwrite(STDERR,"Extra fixture already exists.
");exit(2);}
 $builder=new class {use \Tests\Concerns\BuildsAgendaLifecycleData;
 public function add($actor,$original):array {
  $patient=$this->createPatient($actor,['nombre'=>'Roberto','apellido_paterno'=>'Mogrovejo Reyes','apellido_materno'=>'Fernández','numero_identidad'=>'93123003','historia_clinica'=>'PRIVATE_FIXTURE_3']);
  $catalog=['doctor'=>$original->doctor,'service'=>\App\Models\Service::findOrFail($original->service_id),'rate'=>\App\Models\AdditionalRate::findOrFail($original->additional_rate_id)];
  $a=$this->lifecycleAppointment($actor,$catalog,['site_id'=>$original->site_id,'attendance_mode'=>'PRESENCIAL','fecha_cita'=>now('America/Lima')->toDateString(),'hora_cita'=>'16:00:00'],$patient);return [$a->id,$patient->id];
 }};
 [$id,$patientId]=$builder->add(\App\Models\User::findOrFail($fixture['actor']),\App\Models\Appointment::findOrFail($fixture['rows'][0]['appointment']));
 $fixture['rows'][]=['appointment'=>$id,'physician'=>$fixture['rows'][0]['physician']];$fixture['patients'][]=$patientId;file_put_contents($stateFile,json_encode($fixture,JSON_PRETTY_PRINT));
 $configFile=config('waiting_room.configuration_file');$c=json_decode(file_get_contents($configFile),true);$c['privacy']['fixture_patient_ids'][]=$patientId;file_put_contents($configFile,json_encode($c,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));echo "Created fourth fictional patient; destinations unchanged.
";exit(0);
}
if($action==='access'){
 $actor=\App\Models\User::findOrFail($fixture['actor']);$actor->update(['email'=>'admision-fixture@example.invalid','password'=>\Illuminate\Support\Facades\Hash::make('Local-ERP-Fixture-2026')]);
 foreach($fixture['rows'] as $i=>$r)\App\Models\User::findOrFail($r['physician'])->update(['email'=>'doctor-fixture-'.$i.'@example.invalid','password'=>\Illuminate\Support\Facades\Hash::make('Local-ERP-Fixture-2026')]);
 echo "Fictional staff access configured.
";exit(0);
}
if($action==='renew'){
 foreach($fixture['rows'] as $r)if(\App\Models\Appointment::findOrFail($r['appointment'])->estado_cita!=='ATENDIDO'){fwrite(STDERR,"Finish the fictional episode through official care before renewal.
");exit(2);}
 foreach($fixture['rows'] as &$r){$old=\App\Models\Appointment::findOrFail($r['appointment']);
  $attributes=$old->only(['user_id','patient_id','doctor_id','service_id','additional_rate_id','site_id','attendance_mode','duracion_cita','precio_programado','total_pagado','saldo_pendiente','estado_pagado']);
  $new=\App\Models\Appointment::create($attributes+['numero_cita'=>'FIXTURE-'.\Illuminate\Support\Str::uuid(),'fecha_cita'=>now('America/Lima')->toDateString(),'hora_cita'=>'15:30:00','estado_cita'=>'PROGRAMADO','estado_agenda'=>'LEGADO','fecha_registro'=>now()->toDateString(),'observaciones'=>'PRIVATE_OBSERVATIONS_FIXTURE']);$r['appointment']=$new->id;
 }unset($r);file_put_contents($stateFile,json_encode($fixture,JSON_PRETTY_PRINT));echo "Created new fictional episodes; audit history preserved.
";exit(0);
}
$row=$fixture['rows'][$index]??null;if(!$row){fwrite(STDERR,"Unknown fixture index.
");exit(2);}
$a=\App\Models\Appointment::findOrFail($row['appointment']);$actor=\App\Models\User::findOrFail($fixture['actor']);$service=app(\App\Services\Scheduling\AdmissionCareService::class);$data=['expected_state'=>$a->estado_cita,'request_key'=>(string)\Illuminate\Support\Str::uuid(),'confirmed'=>true];
if($action==='long'){$a->patient->update(['nombre'=>'María','apellido_paterno'=>'Fernández de la Cruz','apellido_materno'=>'Valderrama']);}
elseif($action==='arrive')$a=$service->registerArrival($a->id,$actor,$data);
elseif(in_array($action,['call','start','finish'],true)){$data['state']=['call'=>'LLAMANDO','start'=>'EN_ATENCION','finish'=>'ATENDIDO'][$action];$who=$action==='call'?$actor:\App\Models\User::findOrFail($row['physician']);$a=$service->transition($a->id,$who,$data);}
elseif($action==='reverse'){$data['expected_event_id']=$a->events()->latest('id')->value('id');$data['motivo']='Corrección de prueba ficticia';$a=$service->reverse($a->id,$actor,$data);}
elseif($action!=='status'){fwrite(STDERR,"Allowed: seed, arrive, call, start, finish, reverse, long, status.
");exit(2);}
echo json_encode(['fixture'=>$index,'state'=>$a->estado_cita,'audit_events'=>$a->events()->count(),'physical_departure_recorded'=>$a->hora_salida!==null],JSON_THROW_ON_ERROR).PHP_EOL;
