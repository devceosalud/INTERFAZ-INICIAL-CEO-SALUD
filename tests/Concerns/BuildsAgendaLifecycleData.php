<?php

namespace Tests\Concerns;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Support\Scheduling\AppointmentAgendaLifecycle as Lifecycle;
use App\Support\Scheduling\SchedulingCapability;
use Spatie\Permission\Models\Permission;

trait BuildsAgendaLifecycleData
{
    use BuildsBaselineData;

    protected function agendaReader(string $role = 'COMERCIAL'): User
    {
        $user = $this->createUserWithRole($role);
        foreach ([SchedulingCapability::MVP_ACCESS, SchedulingCapability::VIEW] as $capability) {
            Permission::firstOrCreate(['name' => $capability, 'guard_name' => 'web']);
            $user->givePermissionTo($capability);
        }

        return $user;
    }

    protected function lifecycleAppointment(User $creator, array $catalog, array $attributes = [], ?Patient $patient = null): Appointment
    {
        $patient ??= $this->createPatient($creator, [
            'numero_identidad' => (string) (71000000 + Patient::count()),
            'historia_clinica' => 'PRIVACY-'.uniqid(),
        ]);

        return Appointment::create(array_merge([
            'numero_cita' => 'PRIVACY-'.uniqid(),
            'user_id' => $creator->id,
            'patient_id' => $patient->id,
            'doctor_id' => $catalog['doctor']->id,
            'service_id' => $catalog['service']->id,
            'additional_rate_id' => $catalog['rate']->id,
            'fecha_cita' => now()->toDateString(),
            'hora_cita' => '09:00:00',
            'duracion_cita' => 30,
            'precio_programado' => 100,
            'total_pagado' => 0,
            'saldo_pendiente' => 100,
            'estado_pagado' => 'PENDIENTE',
            'estado_cita' => 'PROGRAMADO',
            'estado_agenda' => Lifecycle::LEGACY,
            'tipo_agendamiento' => null,
            'fecha_registro' => now()->toDateString(),
        ], $attributes));
    }
}
