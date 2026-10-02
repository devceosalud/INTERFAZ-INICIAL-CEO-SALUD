<?php

namespace Tests\Feature\Patients;

use App\Models\Appointment;
use App\Models\Channel;
use App\Models\Patient;
use App\Models\Responsible;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

class PendingPatientChartTest extends TestCase
{
    use BuildsBaselineData;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_future_visit_with_a_blocking_gap_appears_and_email_stays_recommended(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00', 'America/Lima'));
        $user = $this->createUserWithRole('ADMISION');
        $channel = $this->channel();
        $catalog = $this->createAppointmentCatalog();
        $patient = $this->createPatient($user, array_merge($this->completeChart($channel), [
            'numero_identidad' => '81110001',
            'telefono' => null,
            'email' => null,
            'nombre' => 'INCOMPLETO',
            'apellido_paterno' => 'PENDIENTE',
            'historia_clinica' => 'HCE-PEND-1',
        ]));
        $this->appoint($user, $patient, $catalog, [
            'fecha_cita' => '2026-10-03',
            'hora_cita' => '10:20:00',
        ]);

        $this->actingAs($user)
            ->get('/admissionist/patient?vista=pendientes')
            ->assertOk()
            ->assertSee('81110001')
            ->assertSee('HCE-PEND-1')
            ->assertSee('Teléfono')
            ->assertSee('Datos recomendados pendientes: correo')
            ->assertSee('Próxima 03/10/2026 10:20')
            ->assertSee('Doctor Baseline')
            ->assertSee('data-complete-patient="'.$patient->id.'"', false);
    }

    public function test_a_future_visit_missing_only_email_stays_out_of_the_queue(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00', 'America/Lima'));
        $user = $this->createUserWithRole('ADMISION');
        $channel = $this->channel();
        $catalog = $this->createAppointmentCatalog();
        $patient = $this->createPatient($user, array_merge($this->completeChart($channel), [
            'numero_identidad' => '81110011',
            'email' => null,
            'historia_clinica' => 'HCE-SOLO-CORREO',
        ]));
        $this->appoint($user, $patient, $catalog, [
            'fecha_cita' => '2026-10-03',
            'hora_cita' => '10:20:00',
        ]);

        $this->actingAs($user)
            ->get('/admissionist/patient?vista=pendientes')
            ->assertOk()
            ->assertDontSee('81110011')
            ->assertDontSee('HCE-SOLO-CORREO');
    }

    public function test_past_cancelled_and_missed_visits_do_not_enter_the_queue(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00', 'America/Lima'));
        $user = $this->createUserWithRole('ADMISION');
        $catalog = $this->createAppointmentCatalog();
        $past = $this->incompletePatient($user, '81110021');
        $cancelled = $this->incompletePatient($user, '81110022');
        $missed = $this->incompletePatient($user, '81110023');
        $earlierToday = $this->incompletePatient($user, '81110024');
        $laterToday = $this->incompletePatient($user, '81110025');
        $this->appoint($user, $past, $catalog, ['fecha_cita' => '2026-09-21', 'hora_cita' => '14:00:00']);
        $this->appoint($user, $cancelled, $catalog, [
            'fecha_cita' => '2026-10-03',
            'hora_cita' => '09:00:00',
            'estado_cita' => 'CANCELADO',
        ]);
        $this->appoint($user, $missed, $catalog, [
            'fecha_cita' => '2026-10-03',
            'hora_cita' => '09:30:00',
            'estado_cita' => 'NO_ASISTIO',
        ]);
        $this->appoint($user, $earlierToday, $catalog, [
            'fecha_cita' => '2026-10-02',
            'hora_cita' => '11:00:00',
        ]);
        $this->appoint($user, $laterToday, $catalog, [
            'fecha_cita' => '2026-10-02',
            'hora_cita' => '13:00:00',
        ]);

        $this->actingAs($user)
            ->get('/admissionist/patient?vista=pendientes')
            ->assertOk()
            ->assertDontSee('81110021')
            ->assertDontSee('81110022')
            ->assertDontSee('81110023')
            ->assertDontSee('81110024')
            ->assertSee('81110025')
            ->assertSee('Próxima 02/10/2026 13:00');
    }

    public function test_the_queue_uses_the_lima_wall_clock_including_the_date_change(): void
    {
        $user = $this->createUserWithRole('ADMISION');
        $catalog = $this->createAppointmentCatalog();
        Carbon::setTestNow(Carbon::parse('2026-10-02 19:00:00', 'UTC'));

        $past = $this->incompletePatient($user, '81110031');
        $soon = $this->incompletePatient($user, '81110032');
        $evening = $this->incompletePatient($user, '81110033');
        $cancelled = $this->incompletePatient($user, '81110034');
        $missed = $this->incompletePatient($user, '81110035');
        $this->appoint($user, $past, $catalog, ['fecha_cita' => '2026-10-02', 'hora_cita' => '13:00:00']);
        $this->appoint($user, $soon, $catalog, ['fecha_cita' => '2026-10-02', 'hora_cita' => '14:30:00']);
        $this->appoint($user, $evening, $catalog, ['fecha_cita' => '2026-10-02', 'hora_cita' => '18:00:00']);
        $this->appoint($user, $cancelled, $catalog, [
            'fecha_cita' => '2026-10-02',
            'hora_cita' => '15:00:00',
            'estado_cita' => 'CANCELADO',
        ]);
        $this->appoint($user, $missed, $catalog, [
            'fecha_cita' => '2026-10-02',
            'hora_cita' => '16:00:00',
            'estado_cita' => 'NO_ASISTIO',
        ]);

        $this->actingAs($user)
            ->get('/admissionist/patient?vista=pendientes')
            ->assertOk()
            ->assertDontSee('81110031')
            ->assertSee('81110032')
            ->assertSee('Próxima 02/10/2026 14:30')
            ->assertSee('81110033')
            ->assertSee('Próxima 02/10/2026 18:00')
            ->assertDontSee('81110034')
            ->assertDontSee('81110035');

        Carbon::setTestNow(Carbon::parse('2026-10-03 04:00:00', 'UTC'));
        $earlierTonight = $this->incompletePatient($user, '81110036');
        $stillTonight = $this->incompletePatient($user, '81110037');
        $nextMorning = $this->incompletePatient($user, '81110038');
        $this->appoint($user, $earlierTonight, $catalog, ['fecha_cita' => '2026-10-02', 'hora_cita' => '22:30:00']);
        $this->appoint($user, $stillTonight, $catalog, ['fecha_cita' => '2026-10-02', 'hora_cita' => '23:30:00']);
        $this->appoint($user, $nextMorning, $catalog, ['fecha_cita' => '2026-10-03', 'hora_cita' => '00:30:00']);

        $this->actingAs($user)
            ->get('/admissionist/patient?vista=pendientes')
            ->assertOk()
            ->assertDontSee('81110032')
            ->assertDontSee('81110033')
            ->assertDontSee('81110036')
            ->assertSee('81110037')
            ->assertSee('Próxima 02/10/2026 23:30')
            ->assertSee('81110038')
            ->assertSee('Próxima 03/10/2026 00:30');
    }

    public function test_a_complete_chart_and_a_patient_without_an_appointment_stay_out_of_the_list(): void
    {
        $user = $this->createUserWithRole('ADMISION');
        $channel = $this->channel();
        $catalog = $this->createAppointmentCatalog();
        $complete = $this->createPatient($user, array_merge($this->completeChart($channel), [
            'numero_identidad' => '81110002',
            'historia_clinica' => 'HCE-OK',
        ]));
        $this->appoint($user, $complete, $catalog);
        $this->createPatient($user, [
            'numero_identidad' => '81110003',
            'email' => null,
            'historia_clinica' => 'HCE-SIN-CITA',
        ]);

        $this->actingAs($user)
            ->get('/admissionist/patient?vista=pendientes')
            ->assertOk()
            ->assertDontSee('81110002')
            ->assertDontSee('HCE-OK')
            ->assertDontSee('81110003')
            ->assertDontSee('HCE-SIN-CITA')
            ->assertSee('No hay fichas pendientes de completar.');
    }

    public function test_completing_blocking_fields_keeps_the_same_patient_history_and_appointment(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00', 'America/Lima'));
        $user = $this->createUserWithRole('ADMISION');
        $channel = $this->channel();
        $catalog = $this->createAppointmentCatalog();
        $patient = $this->createPatient($user, [
            'numero_identidad' => '81110004',
            'historia_clinica' => 'HCE-MISMA',
            'email' => null,
            'telefono' => null,
        ]);
        $appointment = $this->appoint($user, $patient, $catalog, [
            'fecha_cita' => '2026-10-20',
            'hora_cita' => '11:00:00',
            'estado_cita' => 'PROGRAMADO',
        ]);

        $this->actingAs($user)
            ->putJson('/patients/'.$patient->id, [
                'tipo_identificacion' => 'DNI',
                'numero_identidad' => '81110004',
                'nombre' => 'Paciente',
                'apellido_paterno' => 'Baseline',
                'apellido_materno' => 'Test',
                'genero' => 'MUJER',
                'fecha_nacimiento' => '1990-01-01',
                'telefono' => '999888777',
                'direccion' => 'Av. Prueba 123',
                'estado_civil' => 'SOLTERO',
                'channel_id' => $channel->id,
                'registrar_responsable' => false,
            ])
            ->assertOk()
            ->assertJsonPath('patient.id', $patient->id)
            ->assertJsonPath('patient.historia_clinica', 'HCE-MISMA');

        $patient->refresh();
        $appointment->refresh();
        $this->assertSame($patient->id, (int) Patient::query()->where('numero_identidad', '81110004')->value('id'));
        $this->assertSame('HCE-MISMA', $patient->historia_clinica);
        $this->assertNull($patient->email);
        $this->assertSame('2026-10-20', substr((string) $appointment->fecha_cita, 0, 10));
        $this->assertSame('11:00:00', substr((string) $appointment->hora_cita, 0, 8));
        $this->assertSame('PROGRAMADO', $appointment->estado_cita);
        $this->assertSame(0, Responsible::query()->where('patient_id', $patient->id)->count());

        $this->actingAs($user)
            ->get('/admissionist/patient?vista=pendientes')
            ->assertOk()
            ->assertDontSee('81110004');
    }

    public function test_a_minor_with_a_future_visit_stays_pending_until_one_responsible_exists(): void
    {
        $user = $this->createUserWithRole('ADMISION');
        $channel = $this->channel();
        $catalog = $this->createAppointmentCatalog();
        $patient = $this->createPatient($user, array_merge($this->completeChart($channel), [
            'numero_identidad' => '81110005',
            'fecha_nacimiento' => '2015-03-02',
            'historia_clinica' => 'HCE-MENOR',
        ]));
        $appointmentId = $this->appoint($user, $patient, $catalog)->id;

        $this->actingAs($user)
            ->get('/admissionist/patient?vista=pendientes')
            ->assertOk()
            ->assertSee('81110005')
            ->assertSee('Responsable');

        $this->actingAs($user)->putJson('/patients/'.$patient->id, $this->minorPayload($channel, [
            'responsable_nombres' => 'ANA DEMO',
        ]))->assertOk();
        $this->assertSame(1, Responsible::query()->where('patient_id', $patient->id)->count());

        $this->actingAs($user)->putJson('/patients/'.$patient->id, $this->minorPayload($channel, [
            'responsable_nombres' => 'ANA DEMO ACTUALIZADA',
        ]))->assertOk();
        $this->assertSame(1, Responsible::query()->where('patient_id', $patient->id)->count());
        $this->assertSame('ANA DEMO ACTUALIZADA', Responsible::query()->where('patient_id', $patient->id)->value('nombres'));
        $this->assertSame($appointmentId, Appointment::query()->where('patient_id', $patient->id)->value('id'));
        $this->assertSame('HCE-MENOR', $patient->fresh()->historia_clinica);

        $this->actingAs($user)
            ->get('/admissionist/patient?vista=pendientes')
            ->assertOk()
            ->assertDontSee('81110005');
    }

    public function test_a_document_search_marks_an_old_incomplete_chart_without_a_future_visit(): void
    {
        $user = $this->createUserWithRole('ADMISION');
        $channel = $this->channel();
        $catalog = $this->createAppointmentCatalog();
        $old = $this->createPatient($user, [
            'numero_identidad' => '81110006',
            'telefono' => null,
            'email' => 'antiguo@example.invalid',
            'historia_clinica' => 'HCE-ANTIGUA',
        ]);
        $this->appoint($user, $old, $catalog, [
            'fecha_cita' => '2026-09-21',
            'hora_cita' => '14:00:00',
        ]);
        $emailOnly = $this->createPatient($user, array_merge($this->completeChart($channel), [
            'numero_identidad' => '81110007',
            'email' => null,
        ]));

        $this->actingAs($user)
            ->get('/admissionist/patient?numero_documento=81110006')
            ->assertOk()
            ->assertSee('id="patient-pending-banner"', false)
            ->assertSee('Ficha pendiente de completar')
            ->assertSee('data-complete-patient="'.$old->id.'"', false)
            ->assertSee('Completar ficha');

        $this->actingAs($user)
            ->get('/admissionist/patient?vista=pendientes&numero_documento=81110006')
            ->assertOk()
            ->assertSee('No hay fichas pendientes de completar.')
            ->assertDontSee('HCE-ANTIGUA');

        $this->actingAs($user)
            ->get('/admissionist/patient?numero_documento=81110007')
            ->assertOk()
            ->assertDontSee('id="patient-pending-banner"', false)
            ->assertDontSee('Ficha pendiente de completar');
    }

    public function test_the_pending_page_does_not_query_each_appointment_separately_and_paginates(): void
    {
        $user = $this->createUserWithRole('ADMISION');
        $catalog = $this->createAppointmentCatalog();
        $this->seedPending($user, $catalog, 2);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get('/admissionist/patient?vista=pendientes')->assertOk();
        $few = count(DB::getQueryLog());

        $this->seedPending($user, $catalog, 6, 3);
        DB::flushQueryLog();
        $this->actingAs($user)->get('/admissionist/patient?vista=pendientes')->assertOk();
        $many = count(DB::getQueryLog());

        $this->assertLessThanOrEqual($few + 2, $many);

        $this->seedPending($user, $catalog, 18, 9);
        $this->actingAs($user)
            ->get('/admissionist/patient?vista=pendientes&page=2')
            ->assertOk()
            ->assertSee('81900026')
            ->assertDontSee('81900001');
    }

    private function channel(): Channel
    {
        return Channel::create(['nombre' => 'Referido de prueba', 'estado' => 'ACTIVO']);
    }

    /**
     * @return array<string, mixed>
     */
    private function completeChart(Channel $channel): array
    {
        return [
            'fecha_nacimiento' => '1990-01-01',
            'telefono' => '999888777',
            'email' => 'completo@example.invalid',
            'direccion' => 'Av. Prueba 123',
            'estado_civil' => 'SOLTERO',
            'channel_id' => $channel->id,
        ];
    }

    private function incompletePatient(User $user, string $document): Patient
    {
        return $this->createPatient($user, [
            'numero_identidad' => $document,
            'telefono' => null,
            'direccion' => null,
            'estado_civil' => null,
            'channel_id' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $catalog
     * @param array<string, mixed> $attributes
     */
    private function appoint(User $user, Patient $patient, array $catalog, array $attributes = []): Appointment
    {
        return Appointment::create(array_merge([
            'numero_cita' => 'PEND-'.$patient->id,
            'user_id' => $user->id,
            'patient_id' => $patient->id,
            'doctor_id' => $catalog['doctor']->id,
            'service_id' => $catalog['service']->id,
            'additional_rate_id' => $catalog['rate']->id,
            'fecha_cita' => now()->addDay()->toDateString(),
            'hora_cita' => '09:20:00',
            'duracion_cita' => 20,
            'estado_cita' => 'PROGRAMADO',
            'estado_pagado' => 'PENDIENTE',
        ], $attributes));
    }

    /**
     * @param array<string, mixed> $catalog
     */
    private function seedPending(User $user, array $catalog, int $count, int $start = 1): void
    {
        for ($index = $start; $index < $start + $count; $index++) {
            $patient = $this->createPatient($user, [
                'numero_identidad' => sprintf('8190%04d', $index),
                'nombre' => sprintf('N%02d', $index),
                'apellido_paterno' => 'Cola',
                'apellido_materno' => 'Pendiente',
                'email' => null,
                'telefono' => null,
                'direccion' => null,
                'estado_civil' => null,
                'channel_id' => null,
            ]);
            $this->appoint($user, $patient, $catalog);
        }
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function minorPayload(Channel $channel, array $overrides = []): array
    {
        return array_merge([
            'tipo_identificacion' => 'DNI',
            'numero_identidad' => '81110005',
            'nombre' => 'Paciente',
            'apellido_paterno' => 'Baseline',
            'apellido_materno' => 'Test',
            'genero' => 'MUJER',
            'fecha_nacimiento' => '2015-03-02',
            'telefono' => '999888777',
            'email' => 'completo@example.invalid',
            'direccion' => 'Av. Prueba 123',
            'estado_civil' => 'SOLTERO',
            'channel_id' => $channel->id,
            'registrar_responsable' => true,
            'responsable_parentesco' => 'PAPA',
            'responsable_nombres' => 'ANA DEMO',
            'responsable_telefono' => '999111222',
            'responsable_tipo_identificacion' => 'DNI',
            'responsable_numero_identidad' => '12345678',
        ], $overrides);
    }
}
