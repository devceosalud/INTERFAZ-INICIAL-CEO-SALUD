<?php

namespace Tests\Feature\Catalog;

use App\Models\Doctor;
use App\Models\DoctorService;
use App\Services\Catalog\ActiveDoctorServiceResolver;
use App\Support\Scheduling\SchedulingCapability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

class ActiveDoctorServiceCatalogTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAgendaLifecycleData;

    private array $catalog;
    private DoctorService $current;
    private $actor;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scheduling.enabled' => true]);
        $this->actor = $this->agendaReader('ADMISION');
        $this->actor->givePermissionTo(Permission::findOrCreate(SchedulingCapability::CREATE, 'web'));
        $this->catalog = $this->createAppointmentCatalog();
        $this->catalog['doctorService']->update(['estado' => 'INACTIVO']);
        $this->catalog['rate']->update(['nombre' => 'TARIFA ESTANDAR', 'tipo_tarifa' => 'MONTO_FIJO']);
        $this->current = DoctorService::create([
            'doctor_id' => $this->catalog['doctor']->id, 'service_id' => $this->catalog['service']->id,
            'precio_primera_consulta' => 150, 'precio_reconsulta' => 120, 'dias_reconsulta' => 15, 'estado' => 'ACTIVO',
        ]);
    }

    public function test_only_the_active_assignment_is_current_and_general_doctors_keep_their_own_price(): void
    {
        $resolved = app(ActiveDoctorServiceResolver::class)->resolve($this->current->doctor_id, $this->current->service_id);
        $this->assertSame($this->current->id, $resolved->id);
        $this->assertEquals(150, $resolved->precio_primera_consulta);
        $this->assertEquals(120, $resolved->precio_reconsulta);
        $other = Doctor::create(['specialty_id' => $this->catalog['specialty']->id, 'nombre' => 'General', 'estado' => 'ACTIVO']);
        DoctorService::create(['doctor_id' => $other->id, 'service_id' => $this->current->service_id,
            'precio_primera_consulta' => 100, 'precio_reconsulta' => 80, 'estado' => 'ACTIVO']);
        $general = app(ActiveDoctorServiceResolver::class)->resolve($other->id, $this->current->service_id);
        $this->assertEquals(100, $general->precio_primera_consulta);
        $this->assertEquals(80, $general->precio_reconsulta);
        $this->assertEquals(100, $this->catalog['doctorService']->fresh()->precio_primera_consulta);
    }

    public function test_master_endpoint_rejects_another_active_assignment_without_changing_the_protected_controller(): void
    {
        $admin = $this->createUserWithRole('ADMINISTRADOR');
        $this->actingAs($admin)->postJson('/master/admin/doctor/services/store', [
            'doctor_id' => $this->current->doctor_id, 'service_id' => $this->current->service_id,
            'precio_estandar' => 999, 'reconsulta' => 888, 'dias' => 15,
        ])->assertUnprocessable()->assertJsonValidationErrors('service_id');
        $this->assertDatabaseCount('doctor_services', 2);
    }

    public function test_inactive_history_can_be_added_but_cannot_be_reactivated_over_an_active_assignment(): void
    {
        $inactive = DoctorService::create(array_merge($this->current->getAttributes(), ['id' => null, 'estado' => 'INACTIVO']));
        $this->assertDatabaseCount('doctor_services', 3);
        try { $inactive->update(['estado' => 'ACTIVO']); $this->fail('Reactivation must be rejected.'); }
        catch (ValidationException $exception) { $this->assertArrayHasKey('service_id', $exception->errors()); }
        $this->assertSame('INACTIVO', $inactive->fresh()->estado);
    }

    public function test_moving_an_assignment_to_an_already_active_pair_is_rejected(): void
    {
        $other = Doctor::create(['specialty_id' => $this->catalog['specialty']->id, 'nombre' => 'Other', 'estado' => 'ACTIVO']);
        $row = DoctorService::create(['doctor_id' => $other->id, 'service_id' => $this->current->service_id, 'estado' => 'ACTIVO']);
        try { $row->update(['doctor_id' => $this->current->doctor_id]); $this->fail('Duplicate move must fail.'); }
        catch (ValidationException $exception) { $this->assertArrayHasKey('service_id', $exception->errors()); }
        $this->assertSame($other->id, (int) $row->fresh()->doctor_id);
    }

    public function test_api_lists_and_prices_only_the_current_assignment_and_rejects_inactive_ids(): void
    {
        $this->actingAs($this->actor)->postJson('/api/appointment/service/doctor', ['doctor_id' => $this->current->doctor_id])
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->current->id);
        $payload = ['patient_id' => 999999, 'service_id' => $this->current->id, 'additional_rate_id' => $this->catalog['rate']->id];
        $this->postJson('/api/appointment/calculated', $payload)->assertOk()->assertJsonPath('precio_programado', 150);
        $this->postJson('/api/appointment/calculated', array_merge($payload, ['service_id' => $this->catalog['doctorService']->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('service_id');
        $this->postJson('/api/appointment/calculated', $payload + ['doctor_id' => 999999])
            ->assertUnprocessable()->assertJsonValidationErrors('service_id');
    }

    public function test_legacy_multiple_actives_make_both_api_readers_fail_explicitly(): void
    {
        DB::table('doctor_services')->insert(['doctor_id' => $this->current->doctor_id, 'service_id' => $this->current->service_id,
            'precio_primera_consulta' => 999, 'estado' => 'ACTIVO']);
        $this->actingAs($this->actor)->postJson('/api/appointment/service/doctor', ['doctor_id' => $this->current->doctor_id])
            ->assertUnprocessable()->assertJsonValidationErrors('service_id');
        $this->postJson('/api/appointment/calculated', ['service_id' => $this->current->id])
            ->assertUnprocessable()->assertJsonValidationErrors('service_id');
    }

    public function test_inactive_service_or_doctor_cannot_supply_a_current_price(): void
    {
        foreach ([$this->catalog['service'], $this->catalog['doctor']] as $model) {
            $model->update(['estado' => 'INACTIVO']);
            try { app(ActiveDoctorServiceResolver::class)->resolveAssignment($this->current->id); $this->fail('Inactive catalogue must fail.'); }
            catch (ValidationException $exception) { $this->assertArrayHasKey('service_id', $exception->errors()); }
            $model->update(['estado' => 'ACTIVO']);
        }
    }

    public function test_agenda_enables_current_service_and_new_booking_uses_150_without_repricing_history(): void
    {
        $historical = $this->lifecycleAppointment($this->actor, $this->catalog, ['precio_programado' => 100, 'hora_cita' => '07:00']);
        $before = $historical->fresh()->getAttributes();
        $this->actingAs($this->actor)->get(route('scheduling.mvp.agenda'))->assertOk()->assertViewHas('doctorServices', function ($catalog) {
            $row = $catalog[$this->current->doctor_id][0];
            return $row['disponible'] === true && $row['precio'] === 150.0;
        });
        $this->postJson(route('scheduling.mvp.agenda.appointments.store'), [
            'patient_id' => $historical->patient_id, 'doctor_id' => $this->current->doctor_id, 'service_id' => $this->current->service_id,
            'fecha_cita' => $this->catalog['schedule']->fecha_cita, 'hora_cita' => '08:00', 'duracion_cita' => 30,
        ])->assertCreated();
        $this->assertDatabaseHas('appointments', ['precio_programado' => 150, 'estado_pagado' => 'PENDIENTE']);
        $this->assertSame($before, $historical->fresh()->getAttributes());
    }
}
