<?php

namespace Tests\Feature\Baseline;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

class PatientAndAppointmentSmokeTest extends TestCase
{
    use BuildsBaselineData;
    use RefreshDatabase;

    public function test_authenticated_user_can_create_a_patient_with_current_contract(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->postJson('/admissionist/patient/store', [
            'nombre_paciente' => 'Ana',
            'apellido_paterno' => 'Baseline',
            'apellido_materno' => 'Paciente',
            'genero_paciente' => 'MUJER',
            'tipo_identificacion' => 'DNI',
            'numero_identidad' => '71111111',
            'fecha_nacimiento' => '1992-05-10',
            'email' => 'ana@example.invalid',
        ])->assertOk()->assertJsonPath('code', 1);

        $this->assertDatabaseHas('patients', [
            'numero_identidad' => '71111111',
            'user_id' => $user->id,
            'historia_clinica' => '1',
        ]);
    }

    public function test_authenticated_user_can_create_an_unpaid_appointment_with_current_contract(): void
    {
        $user = $this->createUser();
        $patient = $this->createPatient($user);
        $catalog = $this->createAppointmentCatalog();

        $this->actingAs($user)->postJson('/admissionist/appointment/store', [
            'patient_id' => $patient->id,
            'doctor_id' => $catalog['doctor']->id,
            'service_id' => $catalog['doctorService']->id,
            'additional_rate_id' => $catalog['rate']->id,
            'fecha_cita' => $catalog['schedule']->fecha_cita,
            'hora_cita' => '09:00',
            'cita_doble' => false,
            'precio_programado' => 100,
            'total_pagado' => 0,
            'saldo_pendiente' => 100,
        ])->assertOk()->assertJsonPath('code', 1);

        $this->assertDatabaseHas('appointments', [
            'patient_id' => $patient->id,
            'doctor_id' => $catalog['doctor']->id,
            'service_id' => $catalog['service']->id,
            'duracion_cita' => 30,
            'estado_pagado' => 'PENDIENTE',
            'estado_cita' => 'PROGRAMADO',
        ]);
        $this->assertDatabaseCount('vouchers', 0);
        $this->assertDatabaseCount('payments', 0);
    }
}
