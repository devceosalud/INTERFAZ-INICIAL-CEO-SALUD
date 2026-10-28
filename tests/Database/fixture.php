<?php
/** Loopback MariaDB fictitious integration only; no HTTP fixture route. */
require __DIR__.'/../../vendor/autoload.php';
$app=\Tests\Support\WaitingRoomMariaDbQa::boot(true);
$runtime=realpath(dirname(base_path()).'/runtime/clinical-review-20261028');
$stateFile=$runtime.'/fixture-state.json';$action=$argv[1]??'status';$index=(int)($argv[2]??0);
if($action==='seed'){
    if(\App\Models\Appointment::count()!==0||is_file($stateFile))throw new \RuntimeException('Refusing to overwrite fixtures.');
    $builder=new class {use \Tests\Concerns\BuildsAgendaLifecycleData;
      public function build():array {
        $actor=$this->agendaReader('ADMISION');$actor->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate(\App\Support\Scheduling\SchedulingCapability::UPDATE,'web'));
        $site=$this->createSite(['nombre'=>'Sede de integración ficticia','codigo'=>'WR_SYNTHETIC']);$rows=[];$patients=[];$assignments=[];
        foreach([['María','López','Torres'],['Carlos','Ramírez','Díaz'],['Ana','Fernández de la Cruz','Valderrama']] as $i=>$parts){
          $catalog=$this->createAppointmentCatalog();$doctor=$this->createUserWithRole('MEDICO',['doctor_id'=>$catalog['doctor']->id]);
          foreach(\App\Support\Scheduling\ClinicalCapability::rolePermissions() as $cap)$doctor->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate($cap,'web'));
          $p=$this->createPatient($actor,['nombre'=>$parts[0],'apellido_paterno'=>$parts[1],'apellido_materno'=>$parts[2],'numero_identidad'=>(string)(93123000+$i),'historia_clinica'=>'PRIVATE_FIXTURE_'.$i]);
          $a=$this->lifecycleAppointment($actor,$catalog,['site_id'=>$site->id,'attendance_mode'=>'PRESENCIAL','fecha_cita'=>now('America/Lima')->toDateString(),'observaciones'=>'PRIVATE_OBSERVATIONS_FIXTURE'],$p);
          $rows[]=['appointment'=>$a->id,'physician'=>$doctor->id];$patients[]=$p->id;
          $assignments[]=['site_id'=>$site->id,'day'=>now('America/Lima')->toDateString(),'verified'=>true,'room_ref'=>'fixture-room-'.($i+1),'room_label'=>'0'.($i+1),'doctor_id'=>$catalog['doctor']->id,'professional_label'=>'Dra. Valeria Torres','professional_speech_label'=>'la doctora Valeria Torres'];
        }
        return ['actor'=>$actor->id,'site'=>$site->id,'rows'=>$rows,'patients'=>$patients,'assignments'=>$assignments];
      }
    };
    $state=$builder->build();$id='isolated-mariadb-20261028';$secret=bin2hex(random_bytes(32));
    $configuration=['installations'=>[$id=>['active'=>true,'secret'=>$secret,'site_id'=>$state['site'],'screen_ref'=>'fixture-screen-a']],
      'privacy'=>['mode'=>'fixtures-only','display_mode'=>'initial_two_surnames','speech_mode'=>'full_given_names','fixture_patient_ids'=>$state['patients']], 'assignments'=>$state['assignments']];
    file_put_contents($runtime.'/erp-config.json',json_encode($configuration));file_put_contents($stateFile,json_encode($state));
    $ref=fn($kind,$v)=>$kind.'_'.hash_hmac('sha256',implode('|',[$kind,'fixture-screen-a',$state['site'],$v]),config('waiting_room.reference_key'));
    $password=bin2hex(random_bytes(18));
    file_put_contents($runtime.'/caller-client.json',json_encode(['endpoint'=>'http://127.0.0.1:4191/api/integration/v1/waiting-room/snapshot','key_id'=>$id,'secret'=>$secret,
      'screen_ref'=>$ref('screen','fixture-screen-a'),'site_ref'=>$ref('site',$state['site']),'operator_password_hash'=>password_hash($password,PASSWORD_BCRYPT),
      'data_class'=>'synthetic','credential_purpose'=>'synthetic','voice_agent_id'=>'local-review-dora']));
    file_put_contents($runtime.'/access.json',json_encode(['password'=>$password]));echo "Three fictitious appointments seeded in owned MariaDB.\n";exit;
}
$s=json_decode(file_get_contents($stateFile),true,16,JSON_THROW_ON_ERROR);$r=$s['rows'][$index]??null;if(!$r)throw new \RuntimeException('Unknown fixture.');
$a=\App\Models\Appointment::findOrFail($r['appointment']);$service=app(\App\Services\Scheduling\AdmissionCareService::class);
$d=['expected_state'=>$a->estado_cita,'request_key'=>(string)\Illuminate\Support\Str::uuid(),'confirmed'=>true];
if($action==='arrive')$a=$service->registerArrival($a->id,\App\Models\User::findOrFail($s['actor']),$d);
elseif(in_array($action,['call','start','finish'],true)){
 $d['state']=['call'=>'LLAMANDO','start'=>'EN_ATENCION','finish'=>'ATENDIDO'][$action];$who=$action==='call'?$s['actor']:$r['physician'];
 $a=$service->transition($a->id,\App\Models\User::findOrFail($who),$d);
}elseif($action!=='status')throw new \RuntimeException('Unsupported fixture action.');
echo json_encode(['fixture'=>$index,'state'=>$a->estado_cita,'events'=>$a->events()->count()]).PHP_EOL;
