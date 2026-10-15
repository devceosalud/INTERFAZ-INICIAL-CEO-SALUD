<?php
namespace Tests\Integration;
use App\Models\User;
use App\Models\UserAccountEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;
class WorkerConcurrencyTest extends TestCase
{
    use BuildsAgendaLifecycleData;
    private $actor;
    public function createApplication(){return \Tests\Support\IsolatedWorkerQa::boot();}
    protected function setUp():void
    {
        parent::setUp();$this->artisan('migrate:fresh',['--force'=>true])->assertExitCode(0);
        $this->actor=$this->createUserWithRole('ADMINISTRADOR');
        $this->beforeApplicationDestroyed(function():void{
            $this->artisan('migrate:fresh',['--force'=>true])->assertExitCode(0);
            \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated=false;
        });
    }
    private function race(array $requests): array
    {
        $dir = storage_path('app/qa-workers/races/'.Str::uuid()); mkdir($dir, 0777, true);
        $release = $dir.'/release'; $processes = [];
        foreach ($requests as $i => $request) {
            $input = $dir.'/'.$i.'.input.json'; $output = $dir.'/'.$i.'.output.json';
            file_put_contents($input, json_encode(['actor' => $request['actor'], 'release' => $release, 'path' => $request['path'], 'payload' => $request['payload']]));
            $process = new Process([PHP_BINARY, base_path('tests/Support/worker-qa-request.php'), $input, $output], base_path(), null, null, 30);
            $process->start(); $processes[] = [$process, $output];
        }
        try {
            $deadline = microtime(true) + 15;
            do {
                $ready = true;
                foreach ($processes as [$process, $output]) { $ready = $ready && file_exists($output.'.ready'); }
                if ($ready) { break; } usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertTrue($ready, 'Workers did not reach the QA barrier.'); touch($release);
            $results = [];
            foreach ($processes as [$process, $output]) {
                $process->wait(); $this->assertTrue($process->isSuccessful(), $process->getOutput().$process->getErrorOutput());
                $results[] = json_decode(file_get_contents($output), true, 512, JSON_THROW_ON_ERROR);
            }
            return $results;
        } finally { foreach ($processes as [$process]) { if ($process->isRunning()) { $process->stop(); } } }
    }
    private function statuses(array $results): array { $statuses = array_column($results, 'status'); sort($statuses); return $statuses; }

    public function test_two_requests_cannot_create_two_workers_with_the_same_dni():void
    {
        $payload=['nombre_usuario'=>'TRABAJADOR QA CONCURRENCIA','email'=>'race-a@example.invalid','dni'=>'70000777','password'=>'qa-password-only'];
        $requests=[];foreach(['race-a@example.invalid','race-b@example.invalid'] as $email)$requests[]=['actor'=>$this->actor->id,
            'path'=>'/admin/user/store','payload'=>array_replace($payload,['email'=>$email])];
        $results=$this->race($requests);$this->assertSame([200,200],$this->statuses($results));
        $codes=array_column(array_column($results,'body'),'code');sort($codes);$this->assertSame([0,1],$codes);
        $this->assertSame(1,User::where('dni','70000777')->count());$this->assertSame(1,UserAccountEvent::count());
        $this->assertDatabaseCount('appointments',0);$this->assertDatabaseCount('payments',0);
    }
    public function test_simultaneous_deactivation_retry_is_idempotent():void
    {
        $worker=$this->createUserWithRole('ADMISION');
        $request=['actor'=>$this->actor->id,'path'=>'/admin/user/'.$worker->id.'/access-state','payload'=>[
            'active'=>false,'expected_version'=>0,'request_key'=>(string)Str::uuid(),'reason'=>'QA desactivación simultánea']];
        $this->assertSame([200,200],$this->statuses($this->race([$request,$request])));
        $this->assertFalse($worker->fresh()->is_active);$this->assertSame(1,$worker->fresh()->auth_version);
        $this->assertSame(1,UserAccountEvent::count());$this->assertTrue($worker->fresh()->hasRole('ADMISION'));
    }
    public function test_two_administrators_cannot_disable_each_other_and_leave_no_active_administrator():void
    {
        $other=$this->createUserWithRole('ADMINISTRADOR');$requests=[];
        foreach([[$this->actor,$other],[$other,$this->actor]] as [$actor,$target])$requests[]=['actor'=>$actor->id,
            'path'=>'/admin/user/'.$target->id.'/access-state','payload'=>['active'=>false,'expected_version'=>0,
                'request_key'=>(string)Str::uuid(),'reason'=>'QA carrera entre administradores']];
        $statuses=$this->statuses($this->race($requests));$this->assertSame(200,$statuses[0]);$this->assertContains($statuses[1],[401,403]);
        $this->assertSame(1,User::where('is_active',true)->whereHas('roles',fn($q)=>$q->where('name','ADMINISTRADOR'))->count());
        $this->assertSame(1,UserAccountEvent::count());$this->assertDatabaseCount('model_has_roles',2);
    }
}
