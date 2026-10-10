<?php
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = \Tests\Support\IsolatedAgendaQa::boot();
\Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--force' => true]);
$fixtures = new class {
    use \Tests\Concerns\BuildsAgendaLifecycleData;
    public function seed(): array {
        $users = [];
        foreach (['nocreate', 'reserve', 'payment', 'admin'] as $profile) {
            $user = $this->createUserWithRole($profile === 'admin' ? 'ADMINISTRADOR' : 'COMERCIAL',
                ['name' => 'QA FICTICIO '.$profile, 'email' => 'qa-'.$profile.'@example.invalid',
                    'password' => \Illuminate\Support\Facades\Hash::make('AgendaQaOnly-2026!')]);
            $permissions = [\App\Support\Scheduling\SchedulingCapability::MVP_ACCESS, \App\Support\Scheduling\SchedulingCapability::VIEW];
            if ($profile !== 'nocreate') { $permissions[] = \App\Support\Scheduling\SchedulingCapability::CREATE; }
            if ($profile === 'payment') { $permissions[] = \App\Support\Scheduling\SchedulingCapability::SUBMIT_PAYMENT; }
            foreach ($permissions as $name) { $user->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate($name, 'web')); }
            $users[$profile] = $user;
        }
        $catalog = $this->createAppointmentCatalog();
        $catalog['doctor']->update(['nombre' => 'MEDICO QA FICTICIO']);
        $catalog['service']->update(['nombre' => 'CONSULTA QA S/100']);
        $catalog['rate']->update(['nombre' => 'TARIFA ESTANDAR', 'tipo_tarifa' => 'MONTO_FIJO']);
        $patient = $this->createPatient($users['reserve'], ['nombre' => 'PACIENTE QA', 'apellido_paterno' => 'FICTICIO',
            'apellido_materno' => 'LOCAL', 'numero_identidad' => '70000001', 'historia_clinica' => 'QA-LOCAL-1', 'telefono' => '+51999000001']);
        $box = \App\Models\Cashier::create(['nombre' => 'CAJA QA FICTICIA', 'estado' => 'ACTIVO']);
        \App\Models\CashierShift::create(['cashier_id' => $box->id, 'user_id' => $users['payment']->id,
            'monto_apertura' => 0, 'abierto_en' => now(), 'estado' => 'ABIERTO']);
        \App\Models\VoucherSerie::create(['cashier_id' => $box->id, 'tipo_comprobante' => 'TICKET', 'serie' => 'QA01', 'correlativo_actual' => 0, 'estado' => 'ACTIVO']);
        $historicPatient = $this->createPatient($users['admin'], ['numero_identidad' => '70000002', 'historia_clinica' => 'QA-HISTORIC-2']);
        $old = $this->lifecycleAppointment($users['admin'], $catalog, ['fecha_cita' => $catalog['schedule']->fecha_cita, 'hora_cita' => '10:00:00'], $historicPatient);
        file_put_contents(storage_path('app/qa-final/browser-historical-before.json'), json_encode($old->fresh()->getAttributes()));
        return ['users' => array_map(fn ($u) => ['id' => $u->id, 'email' => $u->email], $users),
            'date' => $catalog['schedule']->fecha_cita, 'doctor_id' => $catalog['doctor']->id, 'patient_number' => '70000001', 'legacy_id' => $old->id];
    }
};
$result = $fixtures->seed();
file_put_contents(storage_path('app/qa-final/browser-fixtures.json'), json_encode($result, JSON_PRETTY_PRINT));
echo json_encode($result, JSON_PRETTY_PRINT).PHP_EOL;
