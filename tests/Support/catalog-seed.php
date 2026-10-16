<?php

require dirname(__DIR__, 2) . '/vendor/autoload.php';
$app = \Tests\Support\IsolatedCatalogQa::boot(true);
$password = getenv('CATALOG_QA_PASSWORD');
if (PHP_SAPI !== 'cli' || getenv('CATALOG_QA_RESET') !== 'OWNED_CATALOG_SYNTHETIC' || !$password || strlen($password) < 24) {
    throw new RuntimeException('Explicit owned synthetic reset required');
}
config(['scheduling.pilot_payment_without_manual_cash_shift' => true]);
\Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--force' => true]);
$fixture = new class
{
    use \Tests\Concerns\BuildsAgendaLifecycleData;
    public function seed($password): array
    {
        $users = [];
        foreach (['admin' => 'ADMINISTRADOR', 'admin2' => 'ADMINISTRADOR', 'commercial' => 'COMERCIAL', 'admission' => 'ADMISION', 'nicole' => 'ADMISION'] as $key => $role) {
            $u = $this->agendaReader($role);
            $u->forceFill(['name' => strtoupper($key) . ' CATALOGO FICTICIO', 'email' => 'catalog-' . $key . '@example.invalid', 'password' => \Illuminate\Support\Facades\Hash::make($password)])->save();
            foreach (\App\Support\Scheduling\MvpPermissionBootstrap::REQUIRED as $cap) {
                $u->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate($cap, 'web'));
            }
            $users[$key] = ['id' => $u->id, 'email' => $u->email];
        }
        $actor = \App\Models\User::findOrFail($users['commercial']['id']);
        $catalog = $this->createAppointmentCatalog();
        $catalog['service']->update(['nombre' => 'CONSULTA MEDICA TRAUMATOLOGICA QA', 'service_type' => 'CONSULTATION', 'pricing_policy' => 'PROFESSIONAL']);
        $catalog['doctor']->update(['nombre' => 'PROFESIONAL QUIROZ FICTICIO QA']);
        $catalog['doctorService']->update(['precio_primera_consulta' => 150, 'precio_reconsulta' => 120, 'dias_reconsulta' => 15]);
        $foot = \App\Models\Service::create(['nombre' => 'CONSULTA MEDICA PIE DIABETICO QA', 'specialty_id' => $catalog['specialty']->id, 'service_type' => 'CONSULTATION', 'pricing_policy' => 'PROFESSIONAL', 'estado' => 'ACTIVO']);
        $aliases = [];
        foreach ([$catalog['service'], $foot] as $s) {
            $aliases[$s->id] = \App\Models\Service::create(['nombre' => 'REEVALUACION QA ' . $s->id, 'specialty_id' => $s->specialty_id, 'service_type' => 'REEVALUATION', 'pricing_policy' => 'PROFESSIONAL', 'canonical_service_id' => $s->id, 'estado' => 'ACTIVO'])->id;
        }
        $institution = \App\Models\Service::create(['nombre' => 'PROCEDIMIENTO INSTITUCIONAL FICTICIO QA', 'specialty_id' => $foot->specialty_id, 'service_type' => 'PROCEDURE', 'pricing_policy' => 'INSTITUTIONAL', 'institutional_price' => 280.5, 'estado' => 'ACTIVO']);
        $other = \App\Models\Doctor::create(['nombre' => 'PROFESIONAL GENERAL FICTICIO QA', 'specialty_id' => $foot->specialty_id, 'estado' => 'ACTIVO']);
        foreach ([[$catalog['doctor']->id, $foot->id, 150, 120], [$other->id, $foot->id, 150, 120], [$other->id, $catalog['service']->id, 100, 80], [$other->id, $institution->id, null, null], [$catalog['doctor']->id, $institution->id, null, null]] as [$doctor, $service, $normal, $re]) {
            \App\Models\DoctorService::create(['doctor_id' => $doctor, 'service_id' => $service, 'precio_primera_consulta' => $normal, 'precio_reconsulta' => $re, 'dias_reconsulta' => 15, 'estado' => 'ACTIVO']);
        }
        $catalog['schedule']->update(['hora_fin' => '18:00']);
        $date = $catalog['schedule']->fecha_cita;
        $otherSchedule = \App\Models\DoctorSchedule::create(['doctor_id' => $other->id, 'dia_semana' => \Carbon\Carbon::parse($date)->dayOfWeekIso, 'fecha_cita' => $date, 'hora_inicio' => '08:00', 'hora_fin' => '18:00', 'duracion_cita' => 30, 'estado' => 'ACTIVO']);
        $mfrSpecialty = new \App\Models\Specialty();
        $mfrSpecialty->forceFill(['id' => 5, 'nombre' => 'MEDICINA FISICA Y REHABILITACION QA', 'estado' => 'ACTIVO'])->save();
        $mfrDoctor = new \App\Models\Doctor();
        $mfrDoctor->forceFill(['id' => 9, 'specialty_id' => 5, 'nombre' => 'KEYLINN FICTICIA QA', 'estado' => 'ACTIVO'])->save();
        $mfrService = \App\Models\Service::create(['nombre' => 'CONSULTA MFR FICTICIA QA', 'specialty_id' => 5, 'service_type' => 'CONSULTATION', 'pricing_policy' => 'PROFESSIONAL', 'estado' => 'ACTIVO']);
        \App\Models\DoctorService::create(['doctor_id' => 9, 'service_id' => $catalog['service']->id, 'precio_primera_consulta' => 100, 'precio_reconsulta' => 80, 'estado' => 'ACTIVO']);
        \App\Models\DoctorService::create(['doctor_id' => 9, 'service_id' => $mfrService->id, 'precio_primera_consulta' => 130, 'precio_reconsulta' => 90, 'estado' => 'ACTIVO']);
        \App\Models\DoctorSchedule::create(['doctor_id' => 9, 'dia_semana' => \Carbon\Carbon::parse($date)->dayOfWeekIso, 'fecha_cita' => $date, 'hora_inicio' => '08:00', 'hora_fin' => '18:00', 'duracion_cita' => 30, 'estado' => 'ACTIVO']);
        $sharedDocument = \App\Models\Service::create(['nombre' => 'INFORME MEDICO COMPARTIDO FICTICIO QA', 'specialty_id' => $catalog['specialty']->id, 'service_type' => 'DOCUMENT', 'pricing_policy' => 'INSTITUTIONAL', 'institutional_price' => 35, 'estado' => 'ACTIVO']);
        $patient = $this->createPatient($actor, ['nombre' => 'PACIENTE CATALOGO FICTICIO', 'numero_identidad' => '73000001', 'historia_clinica' => 'QA-CATALOG']);
        $history = $this->lifecycleAppointment($actor, $catalog, ['precio_programado' => 137, 'saldo_pendiente' => 137, 'estado_cita' => 'ATENDIDO', 'fecha_cita' => \Carbon\Carbon::parse($date)->subDays(1)->toDateString()], $patient);
        // No neutral TARIFA ESTANDAR: reproduces the production configuration defect safely.
        $catalog['rate']->update(['nombre' => 'RECARGO FICTICIO', 'tipo_tarifa' => 'MONTO_FIJO', 'tarifa' => 25]);
        return ['date' => $date, 'users' => $users, 'doctor_id' => $catalog['doctor']->id, 'other_doctor_id' => $other->id, 'service_id' => $catalog['service']->id, 'foot_service_id' => $foot->id, 'institutional_service_id' => $institution->id, 'assignment_id' => $catalog['doctorService']->id, 'aliases' => $aliases, 'patient_id' => $patient->id, 'document' => '73000001', 'history_id' => $history->id, 'mfr_doctor_id' => 9, 'mfr_service_id' => $mfrService->id, 'shared_document_id' => $sharedDocument->id];
    }
};
file_put_contents(storage_path('app/qa-catalog/fixtures.json'), json_encode($fixture->seed($password), JSON_PRETTY_PRINT));
echo 'Seeded only synthetic catalogue QA database' . PHP_EOL;
