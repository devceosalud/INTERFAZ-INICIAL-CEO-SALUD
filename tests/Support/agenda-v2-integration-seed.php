<?php
require dirname(__DIR__,2).'/vendor/autoload.php';
\Tests\Support\IsolatedAgendaV2Qa::boot(true);
if(PHP_SAPI!=='cli'||getenv('AGENDA_V2_INTEGRATION_FIXTURES')!=='APPEND_SYNTHETIC_ONLY')throw new RuntimeException('Explicit isolated fixture append required.');
$fixtureSet=getenv('AGENDA_V2_INTEGRATION_FIXTURE_SET')?:'integration-main';if(!in_array($fixtureSet,['integration-main','round3-regression'],true))throw new RuntimeException('Fixture set refused.');
$tag=$fixtureSet==='integration-main'?'integration':'r3-regression';
$out=storage_path('app/qa-v2/'.$fixtureSet);if(!is_dir($out))mkdir($out,0777,true);
$existing=file_exists($out.'/fixtures.json')?json_decode(file_get_contents($out.'/fixtures.json'),true,512,JSON_THROW_ON_ERROR):null;
$secret=json_decode(file_get_contents(storage_path('app/qa-v2/browser-private.json')),true,512,JSON_THROW_ON_ERROR);
$fixture=new class {
    use \Tests\Concerns\BuildsAgendaLifecycleData;
    public function seed(string $password,?array $existing=null,string $tag='integration'):array
    {
        if($existing){
            $users=$existing['users'];$admin=\App\Models\User::findOrFail($users['admin']['id']);$date=$existing['date'];
            $site=\App\Models\Site::findOrFail($existing['site_id']);
            $catalog=['doctor'=>\App\Models\Doctor::findOrFail($existing['doctor_id']),'service'=>\App\Models\Service::findOrFail($existing['service_id']),
                'specialty'=>\App\Models\Specialty::findOrFail($existing['specialty_id']),
                'rate'=>\App\Models\AdditionalRate::findOrFail(\App\Models\Appointment::findOrFail($existing['appointments']['direct'])->additional_rate_id)];
        }else{
        $users=[];
        foreach(['admin'=>'ADMINISTRADOR','admission'=>'ADMISION','commercial'=>'COMERCIAL'] as $key=>$role){
            $u=$this->agendaReader($role);$u->forceFill(['name'=>strtoupper($key).' INTEGRACION FICTICIO','email'=>'agenda-'.$tag.'-'.$key.'@example.invalid','password'=>\Illuminate\Support\Facades\Hash::make($password)])->save();
            foreach($key==='commercial'?[]:\App\Support\Scheduling\MvpPermissionBootstrap::REQUIRED as $cap)$u->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate($cap,'web'));
            $cashier=\App\Models\Cashier::create(['nombre'=>'Caja ficticia ronda 2 '.$key,'estado'=>'ACTIVO']);
            \App\Models\CashierShift::create(['cashier_id'=>$cashier->id,'user_id'=>$u->id,'monto_apertura'=>0,'abierto_en'=>now(),'estado'=>'ABIERTO']);
            \App\Models\VoucherSerie::create(['cashier_id'=>$cashier->id,'tipo_comprobante'=>'TICKET','serie'=>'R'.str_pad($u->id,3,'0',STR_PAD_LEFT),'correlativo_actual'=>0,'estado'=>'ACTIVO']);
            $users[$key]=['id'=>$u->id,'email'=>$u->email];
        }
        $admin=\App\Models\User::findOrFail($users['admin']['id']);$catalog=$this->createAppointmentCatalog();$date=now('America/Lima')->toDateString();
        $site=$this->createSite(['codigo'=>$tag==='integration'?'INTQA':'R3REGQA','nombre'=>'SEDE INTEGRACION FICTICIA']);
        $catalog['doctor']->update(['nombre'=>'MÉDICO '.strtoupper($tag).' FICTICIO']);$catalog['specialty']->update(['nombre'=>'TRAUMATOLOGIA INTEGRACION FICTICIA']);
        $catalog['service']->update(['nombre'=>'CONSULTA INTEGRACION FICTICIA','service_type'=>'CONSULTATION','pricing_policy'=>'PROFESSIONAL']);
        $catalog['doctorService']->update(['dias_reconsulta'=>15]);
        $catalog['rate']->update(['nombre'=>'TARIFA ESTANDAR','tipo_tarifa'=>'MONTO_FIJO']);
        $catalog['schedule']->update(['fecha_cita'=>$date,'dia_semana'=>now('America/Lima')->dayOfWeekIso,'hora_inicio'=>'08:00','hora_fin'=>'18:00','site_id'=>$site->id]);
        }
        $appointments=$existing['appointments']??[];$patients=$existing['patients']??[];
        foreach(['fresh2','manual2','auto2','fresh3','none','dirty','reversal','payment','history','direct','historic','dual_auto','audit'] as $index=>$key){
            if(isset($appointments[$key]))continue;
            $p=$this->createPatient($admin,['nombre'=>'PACIENTE INTEGRACION '.strtoupper($key).' FICTICIO','apellido_paterno'=>'QA','apellido_materno'=>'INTEGRACION',
                'numero_identidad'=>(string)(($tag==='integration'?79300010:79510010)+$index),'historia_clinica'=>'INT-FICTICIO-'.$key,'telefono'=>'+51999000002']);
            $a=$this->lifecycleAppointment($key==='payment'?\App\Models\User::findOrFail($users['admission']['id']):$admin,$catalog,[
                ... (in_array($key,['direct','historic','dual_auto','audit'],true)?['estado_cita'=>'ATENDIDO']:[]),
                'fecha_cita'=>$date,'hora_cita'=>$key==='audit'?'07:30':($key==='dual_auto'?'07:00':sprintf('%02d:%02d',8+intdiv($index,2),($index%2)*30)),'site_id'=>$site->id,
                'motivo_consulta'=>$key==='history'?null:'Paciente ficticio refiere dolor de rodilla derecha desde hace una semana.',
                ...($key==='payment'?['estado_agenda'=>'PENDIENTE_CONFIRMACION','tipo_agendamiento'=>'REGULAR','economic_source'=>'VOUCHER']:[])],$p);
            if($key==='historic')app(\App\Services\Scheduling\AppointmentHistory::class)->record($a,\App\Services\Scheduling\ManualReevaluationService::MARKED,$users['commercial']['id'],['metadata'=>['source'=>'ADMISSION_MANUAL_V1']]);
            if($key==='audit')for($i=0;$i<45;$i++)app(\App\Services\Scheduling\AppointmentHistory::class)->record($a,$i%2?\App\Services\Scheduling\ManualReevaluationService::CLEARED:\App\Services\Scheduling\ManualReevaluationService::MARKED,$users['commercial']['id'],['metadata'=>['source'=>'ADMISSION_MANUAL_V1']]);
            $appointments[$key]=$a->id;$patients[$key]=['id'=>$p->id,'document'=>$p->numero_identidad];
        }
        return ['date'=>$date,'users'=>$users,'appointments'=>$appointments,'patients'=>$patients,'doctor_id'=>$catalog['doctor']->id,
            'service_id'=>$catalog['service']->id,'site_id'=>$site->id,'specialty_id'=>$catalog['specialty']->id];
    }
};
$data=\Illuminate\Support\Facades\DB::transaction(fn()=>$fixture->seed($secret['password'],$existing,$tag));
file_put_contents($out.'/fixtures.json',json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo "Appended only fictional integration fixtures; existing manual data preserved.\n";
