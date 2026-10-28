<?php
/** Explicit, idempotent preparation of fictional jornadas. Never retimes or removes history. */
require __DIR__.'/../vendor/autoload.php';$app=require __DIR__.'/../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$runtime=realpath(dirname(base_path()).'/runtime');$db=realpath(config('database.connections.sqlite.database'));
if(!app()->environment('integration')||config('database.default')!=='sqlite'||!$runtime||!str_starts_with(str_replace('\\','/',$db),str_replace('\\','/',$runtime).'/'))throw new RuntimeException('Isolated SQLite required');
$file=env('WR_FIXTURE_STATE');$fixture=json_decode(file_get_contents($file),true,32,JSON_THROW_ON_ERROR);$configFile=config('waiting_room.configuration_file');$configuration=json_decode(file_get_contents($configFile),true,32,JSON_THROW_ON_ERROR);
if(($configuration['privacy']['mode']??null)!=='fixtures-only'||App\Models\Site::findOrFail($fixture['site'])->codigo!=='FIXTURE_A')throw new RuntimeException('Fiction-only policy required');
$day=now('America/Lima')->toDateString();$count=(int)($argv[1]??3);if(!in_array($count,[0,1,2,3,8],true))throw new RuntimeException('Allowed operational count: 0,1,2,3,8');
$templates=$fixture['assignment_templates']??array_slice($fixture['assignments'],0,3);
if(count($templates)<8){
 $builder=new class {use Tests\Concerns\BuildsAgendaLifecycleData;public function doctor(string $name):int{$c=$this->createAppointmentCatalog();$c['doctor']->update(['nombre'=>$name]);return $c['doctor']->id;}};
 foreach(['Dr. Gabriel Silva','Dra. Camila Castro','Dr. Ricardo Molina','Dra. Daniela Reyes','Dr. Álvaro Díaz'] as $i=>$name){$row=$templates[0];$row['doctor_id']=$builder->doctor($name);$row['room_ref']='fixture-room-'.($i+4);$row['room_label']=str_pad((string)($i+4),2,'0',STR_PAD_LEFT);$row['professional_label']=$name;$row['professional_speech_label']='el profesional '.$name;$templates[]=$row;}
}
if(($fixture['day']??null)!==$day){
 Illuminate\Support\Facades\DB::transaction(function()use(&$fixture,$day){foreach($fixture['rows'] as &$r){$old=App\Models\Appointment::findOrFail($r['appointment']);
  if(!in_array((int)$old->patient_id,array_map('intval',$fixture['patients']),true))throw new RuntimeException('Unknown fixture patient');
  $attributes=$old->only(['user_id','patient_id','doctor_id','service_id','additional_rate_id','site_id','attendance_mode','duracion_cita','precio_programado','total_pagado','saldo_pendiente','estado_pagado']);
  $new=App\Models\Appointment::create($attributes+['numero_cita'=>'FIXTURE-'.Illuminate\Support\Str::uuid(),'fecha_cita'=>$day,'hora_cita'=>'09:00:00','estado_cita'=>'PROGRAMADO','estado_agenda'=>'LEGADO','fecha_registro'=>now()->toDateString()]);$r['appointment']=$new->id;
 }unset($r);});
 $fixture['day']=$day;
}
$closed=array_column(array_slice($templates,$count),'doctor_id');
if(App\Models\Appointment::where('site_id',$fixture['site'])->where('fecha_cita',$day)->whereIn('doctor_id',$closed)->whereIn('estado_cita',['LLAMANDO','EN_ATENCION'])->exists())throw new RuntimeException('Finish or reverse active care before closing its room');
$today=[];foreach($templates as $i=>$row){$row['day']=$day;$row['operational']=$i<$count;$today[]=$row;}
$configuration['assignments']=array_values(array_filter($configuration['assignments'],fn($r)=>$r['day']!==$day));$configuration['assignments']=array_merge($configuration['assignments'],$today);
$fixture['assignment_templates']=$templates;$fixture['assignments']=$today;
file_put_contents($configFile,json_encode($configuration,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));file_put_contents($file,json_encode($fixture,JSON_PRETTY_PRINT));
$actor=App\Models\User::findOrFail($fixture['actor']);$service=app(App\Services\Scheduling\AdmissionCareService::class);
foreach($fixture['rows'] as $r){$a=App\Models\Appointment::findOrFail($r['appointment']);if($a->estado_cita==='PROGRAMADO')$service->registerArrival($a->id,$actor,['expected_state'=>'PROGRAMADO','request_key'=>(string)Illuminate\Support\Str::uuid(),'confirmed'=>true]);}
$accessPath=$runtime.'/access.json';$access=json_decode(file_get_contents($accessPath),true,16,JSON_THROW_ON_ERROR);
if(!isset($access['rotated_at'])){
 $access['operator_password']=rtrim(strtr(base64_encode(random_bytes(24)),'+/','-_'),'=');$access['erp_password']=rtrim(strtr(base64_encode(random_bytes(24)),'+/','-_'),'=');$access['erp_email']=$actor->email;$access['doctor_emails']=[];
 $ids=[];foreach($fixture['rows'] as $r){$u=App\Models\User::findOrFail($r['physician']);$access['doctor_emails'][]=$u->email;if(!in_array($u->id,$ids,true)){$u->update(['password'=>Illuminate\Support\Facades\Hash::make($access['erp_password'])]);$ids[]=$u->id;}}
 $actor->update(['password'=>Illuminate\Support\Facades\Hash::make($access['erp_password'])]);$clientFile=$runtime.'/caller.json';$client=json_decode(file_get_contents($clientFile),true);$client['operator_password_hash']=Illuminate\Support\Facades\Hash::make($access['operator_password']);file_put_contents($clientFile,json_encode($client,JSON_PRETTY_PRINT));
 $access['rotated_at']=gmdate('c');file_put_contents($accessPath,json_encode($access,JSON_PRETTY_PRINT));
}
echo json_encode(['day'=>$day,'operating_rooms'=>$count,'fixture_patients'=>count($fixture['rows']),'history_preserved'=>true,'access_file'=>'runtime/access.json']).PHP_EOL;
