<?php
// The included seed boots IsolatedAgendaQa and resets only its verified disposable database.
require __DIR__.'/agenda-qa-browser-seed.php';
$fixtures = new class {
    use \Tests\Concerns\BuildsAgendaLifecycleData;
    public function add(): array {
        $actor = \App\Models\User::where('email', 'qa-payment@example.invalid')->firstOrFail();
        $service = \App\Models\DoctorService::firstOrFail();
        $service->update(['precio_reconsulta' => 50, 'dias_reconsulta' => 7]);
        $patient = $this->createPatient($actor, ['nombre' => 'RECONSULTA QA', 'apellido_paterno' => 'FICTICIO',
            'apellido_materno' => 'LOCAL', 'numero_identidad' => '70000003', 'historia_clinica' => 'QA-RECONSULTA-3',
            'telefono' => '+51999000003']);
        $history = \App\Models\Appointment::create(['numero_cita' => 'QA-ATTENDED-REVIEW', 'user_id' => $actor->id,
            'patient_id' => $patient->id, 'doctor_id' => $service->doctor_id, 'service_id' => $service->service_id,
            'additional_rate_id' => \App\Models\AdditionalRate::firstOrFail()->id,
            'fecha_cita' => now()->subDay()->toDateString(), 'hora_cita' => '08:00', 'duracion_cita' => 30,
            'precio_programado' => 100, 'total_pagado' => 100, 'saldo_pendiente' => 0, 'estado_pagado' => 'PAGADO',
            'estado_agenda' => 'LEGADO', 'estado_cita' => 'ATENDIDO']);
        \App\Models\AdditionalRate::create(['nombre' => 'TARIFA QA REVISION', 'tipo_tarifa' => 'MONTO_FIJO',
            'tarifa' => 30, 'estado' => 'ACTIVO']);
        file_put_contents(storage_path('app/qa-final/review-attended-before.json'), json_encode($history->fresh()->getAttributes()));
        return ['first_consultation_document' => '70000001', 'reconsultation_document' => '70000003',
            'first_price' => '100.00', 'reconsultation_price' => '50.00'];
    }
};
echo json_encode($fixtures->add(), JSON_PRETTY_PRINT).PHP_EOL;
