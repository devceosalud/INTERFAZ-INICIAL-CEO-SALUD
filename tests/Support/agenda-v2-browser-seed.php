<?php
require dirname(__DIR__,2).'/vendor/autoload.php';
$app=\Tests\Support\IsolatedAgendaV2Qa::boot(true);
$password=getenv('AGENDA_V2_QA_PASSWORD');
if(PHP_SAPI!=='cli'||getenv('AGENDA_V2_QA_RESET')!=='OWNED_SYNTHETIC_V2'||!$password||strlen($password)<24)throw new RuntimeException('Explicit owned synthetic reset required.');
\Illuminate\Support\Facades\Artisan::call('migrate:fresh',['--force'=>true]);
$fixture=new class {
    use \Tests\Concerns\BuildsAgendaLifecycleData;
    public function seed(string $password):array
    {
        $users=[];
        foreach(['admin'=>'ADMINISTRADOR','admission'=>'ADMISION','commercial'=>'COMERCIAL'] as $key=>$role){
            $u=$this->agendaReader($role);
            $u->forceFill(['name'=>strtoupper($key).' V2 FICTICIO','email'=>'agenda-v2-'.$key.'@example.invalid','password'=>\Illuminate\Support\Facades\Hash::make($password)])->save();
            foreach(\App\Support\Scheduling\MvpPermissionBootstrap::REQUIRED as $cap)$u->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate($cap,'web'));
            $c=\App\Models\Cashier::create(['nombre'=>'Caja QA V2 '.$key,'estado'=>'ACTIVO']);
            \App\Models\CashierShift::create(['cashier_id'=>$c->id,'user_id'=>$u->id,'monto_apertura'=>0,'abierto_en'=>now(),'estado'=>'ABIERTO']);
            \App\Models\VoucherSerie::create(['cashier_id'=>$c->id,'tipo_comprobante'=>'TICKET','serie'=>'V'.str_pad($u->id,3,'0',STR_PAD_LEFT),'correlativo_actual'=>0,'estado'=>'ACTIVO']);
            $users[$key]=['id'=>$u->id,'email'=>$u->email];
        }
        $admin=\App\Models\User::findOrFail($users['admin']['id']);
        $catalog=$this->createAppointmentCatalog();$today=now('America/Lima')->toDateString();
        $catalog['doctor']->update(['nombre'=>'MÉDICO V2 FICTICIO']);$catalog['service']->update(['nombre'=>'CONSULTA V2 FICTICIA']);
        $catalog['schedule']->update(['fecha_cita'=>$today,'dia_semana'=>now('America/Lima')->dayOfWeekIso,'hora_inicio'=>'08:00','hora_fin'=>'18:00']);
        $catalog['rate']->update(['nombre'=>'TARIFA ESTANDAR','tipo_tarifa'=>'MONTO_FIJO']);
        $appointments=[];
        foreach(['manual','auto','reversal'] as $i=>$key){
            $p=$this->createPatient($admin,['nombre'=>'PACIENTE V2 '.strtoupper($key).' FICTICIO',
                'numero_identidad'=>(string)(79009910+$i),'historia_clinica'=>'V2-QA-'.$key]);
            $a=$this->lifecycleAppointment($admin,$catalog,['fecha_cita'=>$today,'hora_cita'=>sprintf('%02d:00',9+$i)],$p);
            $appointments[$key]=$a->id;
        }
        $p=$this->createPatient($admin,['nombre'=>'PACIENTE V2 PAGO FICTICIO','numero_identidad'=>'79009920','historia_clinica'=>'V2-QA-PAGO','telefono'=>'+51999000001']);
        $pending=$this->lifecycleAppointment(\App\Models\User::findOrFail($users['admission']['id']),$catalog,[
            'fecha_cita'=>$today,'hora_cita'=>'12:00','estado_agenda'=>'PENDIENTE_CONFIRMACION','tipo_agendamiento'=>'REGULAR','economic_source'=>'VOUCHER'], $p);
        $catalog['specialty']->update(['nombre'=>'TRAUMATOLOGIA FICTICIA QA']);
        $catalog['service']->update(['nombre'=>'CONSULTA TRAUMATOLOGIA FICTICIA QA']);
        $mfrSpecialty=\App\Models\Specialty::create(['nombre'=>'MEDICINA FISICA Y REHABILITACION FICTICIA QA','estado'=>'ACTIVO']);
        $mfrDoctor=\App\Models\Doctor::create(['nombre'=>'PROFESIONAL MFR FICTICIA QA','specialty_id'=>$mfrSpecialty->id,'estado'=>'ACTIVO']);
        $mfrService=\App\Models\Service::create(['nombre'=>'CONSULTA MEDICA MFR FICTICIA QA','specialty_id'=>$mfrSpecialty->id,
            'service_type'=>'CONSULTATION','pricing_policy'=>'PROFESSIONAL','estado'=>'ACTIVO']);
        return ['users'=>$users,'date'=>$today,'appointments'=>$appointments,'pending_id'=>$pending->id,
            'mfr_doctor_id'=>$mfrDoctor->id,'mfr_service_id'=>$mfrService->id,'doctor_id'=>$catalog['doctor']->id,'service_id'=>$catalog['service']->id,'patient_id'=>$p->id,'document'=>'79009920'];
    }
};
$data=$fixture->seed($password);
file_put_contents(storage_path('app/qa-v2/browser-fixtures.json'),json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo "Synthetic v2 browser fixtures prepared.\n";
