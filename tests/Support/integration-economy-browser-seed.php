<?php
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__,2).'/vendor/autoload.php';\Tests\Support\RescheduleVisualQa::boot();
$dir=storage_path('app/qa-reschedule-visual');if(file_exists($dir.'/economy-fixture.json'))throw new RuntimeException('Existing cohort preserved.');
$helper=new class{use \Tests\Concerns\BuildsRescheduleVisualData;public function build():array{
    $f=$this->rescheduleVisualFixture();$admin=$this->agendaReader('ADMINISTRADOR');
    foreach([\App\Support\Scheduling\SchedulingCapability::UPDATE,\App\Support\Scheduling\SchedulingCapability::RESCHEDULE,\App\Support\Scheduling\SchedulingCapability::EDIT_PRICING] as $cap)$admin->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate($cap,'web'));
    return $f+['admin'=>$admin];}};
$f=\Illuminate\Support\Facades\DB::transaction(fn()=>$helper->build());$password=bin2hex(random_bytes(24));$admin=$f['admin'];
$admin->forceFill(['email'=>'economic-integration-'.bin2hex(random_bytes(4)).'@example.invalid','password'=>\Illuminate\Support\Facades\Hash::make($password)])->save();
file_put_contents($dir.'/economy-secret.json',json_encode(['email'=>$admin->email,'password'=>$password]));
file_put_contents($dir.'/economy-fixture.json',json_encode(['doctor_id'=>$f['a']->doctor_id,'paid_id'=>$f['a']->id,'actor_id'=>$f['actor']->id],JSON_PRETTY_PRINT));
echo "Fictional economic/reschedule cohort prepared.\n";
