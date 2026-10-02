<?php

namespace Tests\Feature\Patients;

use App\Models\Channel;
use App\Models\Patient;
use App\Models\Responsible;
use App\Support\Patients\DemoChannelCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

class OperationalPatientFormTest extends TestCase
{
    use BuildsBaselineData;
    use RefreshDatabase;

    public function test_email_is_optional_and_rejects_an_invalid_address(): void
    {
        $user = $this->createUserWithRole('ADMISION');

        $this->actingAs($user)->postJson('/patients', $this->payload([
            'email' => null,
        ]))->assertCreated();

        $this->actingAs($user)->postJson('/patients', $this->payload([
            'numero_identidad' => '73378486',
            'email' => 'persona@dominio.com',
        ]))->assertCreated()
            ->assertJsonPath('patient.email', 'persona@dominio.com');

        $this->actingAs($user)->postJson('/patients', $this->payload([
            'numero_identidad' => '73378487',
            'email' => 'abc',
        ]))->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Ingresa un correo electrónico válido.');

        $this->actingAs($user)->postJson('/patients', $this->payload([
            'numero_identidad' => '73378488',
            'email' => 'correo@',
        ]))->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Ingresa un correo electrónico válido.');
    }

    public function test_phone_uses_peru_by_default_and_persists_another_prefix(): void
    {
        $user = $this->createUserWithRole('ADMISION');

        $created = $this->actingAs($user)->postJson('/patients', $this->payload([
            'telefono_prefijo' => '+51',
            'telefono_numero' => '987654321',
        ]))->assertCreated();

        $this->assertSame('+51987654321', $created->json('patient.telefono'));

        $other = $this->actingAs($user)->postJson('/patients', $this->payload([
            'numero_identidad' => '73378486',
            'telefono_prefijo' => '+54',
            'telefono_numero' => '1112345678',
        ]))->assertCreated();

        $this->assertSame('+541112345678', $other->json('patient.telefono'));

        $this->actingAs($user)
            ->getJson('/patients/'.$created->json('patient.id'))
            ->assertOk()
            ->assertJsonPath('patient.telefono', '+51987654321');
    }

    public function test_an_unparsed_legacy_phone_is_kept_when_it_cannot_be_split(): void
    {
        $user = $this->createUserWithRole('ADMISION');
        $patient = $this->createPatient($user, ['telefono' => 'anexo 12']);

        $this->actingAs($user)->putJson('/patients/'.$patient->id, $this->payload([
            'numero_identidad' => $patient->numero_identidad,
            'telefono_numero' => 'anexo 12',
            'telefono_sin_separar' => true,
        ]))->assertOk();

        $this->assertSame('anexo 12', $patient->fresh()->telefono);
    }

    public function test_channels_come_from_the_catalog_and_demo_names_can_be_inserted_without_replacing_existing_rows(): void
    {
        $user = $this->createUserWithRole('ADMISION');
        Channel::create(['nombre' => 'Facebook', 'estado' => 'INACTIVO']);
        Channel::create(['nombre' => 'Referido', 'estado' => 'ACTIVO']);

        $created = DemoChannelCatalog::ensure(true);

        $this->assertEquals(['TikTok', 'Redes del Dr. Julio Quiroz'], $created);
        $this->assertSame('INACTIVO', Channel::query()->where('nombre', 'Facebook')->value('estado'));
        $this->assertSame(1, Channel::query()->where('nombre', 'Facebook')->count());
        $this->assertSame([], DemoChannelCatalog::ensure(true));

        Channel::query()->where('nombre', 'Facebook')->update(['estado' => 'ACTIVO']);

        $html = $this->actingAs($user)->get('/admissionist/patient')->assertOk()->getContent();

        $this->assertStringContainsString('Facebook', $html);
        $this->assertStringContainsString('TikTok', $html);
        $this->assertStringContainsString('Redes del Dr. Julio Quiroz', $html);
        $this->assertStringContainsString('Referido', $html);
        $this->assertStringContainsString('>SOLTERO<', $html);
        $this->assertStringContainsString('>CASADO<', $html);
        $this->assertStringContainsString('>VIUDO<', $html);
        $this->assertStringContainsString('>DIVORCIADO<', $html);
        $this->assertStringContainsString('id="patient-phone-prefix"', $html);
        $this->assertStringContainsString('Perú +51', $html);
        $this->assertStringContainsString('type="email"', $html);
        $this->assertStringContainsString('id="patient-register-responsible"', $html);
    }

    public function test_demo_channel_insert_does_not_run_outside_local(): void
    {
        $this->assertSame([], DemoChannelCatalog::ensure());
        $this->assertSame(0, Channel::query()->count());
    }

    public function test_civil_status_rejects_a_free_text_value(): void
    {
        $user = $this->createUserWithRole('ADMISION');

        $this->actingAs($user)->postJson('/patients', $this->payload([
            'estado_civil' => 'SOLTERO',
        ]))->assertCreated()->assertJsonPath('patient.estado_civil', 'SOLTERO');

        $this->actingAs($user)->postJson('/patients', $this->payload([
            'numero_identidad' => '73378486',
            'estado_civil' => 'cualquier cosa',
        ]))->assertUnprocessable()->assertJsonValidationErrors('estado_civil');
    }

    public function test_an_adult_can_be_saved_with_or_without_a_responsible(): void
    {
        $user = $this->createUserWithRole('ADMISION');

        $this->actingAs($user)->postJson('/patients', $this->payload([
            'fecha_nacimiento' => '1990-01-01',
        ]))->assertCreated();
        $this->assertSame(0, Responsible::query()->count());

        $created = $this->actingAs($user)->postJson('/patients', $this->payload(array_merge(
            $this->responsible(),
            ['numero_identidad' => '73378486', 'fecha_nacimiento' => '1990-01-01']
        )))->assertCreated();

        $this->assertSame(1, Responsible::query()->count());
        $this->assertSame('PAPA', $created->json('patient.responsable.parentezco'));
        $this->assertSame('ANA DEMO', $created->json('patient.responsable.nombres'));
    }

    public function test_a_minor_without_a_responsible_is_rejected_and_one_with_a_responsible_is_stored(): void
    {
        $user = $this->createUserWithRole('ADMISION');

        $this->actingAs($user)->postJson('/patients', $this->payload([
            'fecha_nacimiento' => '2015-01-01',
        ]))->assertUnprocessable()
            ->assertJsonPath('errors.registrar_responsable.0', 'Los pacientes menores de edad deben registrar un responsable o acompañante adulto.');
        $this->assertSame(0, Patient::query()->count());

        $this->actingAs($user)->postJson('/patients', $this->payload(array_merge(
            $this->responsible(),
            ['fecha_nacimiento' => '2015-01-01']
        )))->assertCreated();

        $this->assertSame(1, Patient::query()->count());
        $this->assertSame(1, Responsible::query()->count());
    }

    public function test_an_empty_birth_date_does_not_invent_a_minor(): void
    {
        $user = $this->createUserWithRole('ADMISION');

        $this->actingAs($user)->postJson('/patients', $this->payload([
            'fecha_nacimiento' => null,
        ]))->assertCreated();
        $this->assertSame(0, Responsible::query()->count());
    }

    public function test_editing_the_same_record_updates_the_responsible_instead_of_duplicating_it(): void
    {
        $user = $this->createUserWithRole('ADMISION');
        $created = $this->actingAs($user)->postJson('/patients', $this->payload(array_merge(
            $this->responsible(),
            ['fecha_nacimiento' => '2015-01-01']
        )))->assertCreated();
        $patientId = $created->json('patient.id');

        $this->actingAs($user)->putJson('/patients/'.$patientId, $this->payload(array_merge(
            $this->responsible(),
            [
                'numero_identidad' => '73378485',
                'fecha_nacimiento' => '2015-01-01',
                'responsable_nombres' => 'ANA DEMO ACTUALIZADA',
            ]
        )))->assertOk();

        $this->assertSame(1, Responsible::query()->where('patient_id', $patientId)->count());
        $this->assertSame('ANA DEMO ACTUALIZADA', Responsible::query()->where('patient_id', $patientId)->value('nombres'));

        $this->actingAs($user)->putJson('/patients/'.$patientId, $this->payload([
            'numero_identidad' => '73378485',
            'fecha_nacimiento' => '2015-01-01',
            'nombre' => 'MARIA ELENA',
        ]))->assertUnprocessable();

        $this->actingAs($user)->putJson('/patients/'.$patientId, $this->payload(array_merge(
            $this->responsible(),
            [
                'numero_identidad' => '73378485',
                'fecha_nacimiento' => '1990-01-01',
                'nombre' => 'MARIA ELENA',
                'registrar_responsable' => false,
            ]
        )))->assertOk()->assertJsonPath('patient.nombre', 'MARIA ELENA');

        $this->assertSame(1, Responsible::query()->where('patient_id', $patientId)->count());
        $this->assertSame('ANA DEMO ACTUALIZADA', Responsible::query()->where('patient_id', $patientId)->value('nombres'));
    }

    public function test_the_module_returns_to_the_list_after_create_and_keeps_edit_on_the_record(): void
    {
        $script = file_get_contents(public_path('js/patients/operational.js'));
        $workspace = file_get_contents(public_path('js/patients/patient-workspace.js'));

        $this->assertStringContainsString('workspace.afterCreate()', $script);
        $this->assertStringContainsString('prependCreated(patient)', $script);
        $this->assertStringContainsString("state.mode = 'existing'", $script);
        $this->assertStringContainsString("registro: '—'", $workspace);
        $this->assertStringNotContainsString('window.location', $script);
    }

    public function test_agenda_save_still_returns_the_patient_and_does_not_navigate_to_the_patient_list(): void
    {
        $user = $this->createUserWithRole('ADMISION');
        $script = file_get_contents(public_path('js/scheduling/agenda.js'));

        $response = $this->actingAs($user)->postJson('/patients', $this->payload([
            'telefono_prefijo' => '+51',
            'telefono_numero' => '987654321',
        ]));

        $response->assertCreated()
            ->assertJsonPath('patient.patient_id', fn ($id) => is_int($id))
            ->assertJsonPath('patient.historia_clinica', '01-73378485')
            ->assertJsonPath('patient.telefono', '+51987654321');
        $this->assertStringContainsString('outcome.attach', $script);
        $this->assertStringContainsString('await createAppointment()', $script);
        $this->assertStringContainsString('draftModel.toPayload(currentDraft)', $script);
        $this->assertStringNotContainsString("location.href", $script);
        $this->assertStringContainsString(
            'Preparando la cita.',
            file_get_contents(public_path('js/scheduling/agenda-patient-draft.js'))
        );
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
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

    /**
     * @return array<string, mixed>
     */
    private function responsible(): array
    {
        return [
            'registrar_responsable' => true,
            'responsable_parentesco' => 'PAPA',
            'responsable_nombres' => 'ANA DEMO',
            'responsable_telefono' => '999111222',
            'responsable_tipo_identificacion' => 'DNI',
            'responsable_numero_identidad' => '12345678',
        ];
    }
}
