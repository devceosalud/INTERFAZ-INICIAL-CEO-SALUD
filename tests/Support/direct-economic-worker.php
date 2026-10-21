<?php
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__,2).'/vendor/autoload.php';
$root=dirname(__DIR__,2);$job=realpath($argv[1]??'');$dir=realpath($root.'/storage/app/qa-direct-race');
if(!$dir||!$job||!str_starts_with(strtolower($job),strtolower($dir.DIRECTORY_SEPARATOR)))throw new RuntimeException('Invalid isolated job.');
if(getenv('AGENDA_V2_QA_RUNTIME')!=='agenda-v2-11.8.9')throw new RuntimeException('Owned MariaDB runtime only.');
$app=\Tests\Support\IsolatedAgendaV2Qa::boot();
\Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-10-21 09:00','America/Lima'));
$data=json_decode(file_get_contents($job),true,512,JSON_THROW_ON_ERROR);
\Illuminate\Support\Facades\Auth::login(\App\Models\User::findOrFail($data['actor_id']));
touch($job.'.ready');$deadline=microtime(true)+20;
while(!file_exists($data['release'])){if(microtime(true)>$deadline)throw new RuntimeException('Barrier timeout.');usleep(10000);}
$outer=false;
try{
    if($data['action']==='FISCAL_GUARD'){
        $component=app(\App\Http\Livewire\Sales::class);
        $component->turno=\App\Models\CashierShift::findOrFail($data['shift_id']);
        $component->ticketOrigenId=$data['ticket_id'];$component->atiendeId=$data['patient_id'];$component->carrito=$data['cart'];
        $paused=false;
        \Illuminate\Support\Facades\DB::listen(function($query)use(&$paused,$data,$deadline){
            if(!$paused && \Illuminate\Support\Facades\DB::transactionLevel()>0 && stripos($query->sql,'select * from `appointments`')!==false && stripos($query->sql,'for update')===false){
                $paused=true;touch($data['old_read']);
                while(!file_exists($data['edit_done'])){if(microtime(true)>$deadline)throw new RuntimeException('Fiscal read barrier timeout.');usleep(10000);}
            }
        });
        \Illuminate\Support\Facades\DB::transaction(function()use($component){$method=new ReflectionMethod($component,'lockAppointmentSale');$method->setAccessible(true);$method->invoke($component);});
        echo json_encode(['status'=>200,'guard_passed'=>true]);exit;
    }
    if(!empty($data['old_read']))while(!file_exists($data['old_read'])){if(microtime(true)>$deadline)throw new RuntimeException('Fiscal initial read timeout.');usleep(10000);}
    if($data['leader']??false){
        \Illuminate\Support\Facades\DB::beginTransaction();$outer=true;
        \App\Models\CashierShift::whereKey($data['shift_id'])->lockForUpdate()->firstOrFail();touch($data['locked']);usleep(300000);
    }elseif(!empty($data['locked'])){
        while(!file_exists($data['locked'])){if(microtime(true)>$deadline)throw new RuntimeException('Lock barrier timeout.');usleep(10000);}
    }
    if($data['action']==='EDIT')$result=app(\App\Services\Scheduling\AppointmentEconomicEdit::class)->save($data['appointment_id'],auth()->user(),$data['payload']);
    else{
        $component=app(\App\Http\Livewire\CashierShifts::class);$component->mount();$component->montoContado=70;$component->cerrarTurno();$result=['closed'=>true];
    }
    if($outer)\Illuminate\Support\Facades\DB::commit();
    if(!empty($data['edit_done']))touch($data['edit_done']);
    echo json_encode(['status'=>200,'result'=>$result]);
}catch(Throwable $e){
    if($outer && \Illuminate\Support\Facades\DB::transactionLevel())\Illuminate\Support\Facades\DB::rollBack();
    $status=$e instanceof \Illuminate\Validation\ValidationException?422:($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface?$e->getStatusCode():500);
    echo json_encode(['status'=>$status,'error_class'=>get_class($e)]);
}
