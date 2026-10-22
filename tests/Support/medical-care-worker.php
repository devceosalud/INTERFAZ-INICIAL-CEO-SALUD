<?php
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__,2).'/vendor/autoload.php';
$root=dirname(__DIR__,2);$job=realpath($argv[1]??'');$dir=realpath($root.'/storage/app/qa-medical-race');
if(!$dir||!$job||!str_starts_with(strtolower($job),strtolower($dir.DIRECTORY_SEPARATOR)))throw new RuntimeException('Invalid isolated job.');
if(getenv('AGENDA_V2_QA_RUNTIME')!=='agenda-v2-11.8.9')throw new RuntimeException('Owned disposable MariaDB only.');
$app=\Tests\Support\IsolatedAgendaV2Qa::boot();
\Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-10-21 09:00','America/Lima'));
$data=json_decode(file_get_contents($job),true,512,JSON_THROW_ON_ERROR);
$actor=\App\Models\User::findOrFail($data['actor_id']);
$wait=function($file){$deadline=microtime(true)+20;while(!is_file($file)){if(microtime(true)>$deadline)throw new RuntimeException('QA barrier timeout.');usleep(10000);}};
$outer=false;
try{
    touch($job.'.ready');$wait($data['release']);
    if($data['leader']??false){
        \Illuminate\Support\Facades\DB::beginTransaction();$outer=true;
        if($data['action']==='LINK'){
            app(\App\Services\UserAccountAccess::class)->lockAdministrator($actor,[$data['target_id']]);
            \App\Models\Doctor::whereKey($data['old_doctor_id'])->lockForUpdate()->firstOrFail();
        }else{
            \App\Support\Scheduling\ClinicalAccess::lock($actor,$data['appointment_id'],\App\Services\Scheduling\MedicalCareService::capability($data['clinical_action']));
        }
        touch($data['locked']);$wait($data['attempted']);usleep(300000);
    }elseif(!empty($data['locked'])){$wait($data['locked']);touch($data['attempted']);}
    if($data['action']==='LINK')app(\App\Services\UserDoctorLink::class)->change($data['target_id'],$actor,$data['payload']);
    else app(\App\Services\Scheduling\MedicalCareService::class)->perform($data['appointment_id'],$actor,$data['clinical_action'],$data['payload']);
    if($outer)\Illuminate\Support\Facades\DB::commit();
    echo json_encode(['status'=>200]);
}catch(Throwable $e){
    if($outer && \Illuminate\Support\Facades\DB::transactionLevel())\Illuminate\Support\Facades\DB::rollBack();
    $status=$e instanceof \Illuminate\Validation\ValidationException?422:($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface?$e->getStatusCode():500);
    echo json_encode(['status'=>$status,'error_class'=>get_class($e)]);
}
