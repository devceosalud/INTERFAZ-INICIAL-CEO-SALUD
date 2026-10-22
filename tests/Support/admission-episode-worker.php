<?php
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__,2).'/vendor/autoload.php';
$dir=realpath(dirname(__DIR__,2).'/storage/app/qa-episode-race');$job=realpath($argv[1]??'');
if(!$dir||!$job||!str_starts_with(strtolower($job),strtolower($dir.DIRECTORY_SEPARATOR))||getenv('AGENDA_V2_QA_RUNTIME')!=='agenda-v2-11.8.9')throw new RuntimeException('Owned isolated worker only.');
\Tests\Support\IsolatedAgendaV2Qa::boot();\Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-10-21 09:00','America/Lima'));
$d=json_decode(file_get_contents($job),true,512,JSON_THROW_ON_ERROR);$u=\App\Models\User::findOrFail($d['actor']);
touch($job.'.ready');$deadline=microtime(true)+20;
while(!file_exists($d['release'])){if(microtime(true)>$deadline)throw new RuntimeException('Barrier timeout');usleep(10000);}
try{
    if($d['stale_snapshot']??false){
        $paused=false;
        \Illuminate\Support\Facades\DB::listen(function($query)use(&$paused,$d,$deadline){
            if(!$paused && \Illuminate\Support\Facades\DB::transactionLevel()>0 && str_contains($query->sql,'insert into `appointment_operations`')){
                $paused=true;\App\Models\AppointmentEvent::where('appointment_id',$d['id'])->get();touch($d['snapshot']);
                while(!file_exists($d['committed'])){if(microtime(true)>$deadline)throw new RuntimeException('Snapshot barrier timeout');usleep(10000);}
            }
        });
    }elseif(isset($d['snapshot']))while(!file_exists($d['snapshot'])){if(microtime(true)>$deadline)throw new RuntimeException('Read barrier timeout');usleep(10000);}
    $s=app(\App\Services\Scheduling\AppointmentReevaluationService::class);
    if($d['action']==='resume')$s->physicianDecision($d['id'],$u,$d['payload']+['action'=>'resume']);
    elseif($d['action']==='confirm')$s->confirmOperational($d['id'],$u,$d['payload']);
    else $s->closeDeclined($d['id'],$u,$d['payload']);
    if(\Illuminate\Support\Facades\DB::transactionLevel())\Illuminate\Support\Facades\DB::commit();
    if(isset($d['committed']))touch($d['committed']);echo json_encode(['status'=>200]);
}catch(Throwable $e){
    if(\Illuminate\Support\Facades\DB::transactionLevel())\Illuminate\Support\Facades\DB::rollBack();
    echo json_encode(['status'=>$e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface?$e->getStatusCode():500,'error_class'=>get_class($e),'message'=>$e->getMessage()]);
}
