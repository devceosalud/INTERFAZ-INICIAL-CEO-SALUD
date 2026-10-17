<?php
if(PHP_SAPI!=='cli') exit('CLI only.');
require dirname(__DIR__,2).'/vendor/autoload.php';
$app=\Tests\Support\IsolatedManualReevaluationQa::boot();
\Illuminate\Support\Facades\Artisan::call('migrate:fresh',['--force'=>true]);
$fixtures=new class {
    use \Tests\Concerns\BuildsAgendaLifecycleData;
    public function seed():array {
        $users=[];
        foreach(['ADMISION','ADMINISTRADOR','COMERCIAL'] as $role){
            $user=$this->createUserWithRole($role,['name'=>'PERSONAL FICTICIO '.$role,'email'=>strtolower($role).'@example.invalid',
                'password'=>\Illuminate\Support\Facades\Hash::make('only-local-manual-qa-2026')]);
            foreach([\App\Support\Scheduling\SchedulingCapability::MVP_ACCESS,\App\Support\Scheduling\SchedulingCapability::VIEW,\App\Support\Scheduling\SchedulingCapability::UPDATE] as $cap)
                $user->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate($cap,'web'));
            $users[$role]=$user;
        }
        $catalog=$this->createAppointmentCatalog();$catalog['doctor']->update(['nombre'=>'PROFESIONAL FICTICIO QA']);
        $site=$this->createSite(['nombre'=>'Magdalena del Mar']);$ids=[];
        foreach(['ATENDIDO','EN_ESPERA','LLAMANDO','EN_ATENCION','PROGRAMADO','CONFIRMADO','CANCELADO','NO_ASISTIO','REEVALUACION'] as $i=>$state){
            $patient=$this->createPatient($users['ADMISION'],['nombre'=>'PACIENTE FICTICIO '.$state,'numero_identidad'=>(string)(79000001+$i),'historia_clinica'=>'QA-MANUAL-'.$i]);
            $a=$this->lifecycleAppointment($users['ADMISION'],$catalog,['site_id'=>$site->id,'estado_cita'=>$state,'hora_cita'=>sprintf('%02d:00:00',8+$i)],$patient);
            $ids[$state]=$a->id;
        }
        $a=$this->lifecycleAppointment($users['ADMISION'],$catalog,['site_id'=>$site->id,'estado_cita'=>'ATENDIDO','hora_cita'=>'17:00:00']);
        $a->forceFill(['hora_salida'=>now()])->save();$ids['SALIDA']=$a->id;
        app(\App\Services\Scheduling\AppointmentHistory::class)->record($a,\App\Services\Scheduling\ManualReevaluationService::MARKED,$users['ADMISION']->id);
        $snapshot=[];foreach(['appointments','payments','vouchers','appointment_reevaluation_indications','doctor_schedules'] as $table)
            $snapshot[$table]=\Illuminate\Support\Facades\DB::table($table)->orderBy('id')->get()->toArray();
        file_put_contents(storage_path('app/hotfix-qa/browser-before.json'),json_encode($snapshot));
        return ['date'=>now('America/Lima')->toDateString(),'ids'=>$ids,'doctor_id'=>$catalog['doctor']->id];
    }
};
echo json_encode($fixtures->seed(),JSON_PRETTY_PRINT).PHP_EOL;
