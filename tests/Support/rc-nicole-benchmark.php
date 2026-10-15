<?php
require dirname(__DIR__,2).'/vendor/autoload.php';
if(!in_array('--reset-owned', $argv,true))throw new RuntimeException('Explicit --reset-owned is required; do not reset the browser jornada.');
$suite=in_array('--suite',$argv,true);
$app=$suite?\Tests\Support\IsolatedRcSuiteQa::boot():\Tests\Support\IsolatedRcQa::boot();
set_exception_handler(static function(\Throwable $e){fwrite(STDERR,'QA benchmark failed: '.get_class($e).' at '.basename($e->getFile()).':'.$e->getLine().PHP_EOL);exit(1);});
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\DoctorService;
use App\Models\Cashier;
use App\Models\CashierShift;
use App\Models\VoucherSerie;
use App\Support\Scheduling\SchedulingCapability as C;
use Spatie\Permission\Models\Permission;

// IsolatedRcQa verifies port, schema, user, datadir and version before this reset.
\Illuminate\Support\Facades\Artisan::call('migrate:fresh',['--force'=>true]);
$fixture=new class {use \Tests\Concerns\BuildsAgendaLifecycleData;
    public function base():array{
        $actor=$this->agendaReader('ADMISION');
        $catalog=$this->createAppointmentCatalog();$catalog['rate']->update(['nombre'=>'TARIFA ESTANDAR','tipo_tarifa'=>'MONTO_FIJO']);
        $site=$this->createSite(['codigo'=>'QA-BENCH','nombre'=>'Magdalena del Mar']);
        $doctors=collect([$catalog['doctor']]);
        for($i=1;$i<20;$i++){
            $doctor=Doctor::create(['nombre'=>'MEDICO SINTETICO '.$i,'specialty_id'=>$catalog['specialty']->id,'estado'=>'ACTIVO']);
            DoctorService::create(['doctor_id'=>$doctor->id,'service_id'=>$catalog['service']->id,'precio_primera_consulta'=>100,'precio_reconsulta'=>50,'dias_reconsulta'=>15,'estado'=>'ACTIVO']);$doctors->push($doctor);
        }
        DoctorSchedule::whereKey($catalog['schedule']->id)->delete(); // Only this freshly seeded fixture, never inherited data.
        foreach($doctors as $doctor)for($day=0;$day<5;$day++){
            $date=\Carbon\Carbon::parse('2026-10-15')->addDays($day);
            DoctorSchedule::create(['doctor_id'=>$doctor->id,'site_id'=>$site->id,'fecha_cita'=>$date->toDateString(),'dia_semana'=>$date->dayOfWeekIso,'hora_inicio'=>'08:00','hora_fin'=>'18:00','duracion_cita'=>30,'estado'=>'ACTIVO']);
        }
        $patient=$this->createPatient($actor,['nombre'=>'PACIENTE SINTETICO 0','numero_identidad'=>'80000000','historia_clinica'=>'QA-BENCH-0']);
        $a=$this->lifecycleAppointment($actor,$catalog,['fecha_cita'=>'2026-10-15','hora_cita'=>'08:00','estado_agenda'=>'CONFIRMADA','tipo_agendamiento'=>'REGULAR','economic_source'=>'VOUCHER','site_id'=>$site->id,'total_pagado'=>50,'saldo_pendiente'=>50,'estado_pagado'=>'PARCIAL'],$patient);
        $box=Cashier::create(['nombre'=>'QA BENCH ONLY','estado'=>'ACTIVO']);
        $shift=CashierShift::create(['cashier_id'=>$box->id,'user_id'=>$actor->id,'monto_apertura'=>0,'abierto_en'=>now(),'estado'=>'ABIERTO']);
        VoucherSerie::create(['cashier_id'=>$box->id,'tipo_comprobante'=>'TICKET','serie'=>'QB01','correlativo_actual'=>0,'estado'=>'ACTIVO']);
        $voucher=DB::transaction(fn()=>app(\App\Services\Billing\AppointmentTicketService::class)->create($a,$shift,$actor->id));
        $payment=DB::transaction(fn()=>app(\App\Services\Billing\VoucherPaymentRecorder::class)->record($voucher,$shift,$actor->id,5000,'EFECTIVO',null,null));
        $voucher->update(['estado'=>'PARCIAL']);
        return [$actor,$catalog,$doctors,$patient,$a,$voucher,$voucher->items()->first(),$payment];
    }
};
[$actor,$catalog,$doctors,$patient,$appointment,$voucher,$item,$payment]=$fixture->base();
$templates=[];foreach(['patients'=>$patient,'appointments'=>$appointment,'vouchers'=>$voucher,'voucher_items'=>$item,'payments'=>$payment] as $table=>$model)$templates[$table]=$model->getAttributes();
$queries=[];DB::listen(function($q)use(&$queries){$queries[]=['sql'=>$q->sql,'ms'=>$q->time];});
$kernel=$app->make(\Illuminate\Contracts\Http\Kernel::class);Auth::login($actor);
$filters=['fecha'=>'2026-10-15','hasta'=>'2026-10-19','site_id'=>null,'page'=>1];
$request=function(array $data)use($kernel){
    $request=\Illuminate\Http\Request::create('/scheduling-mvp/admission/feed','POST',[],[],[],['HTTP_ACCEPT'=>'application/json','CONTENT_TYPE'=>'application/json'],json_encode($data));
    $response=$kernel->handle($request);$kernel->terminate($request,$response);
    if($response->getStatusCode()!==200)throw new RuntimeException('Benchmark HTTP '.$response->getStatusCode().': '.$response->getContent());
    return $response;
};
$percentile=function(array $values,float $p){sort($values);return round($values[max(0,(int)ceil(count($values)*$p)-1)],3);};
$report=['environment'=>['php'=>PHP_VERSION,'os'=>PHP_OS_FAMILY,'mariadb'=>DB::selectOne('SELECT VERSION() version')->version,'samples'=>30,'warmups'=>3,'transport'=>'HTTP kernel in one warm PHP CLI process; no browser/network/TLS time','opcache_cli'=>ini_get('opcache.enable_cli'),'dataset'=>'20 doctors, 5 days, distinct patients, one attributed S/50 payment per S/100 appointment; synthetic only'],
    'proposed_local_targets'=>['p95_ms'=>1000,'max_sql_count'=>30,'max_response_bytes'=>100000,'php_peak_mib'=>256], 'datasets'=>[]];
$current=1;
$sizes=[100,500,2000];foreach($argv as $option)if(str_starts_with($option,'--size=')){$sizes=[(int)substr($option,7)];if(!in_array($sizes[0],[100,500,2000],true))throw new RuntimeException('Unsupported bounded fixture size');}
foreach($sizes as $size){
    $batches=array_fill_keys(array_keys($templates),[]);
    for($i=$current;$i<$size;$i++){
        foreach($templates as $table=>$template){
            $r=$template;$r['id']=$i+1;
            if($table==='patients'){$r['numero_identidad']=(string)(80000000+$i);$r['historia_clinica']='QA-BENCH-'.$i;$r['nombre']='PACIENTE SINTETICO '.$i;}
            elseif($table==='appointments'){$r['patient_id']=$i+1;$r['numero_cita']='QA-BENCH-'.$i;$r['doctor_id']=$doctors[$i%20]->id;$r['fecha_cita']=\Carbon\Carbon::parse('2026-10-15')->addDays(intdiv($i,400))->toDateString();$r['hora_cita']=\Carbon\Carbon::parse('08:00')->addMinutes(intdiv($i%400,20)*30)->format('H:i:s');$r['estado_cita']=['PROGRAMADO','EN_ESPERA','EN_ATENCION','ATENDIDO','CANCELADO','NO_ASISTIO','RETIRO'][$i%7];}
            elseif($table==='vouchers'){$r['patient_id']=$i+1;$r['paga_patient_id']=$i+1;$r['correlativo']=$i+1;}
            elseif($table==='voucher_items'){$r['voucher_id']=$i+1;$r['item_id']=$i+1;$r['doctor_id']=$doctors[$i%20]->id;}
            elseif($table==='payments')$r['voucher_id']=$i+1;
            $batches[$table][]=$r;
        }
    }
    foreach($batches as $table=>$rows)foreach(array_chunk($rows,100) as $chunk)DB::table($table)->insert($chunk);
    $current=$size;
    if(in_array('--prepare-http',$argv,true)){
        if(!$suite)throw new RuntimeException('HTTP load fixtures require the separate suite runtime.');
        $secret=json_decode(preg_replace('/^\xEF\xBB\xBF/','',file_get_contents(storage_path('app/qa-rc/browser-private.json'))),true,512,JSON_THROW_ON_ERROR);
        $accounts=[];
        for($userIndex=1;$userIndex<=8;$userIndex++){
            $user=$userIndex===1?$actor:\App\Models\User::factory()->create();
            $user->forceFill(['email'=>'bench-'.$userIndex.'@example.invalid','password'=>\Illuminate\Support\Facades\Hash::make($secret['password'])])->save();
            foreach([C::MVP_ACCESS,C::VIEW] as $cap)$user->givePermissionTo(Permission::findOrCreate($cap,'web'));
            $accounts[]=['id'=>$user->id,'email'=>$user->email];
        }
        file_put_contents(storage_path('app/qa-rc/benchmark-accounts.json'),json_encode(['size'=>$size,'accounts'=>$accounts]));
        echo 'Prepared bounded HTTP fixture size '.$size.'; no browser-jornada database changed.'.PHP_EOL;
        exit;
    }
    $payload=json_decode($request($filters)->getContent(),true,512,JSON_THROW_ON_ERROR);
    if($payload['total']!==$size||$payload['totals']['pagos_reales']!==number_format($size*50,2,'.',''))throw new RuntimeException('Benchmark fixture total mismatch.');
    foreach(['full','unchanged'] as $mode){
        $data=$filters;if($mode==='unchanged')$data['revision']=$payload['revision'];
        for($warm=0;$warm<3;$warm++)$request($data);
        $durations=[];$counts=[];$sqlMs=[];$bytes=[];$sqlShapes=[];
        for($sample=0;$sample<30;$sample++){
            $queries=[];$start=hrtime(true);$response=$request($data);$durations[]=(hrtime(true)-$start)/1e6;
            $counts[]=count($queries);$sqlMs[]=array_sum(array_column($queries,'ms'));$bytes[]=strlen($response->getContent());
            if($sample===0)$sqlShapes=array_column($queries,'sql');
        }
        $result=['appointments'=>$size,'mode'=>$mode,'p50_ms'=>$percentile($durations,.5),'p95_ms'=>$percentile($durations,.95),'max_ms'=>round(max($durations),3),
            'sql_count_min'=>min($counts),'sql_count_max'=>max($counts),'sql_p95_ms'=>$percentile($sqlMs,.95),'response_bytes_max'=>max($bytes),'php_process_peak_mib'=>round(memory_get_peak_usage(true)/1048576,2),'sql_shapes'=>$sqlShapes];
        $result['meets_proposed_local_targets']=$result['p95_ms']<=1000&&$result['sql_count_max']<=30&&$result['response_bytes_max']<=100000&&$result['php_process_peak_mib']<=256;
        $report['datasets'][]=$result;
        file_put_contents(storage_path('app/qa-rc/performance-'.($argv[1]??'baseline').'.json'),json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        echo json_encode(array_diff_key($result,['sql_shapes'=>1]),JSON_UNESCAPED_UNICODE).PHP_EOL;
    }
}
