<?php

namespace Tests\Feature\Patients;

use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

class PatientClinicalHistoryNumberTest extends TestCase
{
    use BuildsBaselineData;
    use RefreshDatabase;

    /**
     * @dataProvider mappedDocuments
     */
    public function test_operational_creation_persists_and_returns_hce(string $type, string $number, string $expected): void
    {
        $user = $this->createUserWithRole('ADMISION');

        $response = $this->actingAs($user)->postJson('/patients', $this->payload([
            'tipo_identificacion' => $type,
            'numero_identidad' => '  '.$number.'  ',
        ]));

        $response->assertCreated()
            ->assertJsonPath('patient.historia_clinica', $expected)
            ->assertJsonPath('patient.numero_identidad', $number);
        $this->assertDatabaseHas('patients', [
            'numero_identidad' => $number,
            'historia_clinica' => $expected,
        ]);
    }

    public function test_patient_module_list_displays_the_persisted_hce(): void
    {
        $user = $this->createUserWithRole('ADMISION');
        $this->actingAs($user)->postJson('/patients', $this->payload())->assertCreated();

        $this->actingAs($user)
            ->get('/admissionist/patient')
            ->assertOk()
            ->assertSee('01-73378485');
    }

    public function test_agenda_shared_mutation_returns_the_persisted_hce_without_creating_an_appointment(): void
    {
        $user = $this->createUserWithRole('ADMISION');

        $this->actingAs($user)
            ->postJson('/patients', $this->payload())
            ->assertCreated()
            ->assertJsonPath('patient.historia_clinica', '01-73378485');

        $this->assertDatabaseCount('patients', 1);
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_legacy_patient_endpoint_uses_the_same_generator_instead_of_numeric_increment(): void
    {
        $user = $this->createUserWithRole('ADMISION');

        $this->actingAs($user)
            ->postJson('/admissionist/patient/store', [
                'nombre_paciente' => 'MARIA',
                'apellido_paterno' => 'PEREZ',
                'apellido_materno' => 'DEMO',
                'genero_paciente' => 'MUJER',
                'tipo_identificacion' => 'DNI',
                'numero_identidad' => '73378485',
                'fecha_nacimiento' => '1990-01-01',
                'email' => 'maria@example.invalid',
            ])
            ->assertOk()
            ->assertJsonPath('patient.historia_clinica', '01-73378485');
    }

    public function test_editing_document_or_other_data_never_regenerates_hce(): void
    {
        $user = $this->createUserWithRole('ADMISION');
        $created = $this->actingAs($user)->postJson('/patients', $this->payload())->assertCreated();
        $patientId = $created->json('patient.id');

        $this->actingAs($user)->putJson('/patients/'.$patientId, $this->payload([
            'tipo_identificacion' => 'PASAPORTE',
            'numero_identidad' => 'AB-123',
            'nombre' => 'MARIA ELENA',
        ]))->assertOk()->assertJsonPath('patient.historia_clinica', '01-73378485');

        $this->actingAs($user)->putJson('/patients/'.$patientId, $this->payload([
            'tipo_identificacion' => 'PASAPORTE',
            'numero_identidad' => 'AB-123',
            'telefono' => '999111222',
        ]))->assertOk()->assertJsonPath('patient.historia_clinica', '01-73378485');
    }

    public function test_inherited_hce_and_inherited_null_are_not_changed_or_backfilled(): void
    {
        $user = $this->createUserWithRole('ADMISION');
        $withHce = $this->createPatient($user, ['historia_clinica' => '9000']);
        $withoutHce = $this->createPatient($user, [
            'numero_identidad' => '73378486',
            'historia_clinica' => null,
        ]);

        $this->actingAs($user)->getJson('/patients/'.$withoutHce->id)
            ->assertOk()
            ->assertJsonPath('patient.historia_clinica', null);

        $this->actingAs($user)->putJson('/patients/'.$withHce->id, $this->payload([
            'numero_identidad' => $withHce->numero_identidad,
        ]))->assertOk()->assertJsonPath('patient.historia_clinica', '9000');
        $this->actingAs($user)->putJson('/patients/'.$withoutHce->id, $this->payload([
            'numero_identidad' => $withoutHce->numero_identidad,
        ]))->assertOk()->assertJsonPath('patient.historia_clinica', null);
    }

    public function test_duplicate_document_keeps_the_existing_database_protection(): void
    {
        $user = $this->createUserWithRole('ADMISION');
        $this->actingAs($user)->postJson('/patients', $this->payload())->assertCreated();

        $this->actingAs($user)->postJson('/patients', $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('numero_identidad');
        $this->assertDatabaseCount('patients', 1);
    }

    public function test_ruc_has_no_invented_mapping_and_sin_documentos_stays_blocked(): void
    {
        $user = $this->createUserWithRole('ADMISION');

        $this->actingAs($user)->postJson('/patients', $this->payload([
            'tipo_identificacion' => 'RUC',
            'numero_identidad' => '20123456789',
        ]))->assertCreated()->assertJsonPath('patient.historia_clinica', null);

        $this->actingAs($user)->postJson('/patients', $this->payload([
            'tipo_identificacion' => 'SIN DOCUMENTOS',
            'numero_identidad' => 'PENDIENTE',
        ]))->assertUnprocessable()->assertJsonValidationErrors('tipo_identificacion');
    }

    public function test_partial_reniec_data_does_not_affect_hce_when_document_is_present(): void
    {
        $user = $this->createUserWithRole('ADMISION');

        $this->actingAs($user)->postJson('/patients', $this->payload([
            'fecha_nacimiento' => null,
            'email' => null,
            'telefono' => null,
        ]))->assertCreated()->assertJsonPath('patient.historia_clinica', '01-73378485');
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function mappedDocuments(): array
    {
        return [
            'DNI' => ['DNI', '73378485', '01-73378485'],
            'CE' => ['CARNET EXTRANJERIA', 'CE-123', '02-CE-123'],
            'passport' => ['PASAPORTE', 'P-123', '03-P-123'],
            'PTP' => ['PTP', 'PTP-123', '04-PTP-123'],
            'TAM' => ['TAM', 'TAM-123', '05-TAM-123'],
            'safe conduct' => ['SALVOCONDUCTO', 'S-123', '06-S-123'],
        ];
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'tipo_identificacion' => 'DNI',
            'numero_identidad' => '73378485',
            'nombre' => 'MARIA',
            'apellido_paterno' => 'PEREZ',
            'apellido_materno' => 'DEMO',
            'genero' => 'MUJER',
            'fecha_nacimiento' => '1990-01-01',
            'registrar_responsable' => false,
        ], $overrides);
    }
}
