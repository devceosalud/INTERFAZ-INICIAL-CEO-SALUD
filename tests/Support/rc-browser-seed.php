<?php
require dirname(__DIR__,2).'/vendor/autoload.php';
$app=\Tests\Support\IsolatedRcQa::boot();
$password=getenv('RC_QA_BROWSER_PASSWORD');
if(PHP_SAPI!=='cli'||getenv('RC_QA_RESET')!=='YES_OWNED_33322'||!is_string($password)||strlen($password)<24)
    throw new RuntimeException('Explicit owned-fixture reset and ephemeral password required.');
\Illuminate\Support\Facades\Artisan::call('migrate:fresh',['--force'=>true]);
$fixture=new class {use \Tests\Concerns\BuildsAgendaLifecycleData;
    public function seed(string $password):array{
        $ordinary=\App\Support\Scheduling\MvpPermissionBootstrap::REQUIRED;
        $caps=\App\Support\Scheduling\SchedulingCapability::class;
        $extra=[\App\Support\Scheduling\DoctorScheduleAccess::MANAGE,\App\Services\Scheduling\ReevaluationIndicationService::CAPABILITY];
        foreach(array_unique(array_merge($caps::all(),$extra)) as $name)\Spatie\Permission\Models\Permission::findOrCreate($name,'web');
        $roles=[];foreach(['ADMINISTRADOR','ADMISION','CAJA','COMERCIAL','FACTURACION','RECEPCION'] as $name)$roles[$name]=\Spatie\Permission\Models\Role::findOrCreate($name,'web');
        foreach(['ADMINISTRADOR','ADMISION','RECEPCION','CAJA'] as $name)$roles[$name]->givePermissionTo($ordinary);
        $roles['COMERCIAL']->givePermissionTo([$caps::MVP_ACCESS,$caps::VIEW,$caps::RESCHEDULE]);
        // Explicit test-only grants to exercise privileged operations; never a production assignment.
        $roles['ADMINISTRADOR']->givePermissionTo([$caps::ASSIGN_RESPONSIBLE,$caps::APPROVE_ZERO_COST,$caps::CREATE_ADDITIONAL,
            $caps::CANCEL,$caps::MARK_NO_SHOW,$caps::WITHDRAW,$caps::VIEW_AUDIT,\App\Support\Scheduling\DoctorScheduleAccess::MANAGE]);
        $roles['ADMISION']->givePermissionTo(\App\Services\Scheduling\ReevaluationIndicationService::CAPABILITY);
        $admin=$this->createUserWithRole('ADMINISTRADOR',['name'=>'ADMIN PRINCIPAL QA FICTICIO','email'=>'rc-admin@example.invalid','dni'=>'90000000',
            'password'=>\Illuminate\Support\Facades\Hash::make($password)]);
        $siteSetup=app(\App\Support\Scheduling\MagdalenaSiteSetup::class);
        $siteReceipt=$siteSetup->apply('MAG-QA',$siteSetup->plan('MAG-QA')['plan_hash'],$admin->id,'QA-RC-FICTICIO-NO-PRODUCCION');
        $quiroz=$this->createAppointmentCatalog();$quiroz['doctor']->update(['nombre'=>'JULIO QUIROZ · QA FICTICIO']);
        $quiroz['service']->update(['nombre'=>'CONSULTA TRAUMATOLOGIA · QA']);$quiroz['doctorService']->update(['precio_primera_consulta'=>150,'precio_reconsulta'=>120,'dias_reconsulta'=>15]);
        $quiroz['rate']->update(['nombre'=>'TARIFA ESTANDAR','tipo_tarifa'=>'MONTO_FIJO','tarifa'=>0]);
        $primary=$this->createAppointmentCatalog();$primary['doctor']->update(['nombre'=>'MEDICO OPERATIVO QA FICTICIO']);
        $primary['service']->update(['nombre'=>'CONSULTA QA S100']);$primary['doctorService']->update(['precio_primera_consulta'=>100,'precio_reconsulta'=>80,'dias_reconsulta'=>15]);
        // Remove only freshly generated default schedules; the jornada creates its own through the UI.
        \Illuminate\Support\Facades\DB::table('doctor_schedules')->whereIn('id',[$quiroz['schedule']->id,$primary['schedule']->id])->delete();
        // The factory's second rate is repurposed as the trauma referral fixture.
        $traumaRate=$primary['rate'];$traumaRate->update(['nombre'=>'REFERIDO TRAUMATOLOGIA','tipo_tarifa'=>'MONTO_FIJO','tarifa'=>50]);
        $foot=\App\Models\Service::create(['specialty_id'=>$quiroz['specialty']->id,'nombre'=>'PIE DIABETICO · QA','estado'=>'ACTIVO']);
        \App\Models\DoctorService::create(['doctor_id'=>$quiroz['doctor']->id,'service_id'=>$foot->id,'precio_primera_consulta'=>150,'precio_reconsulta'=>120,'dias_reconsulta'=>15,'estado'=>'ACTIVO']);
        $footRate=\App\Models\AdditionalRate::create(['nombre'=>'REFERIDO PIE DIABETICO','tipo_tarifa'=>'MONTO_FIJO','tarifa'=>100,'estado'=>'ACTIVO']);
        \App\Models\AdditionalRate::create(['nombre'=>'CAMPAÑA ESPECIAL QA','tipo_tarifa'=>'PORCENTAJE','tarifa'=>20,'estado'=>'ACTIVO']);
        $existing=$this->createPatient($admin,['nombre'=>'PACIENTE EXISTENTE QA','apellido_paterno'=>'FICTICIO','apellido_materno'=>'LOCAL','numero_identidad'=>'70000001','historia_clinica'=>'QA-RC-EXISTENTE','telefono'=>'+51999000001']);
        $historyPatient=$this->createPatient($admin,['nombre'=>'PACIENTE HISTORICO QA','numero_identidad'=>'70009901','historia_clinica'=>'QA-RC-HISTORICO']);
        $history=$this->lifecycleAppointment($admin,$primary,['fecha_cita'=>now('America/Lima')->subDays(40)->toDateString(),
            'hora_cita'=>'08:00:00','estado_cita'=>'ATENDIDO','estado_agenda'=>'LEGADO','total_pagado'=>50,'saldo_pendiente'=>50,'estado_pagado'=>'PARCIAL'],$historyPatient);
        file_put_contents(storage_path('app/qa-rc/historical-before.json'),json_encode($history->fresh()->getAttributes(),JSON_PRETTY_PRINT));
        return ['date'=>now('America/Lima')->toDateString(),'admin'=>['id'=>$admin->id,'email'=>$admin->email],
            'roles'=>array_map(fn($r)=>$r->id,$roles),'site_id'=>$siteReceipt['site_id'],'doctor_id'=>$primary['doctor']->id,
            'service_id'=>$primary['service']->id,'doctor_service_id'=>$primary['doctorService']->id,'standard_rate_id'=>$quiroz['rate']->id,
            'existing_patient_id'=>$existing->id,'historical_appointment_id'=>$history->id,
            'quiroz'=>['doctor_id'=>$quiroz['doctor']->id,'service_ids'=>[$quiroz['service']->id,$foot->id],
                'additional_rate_ids'=>[$quiroz['service']->id=>$traumaRate->id,$foot->id=>$footRate->id]]];
    }
};
$data=$fixture->seed($password);
file_put_contents(storage_path('app/qa-rc/browser-fixtures.json'),json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo 'Owned RC browser fixture prepared; only the principal administrator exists. Other workers must be created through the UI.'.PHP_EOL;
