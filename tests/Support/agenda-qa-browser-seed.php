<?php
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = \Tests\Support\IsolatedAgendaQa::boot();
$qaPassword=getenv('AGENDA_QA_BROWSER_PASSWORD');
if (!is_string($qaPassword) || strlen($qaPassword)<20) throw new \RuntimeException('QA requires a private ephemeral browser password before resetting its disposable database.');
\Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--force' => true]);
$fixtures = new class {
    use \Tests\Concerns\BuildsAgendaLifecycleData;
    public function seed(string $qaPassword): array {
        $users = [];
        foreach (['nocreate', 'reserve', 'payment', 'admin'] as $profile) {
            $user = $this->createUserWithRole($profile === 'admin' ? 'ADMINISTRADOR' : 'COMERCIAL',
                ['name' => 'QA FICTICIO '.$profile, 'email' => 'qa-'.$profile.'@example.invalid',
                    'password' => \Illuminate\Support\Facades\Hash::make($qaPassword)]);
            $permissions = [\App\Support\Scheduling\SchedulingCapability::MVP_ACCESS, \App\Support\Scheduling\SchedulingCapability::VIEW];
            if ($profile !== 'nocreate') { $permissions[] = \App\Support\Scheduling\SchedulingCapability::CREATE; }
            if (in_array($profile,['payment','admin'],true)) { $permissions[] = \App\Support\Scheduling\SchedulingCapability::SUBMIT_PAYMENT; }
            foreach ($permissions as $name) { $user->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate($name, 'web')); }
            if ($profile==='admin') {
                $ordinary=\App\Support\Scheduling\MvpPermissionBootstrap::REQUIRED;
                foreach($ordinary as $name) {
                    $permission=\Spatie\Permission\Models\Permission::findOrCreate($name,'web');
                    $user->roles->first()->givePermissionTo($permission);
                }
            }
            $users[$profile] = $user;
        }
        \Spatie\Permission\Models\Permission::findOrCreate(\App\Support\Scheduling\SchedulingCapability::CREATE_ADDITIONAL,'web');
        $catalog = $this->createAppointmentCatalog();
        $catalog['doctor']->update(['nombre' => 'MEDICO QA FICTICIO']);
        $catalog['service']->update(['nombre' => 'CONSULTA QA S/100']);
        $catalog['rate']->update(['nombre' => 'TARIFA ESTANDAR', 'tipo_tarifa' => 'MONTO_FIJO']);
        $patient = $this->createPatient($users['reserve'], ['nombre' => 'PACIENTE QA', 'apellido_paterno' => 'FICTICIO',
            'apellido_materno' => 'LOCAL', 'numero_identidad' => '70000001', 'historia_clinica' => 'QA-LOCAL-1', 'telefono' => '+51999000001']);
        $box = \App\Models\Cashier::create(['nombre' => 'CAJA QA FICTICIA', 'estado' => 'ACTIVO']);
        foreach(['payment','admin'] as $cashProfile) \App\Models\CashierShift::create(['cashier_id' => $box->id, 'user_id' => $users[$cashProfile]->id,
            'monto_apertura' => 0, 'abierto_en' => now(), 'estado' => 'ABIERTO']);
        \App\Models\VoucherSerie::create(['cashier_id' => $box->id, 'tipo_comprobante' => 'TICKET', 'serie' => 'QA01', 'correlativo_actual' => 0, 'estado' => 'ACTIVO']);
        $historicPatient = $this->createPatient($users['admin'], ['numero_identidad' => '70000002', 'historia_clinica' => 'QA-HISTORIC-2']);
        $old = $this->lifecycleAppointment($users['admin'], $catalog, ['fecha_cita' => $catalog['schedule']->fecha_cita, 'hora_cita' => '10:00:00'], $historicPatient);
        file_put_contents(storage_path('app/qa-final/browser-historical-before.json'), json_encode($old->fresh()->getAttributes()));
        return ['users' => array_map(fn ($u) => ['id' => $u->id, 'email' => $u->email], $users),
            'date' => $catalog['schedule']->fecha_cita, 'doctor_id' => $catalog['doctor']->id, 'patient_number' => '70000001', 'legacy_id' => $old->id];
    }
};
$result = $fixtures->seed($qaPassword);
file_put_contents(storage_path('app/qa-final/browser-fixtures.json'), json_encode($result, JSON_PRETTY_PRINT));
echo json_encode($result, JSON_PRETTY_PRINT).PHP_EOL;
