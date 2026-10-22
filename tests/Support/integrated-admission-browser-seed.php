<?php
// Local fictional bootstrap refuses .env, existing databases and external services.
require __DIR__.'/reschedule-visual-browser-seed.php';
$helper=new class{
    use \Tests\Concerns\BuildsAgendaLifecycleData;
    public function worker(string $role){return $this->agendaReader($role);}
    public function medical(){return $this->createUserWithRole('MEDICO');}
};
$commercial=$helper->worker('COMERCIAL');
$medical=$helper->medical();$medical->assignRole(\Spatie\Permission\Models\Role::findOrCreate('ADMINISTRADOR','web'));
foreach([\App\Support\Scheduling\SchedulingCapability::MVP_ACCESS,\App\Support\Scheduling\SchedulingCapability::VIEW,\App\Support\Scheduling\SchedulingCapability::UPDATE] as $cap)
    $medical->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate($cap,'web'));
$medical->forceFill(['doctor_id'=>$data['a']->doctor_id,'name'=>'MEDICO ADMIN FICTICIO QA','email'=>'integrated-medical@example.invalid','password'=>\Illuminate\Support\Facades\Hash::make($password)])->save();
$commercial->forceFill(['name'=>'COMERCIAL FICTICIO QA','email'=>'integrated-commercial@example.invalid','password'=>\Illuminate\Support\Facades\Hash::make($password)])->save();
$data['actor']->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate(\App\Support\Scheduling\SchedulingCapability::UPDATE,'web'));
$data['a']->patient->update(['nombre'=>'PACIENTE FICTICIO INTEGRADO','apellido_paterno'=>'QA','apellido_materno'=>'LOCAL']);
$f=json_decode(file_get_contents($dir.'/browser-fixture.json'),true);
$f+=['medical_email'=>$medical->email,'medical_id'=>$medical->id,'commercial_email'=>$commercial->email,'commercial_id'=>$commercial->id];
file_put_contents($dir.'/browser-fixture.json',json_encode($f,JSON_PRETTY_PRINT));
echo "Integrated Admission fictional cohort ready; no real accounts modified.\n";
