<?php

namespace Tests\Feature\Scheduling;

use App\Models\Patient;
use App\Models\User;
use App\Services\ReniecService;
use App\Support\Scheduling\SchedulingCapability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

class AgendaPatientLookupTest extends TestCase
{
    use BuildsBaselineData;
    use RefreshDatabase;

    private const URI = '/scheduling-mvp/agenda/patient-lookup';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('scheduling.enabled', true);
        Http::fake();
        $this->app->bind(ReniecService::class, function () {
            throw new \RuntimeException('Agenda must not call RENIEC during local lookup.');
        });
    }

    public function test_an_active_patient_with_the_same_document_is_found(): void
    {
        $patient = $this->createPatient(null, [
            'historia_clinica' => '42',
            'nombre' => 'Maria',
            'apellido_paterno' => 'Perez',
            'apellido_materno' => 'Demo',
            'tipo_identificacion' => 'DNI',
            'numero_identidad' => '70000001',
            'telefono' => '999888777',
            'email' => 'maria@example.invalid',
            'direccion' => 'Calle reservada',
            'estado' => 'ACTIVO',
        ]);

        $this->actingAs($this->reader())
            ->postJson(self::URI, [
                'tipo_identificacion' => '  DNI  ',
                'numero_identidad' => '  70000001  ',
            ])
            ->assertOk()
            ->assertExactJson([
                'status' => 'found',
                'patient' => [
                    'patient_id' => $patient->id,
                    'historia_clinica' => '42',
                    'tipo_identificacion' => 'DNI',
                    'numero_identidad' => '70000001',
                    'nombre' => 'Maria',
                    'apellido_paterno' => 'Perez',
                    'apellido_materno' => 'Demo',
                    'estado' => 'ACTIVO',
                ],
            ]);

        Http::assertNothingSent();
        $this->assertSame(1, Patient::count());
    }

    public function test_an_unknown_document_number_is_not_found(): void
    {
        $this->createPatient();

        $this->actingAs($this->reader())
            ->postJson(self::URI, [
                'tipo_identificacion' => 'DNI',
                'numero_identidad' => '70999999',
            ])
            ->assertOk()
            ->assertExactJson([
                'status' => 'not_found',
                'message' => 'Paciente no registrado',
            ]);

        Http::assertNothingSent();
    }

    public function test_the_same_number_with_another_document_type_is_a_conflict(): void
    {
        $this->createPatient(null, [
            'tipo_identificacion' => 'DNI',
            'numero_identidad' => '70000001',
        ]);

        $this->actingAs($this->reader())
            ->postJson(self::URI, [
                'tipo_identificacion' => 'PASAPORTE',
                'numero_identidad' => '70000001',
            ])
            ->assertOk()
            ->assertExactJson([
                'status' => 'document_conflict',
                'message' => 'El número de documento ya está registrado con otro tipo de identificación.',
            ]);

        $this->assertSame('DNI', Patient::first()->tipo_identificacion);
        Http::assertNothingSent();
    }

    public function test_an_inactive_patient_stays_inactive(): void
    {
        $patient = $this->createPatient(null, [
            'historia_clinica' => '7',
            'nombre' => 'Lucia',
            'apellido_paterno' => 'Vega',
            'apellido_materno' => 'Demo',
            'tipo_identificacion' => 'DNI',
            'numero_identidad' => '70000008',
            'estado' => 'INACTIVO',
        ]);

        $this->actingAs($this->reader())
            ->postJson(self::URI, [
                'tipo_identificacion' => 'DNI',
                'numero_identidad' => '70000008',
            ])
            ->assertOk()
            ->assertExactJson([
                'status' => 'inactive',
                'message' => 'Paciente registrado, actualmente inactivo.',
                'patient' => [
                    'patient_id' => $patient->id,
                    'historia_clinica' => '7',
                    'tipo_identificacion' => 'DNI',
                    'numero_identidad' => '70000008',
                    'nombre' => 'Lucia',
                    'apellido_paterno' => 'Vega',
                    'apellido_materno' => 'Demo',
                    'estado' => 'INACTIVO',
                ],
            ]);

        $this->assertSame('INACTIVO', $patient->fresh()->estado);
        Http::assertNothingSent();
    }

    public function test_lookup_requires_authentication_and_the_agenda_read_capability(): void
    {
        config()->set('scheduling.enabled', false);
        $this->postJson(self::URI, $this->document())->assertNotFound();

        config()->set('scheduling.enabled', true);
        $this->postJson(self::URI, $this->document())->assertUnauthorized();

        $signedIn = $this->createUser();
        $this->actingAs($signedIn)->postJson(self::URI, $this->document())->assertForbidden();

        $signedIn->givePermissionTo(Permission::findOrCreate(SchedulingCapability::MVP_ACCESS, 'web'));
        $this->actingAs($signedIn)->postJson(self::URI, $this->document())->assertForbidden();
    }

    public function test_a_found_patient_does_not_expose_the_rest_of_the_record(): void
    {
        $this->createPatient(null, [
            'telefono' => '999888777',
            'email' => 'secreto@example.invalid',
            'direccion' => 'Jr. Privado 123',
            'fecha_nacimiento' => '1991-02-03',
            'genero' => 'MUJER',
        ]);

        $body = $this->actingAs($this->reader())
            ->postJson(self::URI, $this->document())
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('telefono', $body);
        $this->assertStringNotContainsString('999888777', $body);
        $this->assertStringNotContainsString('email', $body);
        $this->assertStringNotContainsString('secreto@example.invalid', $body);
        $this->assertStringNotContainsString('direccion', $body);
        $this->assertStringNotContainsString('fecha_nacimiento', $body);
        $this->assertStringNotContainsString('genero', $body);
    }

    public function test_the_document_stays_a_string_and_is_only_trimmed(): void
    {
        $this->createPatient(null, [
            'tipo_identificacion' => 'PASAPORTE',
            'numero_identidad' => 'AB-12',
        ]);
        $this->createPatient(null, [
            'tipo_identificacion' => 'DNI',
            'numero_identidad' => '00012345',
        ]);

        $this->actingAs($this->reader())
            ->postJson(self::URI, [
                'tipo_identificacion' => 'PASAPORTE',
                'numero_identidad' => 'AB12',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'not_found');

        $this->actingAs($this->reader())
            ->postJson(self::URI, [
                'tipo_identificacion' => 'DNI',
                'numero_identidad' => '12345',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'not_found');

        $this->actingAs($this->reader())
            ->postJson(self::URI, [
                'tipo_identificacion' => 'DNI',
                'numero_identidad' => '00012345',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'found')
            ->assertJsonPath('patient.numero_identidad', '00012345');

        Http::assertNothingSent();
    }

    private function reader(): User
    {
        $user = $this->createUser();
        $user->givePermissionTo(Permission::findOrCreate(SchedulingCapability::MVP_ACCESS, 'web'));
        $user->givePermissionTo(Permission::findOrCreate(SchedulingCapability::VIEW, 'web'));

        return $user;
    }

    /**
     * @return array<string, string>
     */
    private function document(): array
    {
        return [
            'tipo_identificacion' => 'DNI',
            'numero_identidad' => '70000001',
        ];
    }
}
