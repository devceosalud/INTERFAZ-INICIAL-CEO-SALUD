<?php

namespace Tests\Concerns;

use App\Models\AdditionalRate;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\DoctorService;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Specialty;
use App\Models\User;

trait BuildsBaselineData
{
    protected function createUser(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    protected function createPatient(?User $user = null, array $attributes = []): Patient
    {
        return Patient::create(array_merge([
            'user_id' => $user?->id,
            'historia_clinica' => '1',
            'nombre' => 'Paciente',
            'apellido_paterno' => 'Baseline',
            'apellido_materno' => 'Test',
            'genero' => 'MUJER',
            'tipo_identificacion' => 'DNI',
            'numero_identidad' => '70000001',
            'fecha_registro' => now()->toDateString(),
            'fecha_nacimiento' => '1990-01-01',
            'email' => 'patient@example.invalid',
            'estado' => 'ACTIVO',
        ], $attributes));
    }

    /**
     * @return array{specialty: Specialty, doctor: Doctor, service: Service, doctorService: DoctorService, rate: AdditionalRate, schedule: DoctorSchedule}
     */
    protected function createAppointmentCatalog(): array
    {
        $date = now()->addDay()->toDateString();
        $specialty = Specialty::create(['nombre' => 'Medicina de prueba', 'estado' => 'ACTIVO']);
        $doctor = Doctor::create([
            'specialty_id' => $specialty->id,
            'nombre' => 'Doctor Baseline',
            'estado' => 'ACTIVO',
        ]);
        $service = Service::create([
            'specialty_id' => $specialty->id,
            'nombre' => 'Consulta baseline',
            'estado' => 'ACTIVO',
        ]);
        $doctorService = DoctorService::create([
            'doctor_id' => $doctor->id,
            'service_id' => $service->id,
            'precio_primera_consulta' => 100,
            'precio_reconsulta' => 50,
            'dias_reconsulta' => 7,
            'estado' => 'ACTIVO',
        ]);
        $rate = AdditionalRate::create([
            'nombre' => 'Tarifa regular',
            'tipo_tarifa' => 'FIJA',
            'tarifa' => 0,
            'estado' => 'ACTIVO',
        ]);
        $schedule = DoctorSchedule::create([
            'doctor_id' => $doctor->id,
            'dia_semana' => now()->addDay()->dayOfWeekIso,
            'fecha_cita' => $date,
            'hora_inicio' => '08:00:00',
            'hora_fin' => '12:00:00',
            'duracion_cita' => 30,
            'estado' => 'ACTIVO',
        ]);

        return compact('specialty', 'doctor', 'service', 'doctorService', 'rate', 'schedule');
    }
}

