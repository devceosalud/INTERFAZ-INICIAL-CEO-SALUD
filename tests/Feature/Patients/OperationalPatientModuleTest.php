<?php

namespace Tests\Feature\Patients;

use App\Models\Patient;
use App\Models\Channel;
use App\Models\InteractionMedium;
use App\Services\ReniecService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

class OperationalPatientModuleTest extends TestCase
{
    use BuildsBaselineData;
    use RefreshDatabase;

    /**
     * @dataProvider authorisedReaders
     */
    public function test_the_operational_module_requires_an_authorised_reader(string $role, string $uri): void
    {
        $this->actingAs($this->createUserWithRole($role))
            ->get($uri)
            ->assertOk()
            ->assertSee('id="patients-workspace"', false)
            ->assertSee('id="patients-list-surface"', false)
            ->assertSee('id="patients-record-surface"', false);
    }

    public function authorisedReaders(): array
    {
        return [
            'admission' => ['ADMISION', '/admissionist/patient'],
            'reception' => ['RECEPCION', '/receptionist/patient'],
            'administrator' => ['ADMINISTRADOR', '/admin/patient'],
            'commercial' => ['COMERCIAL', '/admissionist/patient'],
        ];
    }

    public function test_guests_and_users_without_an_operational_role_cannot_read_patient_data(): void
    {
        $this->get('/admissionist/patient')->assertRedirect('/');

        $user = $this->createUser();
        $patient = $this->createPatient();
        $this->actingAs($user)->get('/admissionist/patient')->assertForbidden();
        $this->actingAs($user)->getJson('/patients/'.$patient->id)->assertForbidden();
        $this->actingAs($user)->getJson('/patients/999999')->assertForbidden();
    }

    public function test_the_list_uses_real_supported_patient_filters_without_treating_date_as_attention(): void
    {
        $creator = $this->createUserWithRole('ADMISION');
        $this->createPatient($creator, [
            'historia_clinica' => '9000',
            'tipo_identificacion' => 'DNI',
            'numero_identidad' => '73378485',
            'nombre' => 'MARIA',
            'apellido_paterno' => 'PEREZ',
            'apellido_materno' => 'DEMO',
        ]);
        $this->createPatient($creator, [
            'historia_clinica' => 'ABC-2',
            'tipo_identificacion' => 'PASAPORTE',
            'numero_identidad' => 'PASS-002',
            'nombre' => 'OTRA',
            'apellido_paterno' => 'PERSONA',
            'apellido_materno' => 'PRUEBA',
        ]);

        $this->actingAs($creator)
            ->get('/admissionist/patient?tipo_documento=DNI&numero_documento=3784&hce=9000&nombre=PEREZ+MARIA&fecha=2026-10-01')
            ->assertOk()
            ->assertSee('9000')
            ->assertSee('73378485')
            ->assertSee('PEREZ DEMO MARIA')
            ->assertDontSee('PASS-002')
            ->assertSee('Fecha no filtra atenciones');
    }

    public function test_the_operational_columns_do_not_substitute_appointment_data_for_an_encounter(): void
    {
        $user = $this->createUserWithRole('ADMISION');
        $this->createPatient($user, ['historia_clinica' => '9000']);

        $response = $this->actingAs($user)
            ->get('/admissionist/patient')
            ->assertOk()
            ->assertSee('N.° Registro')
            ->assertSee('Fecha de atención')
            ->assertSee('Pendiente de modelo de atención')
            ->assertSee('9000')
            ->assertDontSee('01-9000');
    }

    public function test_the_existing_patient_detail_returns_the_master_record_and_no_fake_encounter(): void
    {
        $user = $this->createUserWithRole('ADMISION');
        $patient = $this->createPatient($user, [
            'historia_clinica' => '9000',
            'telefono' => '900000000',
            'estado_civil' => 'SOLTERO',
            'direccion' => 'Dirección de prueba',
        ]);

        $this->actingAs($user)
            ->getJson('/patients/'.$patient->id)
            ->assertOk()
            ->assertJsonPath('patient.id', $patient->id)
            ->assertJsonPath('patient.historia_clinica', '9000')
            ->assertJsonPath('patient.tipo_identificacion', 'DNI')
            ->assertJsonPath('patient.numero_identidad', '70000001')
            ->assertJsonPath('patient.estado_civil', 'SOLTERO')
            ->assertJsonPath('encounter', null)
            ->assertJsonMissingPath('appointment');
    }

    public function test_add_back_context_menu_and_preview_hooks_are_present_without_double_click_copy(): void
    {
        $user = $this->createUserWithRole('ADMISION');
        $this->createPatient($user);

        $response = $this->actingAs($user)
            ->get('/admissionist/patient')
            ->assertOk()
            ->assertSee('id="patient-add"', false)
            ->assertSee('id="patient-back"', false)
            ->assertSee('data-patient-id=', false)
            ->assertSee('js/patients/patient-workspace.js', false)
            ->assertSee('js/patients/operational.js', false)
            ->assertSee('id="patient-context-hce"', false)
            ->assertSee('id="patient-reniec"', false);

        $response->assertSee('id="patients-context-menu"', false)
            ->assertSee('id="patient-context-open"', false)
            ->assertSee('Eliminar / Desactivar')
            ->assertDontSee('Doble clic');
    }

    public function test_delete_is_visible_but_disabled_and_admission_has_an_explicit_save_action(): void
    {
        $user = $this->createUserWithRole('ADMISION');

        $response = $this->actingAs($user)->get('/admissionist/patient');

        $response->assertOk()
            ->assertSee('id="patient-delete" disabled', false)
            ->assertSee('id="patient-record-form" novalidate', false)
            ->assertSee('id="patient-save"', false)
            ->assertSee('data-can-write="1"', false);
    }

    public function test_the_reused_reniec_controller_is_dni_only_and_never_writes_a_patient(): void
    {
        $user = $this->createUserWithRole('ADMISION');
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'data' => [
                    'nombres' => 'MARIA',
                    'apellido_paterno' => 'PEREZ',
                    'apellido_materno' => 'DEMO',
                    'fecha_nacimiento' => '01/01/1990',
                    'sexo' => 'MUJER',
                    'estado_civil' => 'SOLTERO',
                    'direccion' => 'Dirección de prueba',
                    'numero' => '73378485',
                ],
            ]),
        ]);

        $this->actingAs($user)
            ->postJson('/patients/reniec-lookup', [
                'tipo_identificacion' => 'DNI',
                'numero_identidad' => '73378485',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'prefilled');

        $this->assertSame(0, Patient::count());

        $this->actingAs($user)
            ->postJson('/patients/reniec-lookup', [
                'tipo_identificacion' => 'PASAPORTE',
                'numero_identidad' => 'PASS-1',
            ])
            ->assertUnprocessable();

        $this->assertSame(0, Patient::count());
        Http::assertSentCount(1);
    }

    public function test_the_index_without_a_query_lists_the_patient_masters(): void
    {
        $user = $this->createUserWithRole('RECEPCION');
        $this->twoPatients($user);

        $this->actingAs($user)
            ->get('/receptionist/patient')
            ->assertOk()
            ->assertSee('73378485')
            ->assertSee('PASS-002')
            ->assertSee('2 registros maestros');
    }

    public function test_searching_with_every_text_filter_empty_keeps_the_base_list(): void
    {
        $user = $this->createUserWithRole('RECEPCION');
        $this->twoPatients($user);

        $this->actingAs($user)
            ->get('/receptionist/patient?tipo_documento=&numero_documento=&hce=&nombre=')
            ->assertOk()
            ->assertSee('73378485')
            ->assertSee('PASS-002')
            ->assertSee('2 registros maestros');
    }

    public function test_null_and_empty_filters_do_not_add_a_restrictive_where(): void
    {
        $user = $this->createUserWithRole('RECEPCION');
        $this->twoPatients($user);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($user)
            ->get('/receptionist/patient?tipo_documento=&numero_documento=&hce=&nombre=')
            ->assertOk();

        $sql = strtolower(collect(DB::getQueryLog())->pluck('query')->implode("\n"));
        $this->assertStringNotContainsString('is null', $sql);
        $this->assertStringNotContainsString('like', $sql);
    }

    public function test_whitespace_only_filters_are_ignored(): void
    {
        $user = $this->createUserWithRole('RECEPCION');
        $this->twoPatients($user);

        $this->actingAs($user)
            ->get('/receptionist/patient?'.http_build_query([
                'tipo_documento' => '   ',
                'numero_documento' => '   ',
                'hce' => '   ',
                'nombre' => '   ',
            ]))
            ->assertOk()
            ->assertSee('73378485')
            ->assertSee('PASS-002')
            ->assertSee('2 registros maestros');
    }

    public function test_a_document_type_still_filters(): void
    {
        $user = $this->createUserWithRole('RECEPCION');
        $this->twoPatients($user);

        $this->actingAs($user)
            ->get('/receptionist/patient?tipo_documento=DNI')
            ->assertOk()
            ->assertSee('73378485')
            ->assertDontSee('PASS-002');
    }

    public function test_a_document_number_still_filters(): void
    {
        $user = $this->createUserWithRole('RECEPCION');
        $this->twoPatients($user);

        $this->actingAs($user)
            ->get('/receptionist/patient?tipo_documento=DNI&numero_documento=73378485')
            ->assertOk()
            ->assertSee('PEREZ DEMO MARIA')
            ->assertDontSee('PASS-002');
    }

    public function test_hce_search_keeps_its_partial_match(): void
    {
        $user = $this->createUserWithRole('RECEPCION');
        $this->twoPatients($user);

        $this->actingAs($user)
            ->get('/receptionist/patient?hce=900')
            ->assertOk()
            ->assertSee('9000')
            ->assertSee('73378485')
            ->assertDontSee('PASS-002');
    }

    public function test_a_name_search_still_filters(): void
    {
        $user = $this->createUserWithRole('RECEPCION');
        $this->twoPatients($user);

        $this->actingAs($user)
            ->get('/receptionist/patient?nombre=PEREZ+MARIA')
            ->assertOk()
            ->assertSee('73378485')
            ->assertDontSee('PASS-002');
    }

    /**
     * @param \App\Models\User $user
     */
    private function twoPatients($user): void
    {
        $this->createPatient($user, [
            'historia_clinica' => '9000',
            'tipo_identificacion' => 'DNI',
            'numero_identidad' => '73378485',
            'nombre' => 'MARIA',
            'apellido_paterno' => 'PEREZ',
            'apellido_materno' => 'DEMO',
        ]);
        $this->createPatient($user, [
            'historia_clinica' => 'ABC-2',
            'tipo_identificacion' => 'PASAPORTE',
            'numero_identidad' => 'PASS-002',
            'nombre' => 'OTRA',
            'apellido_paterno' => 'PERSONA',
            'apellido_materno' => 'PRUEBA',
        ]);
    }

    public function test_admission_reception_and_commercial_can_create_and_update_patients(): void
    {
        $admission = $this->createUserWithRole('ADMISION');
        $channel = Channel::create(['nombre' => 'WhatsApp', 'estado' => 'ACTIVO']);
        $medium = InteractionMedium::create(['nombre' => 'Facebook', 'estado' => 'ACTIVO']);

        $response = $this->actingAs($admission)->postJson('/patients', [
            'tipo_identificacion' => 'DNI',
            'numero_identidad' => '73378485',
            'nombre' => 'MARIA',
            'apellido_paterno' => 'PEREZ',
            'apellido_materno' => 'DEMO',
            'telefono' => '999111222',
            'genero' => 'MUJER',
            'fecha_nacimiento' => '1990-01-01',
            'channel_id' => $channel->id,
            'interaction_medium_id' => $medium->id,
            'email' => 'maria@example.invalid',
            'atribucion_comercial' => 999,
        ]);

        $response->assertCreated()
            ->assertJsonPath('patient.numero_identidad', '73378485')
            ->assertJsonPath('patient.patient_id', fn ($id) => is_int($id));

        $patient = Patient::query()->where('numero_identidad', '73378485')->firstOrFail();
        $this->assertEquals($admission->id, $patient->user_id);
        $this->assertSame('01-73378485', $patient->historia_clinica);
        $this->assertEquals($channel->id, $patient->channel_id);
        $this->assertEquals($medium->id, $patient->interaction_medium_id);

        $this->actingAs($admission)->putJson('/patients/'.$patient->id, [
            'tipo_identificacion' => 'DNI',
            'numero_identidad' => '73378485',
            'nombre' => 'MARIA ELENA',
            'apellido_paterno' => 'PEREZ',
            'apellido_materno' => 'DEMO',
            'telefono' => '999111223',
            'genero' => 'MUJER',
        ])->assertOk()->assertJsonPath('patient.nombre', 'MARIA ELENA');

        $patient->refresh();
        $this->assertEquals($admission->id, $patient->user_id);
        $this->assertSame('01-73378485', $patient->historia_clinica);

        foreach (['RECEPCION' => '70000011', 'COMERCIAL' => '70000012'] as $role => $document) {
            $writer = $this->createUserWithRole($role);
            $created = $this->actingAs($writer)->postJson('/patients', [
                'tipo_identificacion' => 'DNI',
                'numero_identidad' => $document,
                'nombre' => 'PACIENTE',
                'apellido_paterno' => $role,
                'apellido_materno' => 'PRUEBA',
                'genero' => 'HOMBRE',
            ])->assertCreated()->assertJsonPath('patient.numero_identidad', $document);

            $this->actingAs($writer)->putJson('/patients/'.$created->json('patient.id'), [
                'tipo_identificacion' => 'DNI',
                'numero_identidad' => $document,
                'nombre' => 'PACIENTE',
                'apellido_paterno' => $role,
                'apellido_materno' => 'CORREGIDO',
                'genero' => 'HOMBRE',
            ])->assertOk()->assertJsonPath('patient.apellido_materno', 'CORREGIDO');
        }

        $administrator = $this->createUserWithRole('ADMINISTRADOR');
        $this->actingAs($administrator)->postJson('/patients', [])->assertForbidden();
        $this->actingAs($administrator)->putJson('/patients/'.$patient->id, [])->assertForbidden();
    }

    public function test_patient_write_validation_prevents_duplicates_and_undocumented_fake_identifiers(): void
    {
        $admission = $this->createUserWithRole('ADMISION');
        $this->createPatient($admission, ['numero_identidad' => '73378485']);

        $payload = [
            'tipo_identificacion' => 'DNI',
            'numero_identidad' => '73378485',
            'nombre' => 'OTRA',
            'apellido_paterno' => 'PERSONA',
            'apellido_materno' => 'DEMO',
            'genero' => 'MUJER',
        ];

        $this->actingAs($admission)->postJson('/patients', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('numero_identidad');

        $payload['tipo_identificacion'] = 'SIN DOCUMENTOS';
        $payload['numero_identidad'] = '';
        $this->actingAs($admission)->postJson('/patients', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tipo_identificacion', 'numero_identidad']);

        $this->assertSame(1, Patient::count());
    }

    public function test_reception_can_open_the_save_control(): void
    {
        $reception = $this->createUserWithRole('RECEPCION');

        $this->actingAs($reception)->get('/receptionist/patient')
            ->assertOk()
            ->assertSee('data-can-write="1"', false)
            ->assertDontSee('id="patient-save" disabled', false);
    }

    public function test_an_administrator_renders_the_save_control_disabled(): void
    {
        $administrator = $this->createUserWithRole('ADMINISTRADOR');

        $this->actingAs($administrator)->get('/admin/patient')
            ->assertOk()
            ->assertSee('data-can-write="0"', false)
            ->assertSee('id="patient-save" disabled', false)
            ->assertSee('Acceso de solo lectura');
    }

    public function test_the_patient_table_has_no_double_click_open_handler(): void
    {
        $script = file_get_contents(public_path('js/patients/operational.js'));

        $this->assertIsString($script);
        $this->assertStringNotContainsString("addEventListener('dblclick'", $script);
        $this->assertStringContainsString("addEventListener('contextmenu'", $script);
    }
}
