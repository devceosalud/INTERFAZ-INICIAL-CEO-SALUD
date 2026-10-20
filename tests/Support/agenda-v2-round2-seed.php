<?php
require dirname(__DIR__,2).'/vendor/autoload.php';
\Tests\Support\IsolatedAgendaV2Qa::boot(true);
if(PHP_SAPI!=='cli'||getenv('AGENDA_V2_ROUND2_FIXTURES')!=='APPEND_SYNTHETIC_ONLY')throw new RuntimeException('Explicit isolated fixture append required.');
$out=storage_path('app/qa-v2/round2');if(!is_dir($out))mkdir($out,0777,true);
if(file_exists($out.'/fixtures.json')){echo "Round 2 fixtures already exist; preserved.\n";exit;}
$secret=json_decode(file_get_contents(storage_path('app/qa-v2/browser-private.json')),true,512,JSON_THROW_ON_ERROR);
$fixture=new class {
    use \Tests\Concerns\BuildsAgendaLifecycleData;
    public function seed(string $password):array
    {
        $users=[];
        foreach(['admin'=>'ADMINISTRADOR','admission'=>'ADMISION','commercial'=>'COMERCIAL'] as $key=>$role){
            $u=$this->agendaReader($role);$u->forceFill(['name'=>strtoupper($key).' RONDA 2 FICTICIO','email'=>'agenda-r2-'.$key.'@example.invalid','password'=>\Illuminate\Support\Facades\Hash::make($password)])->save();
            foreach(\App\Support\Scheduling\MvpPermissionBootstrap::REQUIRED as $cap)$u->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate($cap,'web'));
            $cashier=\App\Models\Cashier::create(['nombre'=>'Caja ficticia ronda 2 '.$key,'estado'=>'ACTIVO']);
            \App\Models\CashierShift::create(['cashier_id'=>$cashier->id,'user_id'=>$u->id,'monto_apertura'=>0,'abierto_en'=>now(),'estado'=>'ABIERTO']);
            \App\Models\VoucherSerie::create(['cashier_id'=>$cashier->id,'tipo_comprobante'=>'TICKET','serie'=>'R'.str_pad($u->id,3,'0',STR_PAD_LEFT),'correlativo_actual'=>0,'estado'=>'ACTIVO']);
            $users[$key]=['id'=>$u->id,'email'=>$u->email];
        }
        $admin=\App\Models\User::findOrFail($users['admin']['id']);$catalog=$this->createAppointmentCatalog();$date=now('America/Lima')->toDateString();
        $site=$this->createSite(['codigo'=>'R2QA','nombre'=>'SEDE RONDA 2 FICTICIA']);
        $catalog['doctor']->update(['nombre'=>'MÉDICO RONDA 2 FICTICIO']);$catalog['specialty']->update(['nombre'=>'TRAUMATOLOGIA RONDA 2 FICTICIA']);
        $catalog['service']->update(['nombre'=>'CONSULTA RONDA 2 FICTICIA','service_type'=>'CONSULTATION','pricing_policy'=>'PROFESSIONAL']);
        $catalog['doctorService']->update(['dias_reconsulta'=>15]);
        $catalog['rate']->update(['nombre'=>'TARIFA ESTANDAR','tipo_tarifa'=>'MONTO_FIJO']);
        $catalog['schedule']->update(['fecha_cita'=>$date,'dia_semana'=>now('America/Lima')->dayOfWeekIso,'hora_inicio'=>'08:00','hora_fin'=>'18:00','site_id'=>$site->id]);
        $appointments=[];$patients=[];
        foreach(['fresh2','manual2','auto2','fresh3','none','dirty','reversal','payment','history'] as $index=>$key){
            $p=$this->createPatient($admin,['nombre'=>'PACIENTE R2 '.strtoupper($key).' FICTICIO','apellido_paterno'=>'QA','apellido_materno'=>'RONDA DOS',
                'numero_identidad'=>(string)(79220010+$index),'historia_clinica'=>'R2-FICTICIO-'.$key,'telefono'=>'+51999000002']);
            $a=$this->lifecycleAppointment($key==='payment'?\App\Models\User::findOrFail($users['admission']['id']):$admin,$catalog,[
                'fecha_cita'=>$date,'hora_cita'=>sprintf('%02d:%02d',8+intdiv($index,2),($index%2)*30),'site_id'=>$site->id,
                'motivo_consulta'=>$key==='history'?null:'Paciente ficticio refiere dolor de rodilla derecha desde hace una semana.',
                ...($key==='payment'?['estado_agenda'=>'PENDIENTE_CONFIRMACION','tipo_agendamiento'=>'REGULAR','economic_source'=>'VOUCHER']:[])],$p);
            $appointments[$key]=$a->id;$patients[$key]=['id'=>$p->id,'document'=>$p->numero_identidad];
        }
        return ['date'=>$date,'users'=>$users,'appointments'=>$appointments,'patients'=>$patients,'doctor_id'=>$catalog['doctor']->id,
            'service_id'=>$catalog['service']->id,'site_id'=>$site->id,'specialty_id'=>$catalog['specialty']->id];
    }
};
$data=\Illuminate\Support\Facades\DB::transaction(fn()=>$fixture->seed($secret['password']));
file_put_contents($out.'/fixtures.json',json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo "Appended only fictional round 2 fixtures; existing manual data preserved.\n";
