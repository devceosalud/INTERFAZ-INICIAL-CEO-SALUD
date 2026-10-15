<?php
require dirname(__DIR__,2).'/vendor/autoload.php';
$app=\Tests\Support\IsolatedWorkerQa::boot();
$password=getenv('WORKER_QA_BROWSER_PASSWORD');
if(!is_string($password)||strlen($password)<24)throw new RuntimeException('Ephemeral QA password required.');
\Illuminate\Support\Facades\Artisan::call('migrate:fresh',['--force'=>true]);
$fixture=new class {use \Tests\Concerns\BuildsAgendaLifecycleData;
    public function run($password):array{
        $users=[];foreach(['admin','admin2','worker'] as $profile){
            $u=$this->createUserWithRole($profile==='worker'?'ADMISION':'ADMINISTRADOR',[
                'name'=>'QA FICTICIO '.$profile,'email'=>'qa-'.$profile.'@example.invalid',
                'password'=>\Illuminate\Support\Facades\Hash::make($password)]);
            foreach([\App\Support\Scheduling\SchedulingCapability::MVP_ACCESS,\App\Support\Scheduling\SchedulingCapability::VIEW] as $name)
                $u->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate($name,'web'));
            $users[$profile]=['id'=>$u->id,'email'=>$u->email];
        }return $users;
    }
};
file_put_contents(storage_path('app/qa-workers/browser-fixtures.json'),json_encode($fixture->run($password),JSON_PRETTY_PRINT));
echo 'Fictitious worker fixtures ready in owned QA runtime.'.PHP_EOL;
