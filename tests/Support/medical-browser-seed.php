<?php
// The shared bootstrap refuses .env and pre-existing DBs, disables outbound services
// and uses only its fixed fictional SQLite database. No production bootstrap allowed.
require __DIR__.'/reschedule-visual-browser-seed.php';
$actor=$data['actor'];$actor->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate(\App\Support\Scheduling\SchedulingCapability::UPDATE,'web'));
$data['a']->patient->update(['nombre'=>'PACIENTE FICTICIO CLINICO','apellido_paterno'=>'QA','apellido_materno'=>'LOCAL']);
$helper=new class{
    use \Tests\Concerns\BuildsAgendaLifecycleData;
    public function admin(){return $this->agendaReader('ADMINISTRADOR');}
    public function user(array $attributes){return $this->createUser($attributes);}
    public function medical(array $attributes){$u=$this->createUserWithRole('MEDICO');$u->forceFill($attributes)->save();return $u;}
    public function catalog(){return $this->createAppointmentCatalog();}
};
$admin=$helper->admin();$admin->forceFill(['name'=>'ADMIN FICTICIO MEDICO','email'=>'medical-admin@example.invalid','password'=>\Illuminate\Support\Facades\Hash::make($password)])->save();
$existing=$helper->user(['name'=>'CUENTA EXISTENTE MEDICO FICTICIO','email'=>'medical-existing@example.invalid','password'=>\Illuminate\Support\Facades\Hash::make($password)]);
$other=$helper->catalog();
$wrong=$helper->medical(['name'=>'OTRO MEDICO FICTICIO','email'=>'medical-other@example.invalid','password'=>\Illuminate\Support\Facades\Hash::make($password),'doctor_id'=>$other['doctor']->id]);
$unlinked=$helper->medical(['name'=>'MEDICO FICTICIO SIN VINCULO','email'=>'medical-unlinked@example.invalid','password'=>\Illuminate\Support\Facades\Hash::make($password)]);
$f=json_decode(file_get_contents($dir.'/browser-fixture.json'),true);
$f+=['admin_email'=>$admin->email,'existing_email'=>$existing->email,'existing_id'=>$existing->id,
    'wrong_email'=>$wrong->email,'unlinked_email'=>$unlinked->email,
    'medical_role_id'=>\Spatie\Permission\Models\Role::findByName('MEDICO','web')->id,'initial_users'=>\App\Models\User::count()];
file_put_contents($dir.'/browser-fixture.json',json_encode($f,JSON_PRETTY_PRINT));
echo "Fictitious existing medical account remains unlinked and without role, awaiting explicit admin UI verification.\n";
