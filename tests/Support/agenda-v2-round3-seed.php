<?php
require dirname(__DIR__,2).'/vendor/autoload.php';
\Tests\Support\IsolatedAgendaV2Qa::boot(true);
if(PHP_SAPI!=='cli'||getenv('AGENDA_V2_ROUND3_FIXTURES')!=='APPEND_SYNTHETIC_ONLY')throw new RuntimeException('Explicit fictional append required.');
$out=storage_path('app/qa-v2/round3');if(!is_dir($out))mkdir($out,0777,true);
$f=json_decode(file_get_contents(storage_path('app/qa-v2/integration-main/fixtures.json')),true);
$existing=is_file($out.'/fixtures.json')?json_decode(file_get_contents($out.'/fixtures.json'),true):null;
$builder=new class {use \Tests\Concerns\BuildsAgendaLifecycleData;
public function benchmark($f,int $count=6){
 $actor=\App\Models\User::findOrFail($f['users']['commercial']['id']);
 $template=\App\Models\Appointment::findOrFail($f['round3']['exit0']);
 if(app(\App\Services\Billing\AppointmentEconomicPosition::class)->forAppointment($template)['paid_cents']!==0 || (float)$template->total_pagado!==0.0)throw new RuntimeException('Benchmark template must be unpaid and fictional.');
 $catalog=['doctor'=>$template->doctor,'service'=>$template->service,'rate'=>\App\Models\AdditionalRate::findOrFail($template->additional_rate_id)];
 $ids=[];for($i=6;$i<6+$count;$i++){
 $p=$this->createPatient($actor,['nombre'=>'PACIENTE BENCHMARK SALIDA FICTICIO','apellido_paterno'=>'QA','apellido_materno'=>'LOCAL','numero_identidad'=>(string)(79600000+\App\Models\Patient::count()),'historia_clinica'=>'R3-PERF-'.\Illuminate\Support\Str::uuid()]);
 $a=$this->lifecycleAppointment($actor,$catalog,['fecha_cita'=>$f['date'],'hora_cita'=>sprintf('%02d:%02d',9+intdiv($i-6,2),($i%2)*30),'estado_agenda'=>'CONFIRMADA','tipo_agendamiento'=>'REGULAR','estado_cita'=>'ATENDIDO','site_id'=>$f['site_id'],'motivo_consulta'=>'Caso ficticio repetible para medir salida fisica.'],$p);
 $a->forceFill(['hora_atendido'=>now()->subMinutes(10)])->save();
 $ids['exit'.$i]=$a->id;
 }return $ids;
}
public function append($f,$existing){
 if($existing)return $existing;
 $admin=\App\Models\User::findOrFail($f['users']['admin']['id']);$owner=\App\Models\User::findOrFail($f['users']['commercial']['id']);
 $catalog=['doctor'=>\App\Models\Doctor::findOrFail($f['doctor_id']),'service'=>\App\Models\Service::findOrFail($f['service_id']),
 'rate'=>\App\Models\AdditionalRate::findOrFail(\App\Models\Appointment::findOrFail($f['appointments']['direct'])->additional_rate_id)];
 $appointments=[];
 foreach(['unpaid','confirmed','advance','legacy','postoperative','wound',...array_map(fn($n)=>'exit'.$n,range(0,11))] as $i=>$key){
 $patient=$this->createPatient($owner,['nombre'=>'PACIENTE RONDA TRES '.strtoupper($key).' FICTICIO','apellido_paterno'=>'QA','apellido_materno'=>'LOCAL','numero_identidad'=>(string)(79500000+$i),'historia_clinica'=>'R3-FICTICIO-'.$key]);
 $a=$this->lifecycleAppointment($owner,$catalog,['fecha_cita'=>$f['date'],'hora_cita'=>sprintf('%02d:%02d',6+intdiv($i,2),($i%2)*30),'site_id'=>$f['site_id'],
 'estado_agenda'=>in_array($key,['unpaid','advance'])?'PENDIENTE_CONFIRMACION':($key==='legacy'?'LEGADO':'CONFIRMADA'),
 'tipo_agendamiento'=>$key==='legacy'?null:'REGULAR','estado_cita'=>str_starts_with($key,'exit')?'ATENDIDO':'PROGRAMADO',
 'motivo_consulta'=>'Documento y atención exclusivamente ficticios para ronda tres.',
 'is_postoperative'=>$key==='postoperative','has_diabetic_wound'=>$key==='wound'], $patient);
  if(str_starts_with($key,'exit'))$a->forceFill(['hora_atendido'=>now()->subMinutes(10)])->save();
 $appointments[$key]=$a->id;
 }
 return $f+['round3'=>$appointments];
}};
$data=\Illuminate\Support\Facades\DB::transaction(fn()=>$builder->append($f,$existing));
if(in_array('--profile',$argv,true)){
 $ids=\Illuminate\Support\Facades\DB::transaction(fn()=>$builder->benchmark($data,1));$actor=\App\Models\User::findOrFail($data['users']['commercial']['id']);$queries=[];
 \Illuminate\Support\Facades\DB::listen(function($q)use(&$queries){$queries[]=['sql_template'=>$q->sql,'milliseconds'=>$q->time];});
 $started=microtime(true);app(\App\Services\Scheduling\AdmissionCareService::class)->transition($ids['exit6'],$actor,['state'=>'SALIDA_REGISTRADA','expected_state'=>'ATENDIDO','request_key'=>(string)\Illuminate\Support\Str::uuid()]);
 file_put_contents($out.'/sql-departure-profile.json',json_encode(['fictitious_only'=>true,'service_ms'=>round((microtime(true)-$started)*1000,2),'query_count'=>count($queries),'sql_ms'=>array_sum(array_column($queries,'milliseconds')),'remaining_transaction_level'=>\Illuminate\Support\Facades\DB::transactionLevel(),'queries'=>$queries],JSON_PRETTY_PRINT));echo "Profiled one fictional departure; no parameters were logged.\n";exit;
}
if(in_array('--benchmark',$argv,true)){$data['round3']=\Illuminate\Support\Facades\DB::transaction(fn()=>$builder->benchmark($data));file_put_contents($out.'/latest-benchmark.json',json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));echo "Appended six fictional benchmark departures.\n";exit;}
file_put_contents($out.'/fixtures.json',json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo "Round 3 fictional fixtures appended; existing appointments unchanged.\n";
