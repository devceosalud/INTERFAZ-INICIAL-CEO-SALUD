<?php
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__,2).'/vendor/autoload.php';
\Tests\Support\RescheduleVisualQa::boot();
$dir=storage_path('app/qa-reschedule-visual');
if(file_exists($dir.'/integration-fixture.json')){
    if(($argv[1]??'')!=='--new-run')throw new RuntimeException('Existing evidence preserved.');
    foreach(['fixture','secret'] as $name)copy($dir.'/integration-'.$name.'.json',$dir.'/integration-'.$name.'-'.time().'.json');
}
$helper=new class{
    use \Tests\Concerns\BuildsRescheduleVisualData;
    public function build():array{
        $f=$this->rescheduleVisualFixture();$actor=$f['actor'];$catalog=$f['catalog'];$cases=['paid'=>$f['a']->id];
        $actor->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate(\App\Support\Scheduling\SchedulingCapability::UPDATE,'web'));
        foreach(['normal','manual','declined','without_deposit','private'] as $i=>$key){
            $a=$this->lifecycleAppointment($actor,$catalog,['fecha_cita'=>'2026-10-21','hora_cita'=>sprintf('%02d:%02d',12+intdiv(($i+2)*20,60),(($i+2)*20)%60),
                'duracion_cita'=>20,'precio_programado'=>120,'catalog_price'=>120,'saldo_pendiente'=>120,
                'estado_agenda'=>in_array($key,['without_deposit','private'])?'PENDIENTE_CONFIRMACION':'CONFIRMADA']);
            $a->patient->update(['nombre'=>'PACIENTE FICTICIO '.strtoupper($key),'apellido_paterno'=>'QA INTEGRACION']);$cases[$key]=$a->id;
        }
        $medical=$this->createUserWithRole('MEDICO');$medical->forceFill(['doctor_id'=>$catalog['doctor']->id])->save();
        $other=$this->createUserWithRole('MEDICO');$other->forceFill(['doctor_id'=>$this->createAppointmentCatalog()['doctor']->id])->save();
        return $f+['cases'=>$cases,'medical'=>$medical,'other'=>$other,'commercial'=>$this->agendaReader('COMERCIAL'),'admin'=>$this->agendaReader('ADMINISTRADOR')];
    }
};
$f=\Illuminate\Support\Facades\DB::transaction(fn()=>$helper->build());$password=bin2hex(random_bytes(24));$accounts=[];$run=bin2hex(random_bytes(4));
foreach(['actor','medical','other','commercial','admin'] as $key){$u=$f[$key];$email='integration-'.$run.'-'.$key.'@example.invalid';
    $u->forceFill(['email'=>$email,'password'=>\Illuminate\Support\Facades\Hash::make($password),'name'=>'USUARIO FICTICIO '.strtoupper($key)])->save();$accounts[$key]=$email;}
file_put_contents($dir.'/integration-secret.json',json_encode(['accounts'=>$accounts,'password'=>$password]));
file_put_contents($dir.'/integration-fixture.json',json_encode(['cases'=>$f['cases'],'date'=>'2026-10-21','doctor_id'=>$f['catalog']['doctor']->id],JSON_PRETTY_PRINT));
echo "Fictional integrated cohort created in isolated browser DB.\n";
