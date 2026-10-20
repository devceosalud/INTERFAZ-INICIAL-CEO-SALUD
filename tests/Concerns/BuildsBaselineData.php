<?php

namespace Tests\Concerns;

use App\Models\AdditionalRate;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\DoctorService;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Site;
use App\Models\Specialty;
use App\Models\User;
use Spatie\Permission\Models\Role;

trait BuildsBaselineData
{
    /** Test-only transition: assert the retired endpoint is forbidden, then keep its economic coverage through Agenda. */
    protected function agendaCreationResponse(string $entry, array $payload, bool $json = true, ?string $operationalUrl = null)
    {
        $method=$json?'postJson':'post';
        if($entry==='legacy'){
            $this->{$method}('/admissionist/appointment/store',$payload)->assertForbidden();
            $assignment=isset($payload['service_id'])?DoctorService::find($payload['service_id']):null;
            if($assignment)$payload['service_id']=$assignment->service_id;
            if(!isset($payload['payment']))$payload['payment']=['amount'=>(string)($payload['total_pagado']??0),
                'method'=>$payload['metodo_pago']??'EFECTIVO','operation'=>$payload['numero_operacion']??null,'origin'=>$payload['entidad_origen']??null];
        }
        if(!isset($payload['duracion_cita']))$payload['duracion_cita']=!empty($payload['cita_doble'])?60:30;
        $payload+=['mode'=>'RESERVE','booking_type'=>'REGULAR',
            'duracion_cita'=>30,'motivo_consulta'=>'Motivo ficticio para prueba de registro'];
        $url=$entry!=='legacy' && $operationalUrl ? $operationalUrl : '/scheduling-mvp/agenda/registrations';
        if($entry==='legacy'&&!isset($payload['request_key'])&&$payload['mode']==='RESERVE'&&(float)($payload['payment']['amount']??0)===0.0)$url='/scheduling-mvp/agenda/appointments';
        return $json?$this->postJson($url,$payload):$this->post($url,$payload,['Accept'=>'application/json']);
    }

    protected function createUser(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    protected function createUserWithRole(string $roleName, array $attributes = []): User
    {
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $user = $this->createUser($attributes);
        $user->assignRole($role);

        return $user;
    }
    /** Explicit test fixture only. Ordinary createUserWithRole never grants management. */
    protected function scheduleManager(string $roleName,array $attributes=[]): User
    {
        $user=$this->createUserWithRole($roleName,$attributes);
        $user->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate(\App\Support\Scheduling\DoctorScheduleAccess::MANAGE,'web'));
        return $user;
    }
    protected function scheduleVersion(int $id):?string
    {
        $row=DoctorSchedule::find($id);
        return $row?\App\Services\Scheduling\DoctorScheduleMutation::version($row):null;
    }

    protected function createSite(array $attributes = []): Site
    {
        return Site::create(array_merge([
            'codigo' => 'BASELINE',
            'nombre' => 'Sede Baseline',
            'estado' => 'ACTIVO',
        ], $attributes));
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
