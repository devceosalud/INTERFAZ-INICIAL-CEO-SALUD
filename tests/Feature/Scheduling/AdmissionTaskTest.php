<?php

namespace Tests\Feature\Scheduling;

use App\Models\Appointment;
use App\Services\Scheduling\AdmissionTaskService;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

class AdmissionTaskTest extends TestCase
{
    use RefreshDatabase, BuildsAgendaLifecycleData;

    private $admin;
    private $catalog;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scheduling.enabled' => true, 'app.debug' => false]);
        $this->admin = $this->agendaReader('ADMINISTRADOR');
        $this->catalog = $this->createAppointmentCatalog();
        $this->actingAs($this->admin);
    }

    private function appointment(array $attrs = []): Appointment
    {
        return $this->lifecycleAppointment($this->admin, $this->catalog, $attrs);
    }

    private function createTask(Appointment $a, string $text = 'Confirmar radiografía pendiente', ?string $key = null)
    {
        return $this->postJson(route('scheduling.mvp.admission.tasks.store', $a->id), [
            'text' => $text, 'request_key' => $key ?? (string) Str::uuid(),
        ]);
    }

    private function toggle(Appointment $a, int $id, bool $expected, bool $done)
    {
        return $this->patchJson(route('scheduling.mvp.admission.tasks.completion', [$a->id, $id]), [
            'expected_completed' => $expected, 'completed' => $done,
        ]);
    }

    public function test_only_admin_can_create_and_admission_or_commercial_can_mark_done_without_editing_text(): void
    {
        $a = $this->appointment();
        $before = $a->fresh()->getAttributes();
        $this->createTask($a)->assertCreated();
        $task = DB::table('appointment_admission_tasks')->sole();
        $this->getJson(route('scheduling.mvp.admission.detail', $a->id))->assertOk()
            ->assertJsonPath('can_manage_operational_tasks', true)
            ->assertJsonPath('operational_tasks.0.text', 'Confirmar radiografía pendiente')
            ->assertJsonPath('operational_tasks.0.created_by', $this->admin->name);
        foreach (['ADMISION', 'COMERCIAL'] as $role) {
            $worker = $this->agendaReader($role);
            $this->actingAs($worker);
            $this->getJson(route('scheduling.mvp.admission.detail', $a->id))->assertOk()
                ->assertJsonPath('can_manage_operational_tasks', false)
                ->assertJsonPath('can_complete_operational_tasks', true)
                ->assertJsonMissingPath('operational_tasks.0.created_by');
            $this->createTask($a)->assertForbidden();
            $this->toggle($a, $task->id, false, true)->assertOk();
            $this->toggle($a, $task->id, false, true)->assertConflict();
            $this->toggle($a, $task->id, true, false)->assertOk();
        }
        $this->assertSame($before, $a->fresh()->getAttributes());
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('appointment_operations', 0);
        $this->assertDatabaseCount('appointment_admission_tasks', 1);
        $this->assertDatabaseCount('appointment_admission_task_events', 5);
        $this->assertSame(['CREATED', 'COMPLETED', 'REOPENED', 'COMPLETED', 'REOPENED'],
            DB::table('appointment_admission_task_events')->orderBy('id')->pluck('action')->all());
    }

    public function test_read_permissions_are_narrow_and_do_not_leak_tasks_to_other_roles_or_feed(): void
    {
        $a = $this->appointment();
        $this->createTask($a, 'CONTROL OPERATIVO MUY PRIVADO')->assertCreated();
        foreach (['RECEPCION', 'CAJA', 'FACTURACION'] as $role) {
            $actor = $this->agendaReader($role);
            $this->actingAs($actor);
            $result = $this->getJson(route('scheduling.mvp.admission.detail', $a->id))->assertOk()
                ->assertJsonPath('operational_tasks', [])
                ->assertJsonPath('can_manage_operational_tasks', false)
                ->assertJsonPath('can_complete_operational_tasks', false);
            $this->assertStringNotContainsString('CONTROL OPERATIVO MUY PRIVADO', $result->getContent());
            $this->createTask($a)->assertForbidden();
            $this->toggle($a, 1, false, true)->assertForbidden();
        }
        $this->actingAs($this->admin);
        $feed = $this->postJson(route('scheduling.mvp.admission.feed'), [
            'fecha' => now()->toDateString(),
        ])->assertOk();
        $this->assertStringNotContainsString('CONTROL OPERATIVO MUY PRIVADO', $feed->getContent());
        $agenda = $this->getJson('/scheduling-mvp/agenda/feed?' . http_build_query([
            'vista' => 'dia', 'fecha' => now()->toDateString(),
            'doctor_id' => [$this->catalog['doctor']->id],
        ]))->assertOk();
        $this->assertStringNotContainsString('CONTROL OPERATIVO MUY PRIVADO', $agenda->getContent());
    }

    public function test_private_reservation_is_not_exposed_to_admin_creator_or_unrelated_commercial(): void
    {
        $owner = $this->agendaReader('COMERCIAL');
        $other = $this->agendaReader('COMERCIAL');
        $a = $this->appointment();
        $this->createTask($a, 'Pendiente sólo de su responsable')->assertCreated();
        $a->forceFill(['estado_agenda' => 'PENDIENTE_CONFIRMACION', 'responsible_user_id' => $owner->id])->save();
        $this->getJson(route('scheduling.mvp.admission.detail', $a->id))->assertNotFound();
        $this->createTask($a)->assertNotFound();
        $this->actingAs($other);
        $this->getJson(route('scheduling.mvp.admission.detail', $a->id))->assertNotFound();
        $this->toggle($a, 1, false, true)->assertNotFound();
        $this->actingAs($owner);
        $this->getJson(route('scheduling.mvp.admission.detail', $a->id))->assertOk()
            ->assertJsonPath('operational_tasks.0.text', 'Pendiente sólo de su responsable');
        $this->toggle($a, 1, false, true)->assertOk();
    }

    public function test_admin_permission_is_opt_in_and_no_appointment_update_permission_is_required(): void
    {
        $a = $this->appointment();
        $writer = $this->agendaReader('RECEPCION');
        $this->actingAs($writer);
        $this->createTask($a)->assertForbidden();
        $writer->givePermissionTo(Permission::findOrCreate(AdmissionTaskService::MANAGE, 'web'));
        $this->createTask($a)->assertCreated();
        $this->getJson(route('scheduling.mvp.admission.detail', $a->id))
            ->assertJsonPath('can_manage_operational_tasks', true);
        $writer->revokePermissionTo(AdmissionTaskService::MANAGE);
        $this->getJson(route('scheduling.mvp.admission.detail', $a->id))
            ->assertJsonPath('operational_tasks', []);
        $this->assertDatabaseCount('appointment_admission_tasks', 1);
        $this->assertDatabaseCount('appointment_events', 0);
    }

    public function test_idempotent_create_invalid_fields_stale_status_and_other_appointment_are_guarded(): void
    {
        $a = $this->appointment();
        $b = $this->appointment();
        $key = (string) Str::uuid();
        $this->createTask($a, 'Primera tarea', $key)->assertCreated();
        $this->createTask($a, 'Primera tarea', $key)->assertCreated();
        $this->createTask($a, 'Texto cambiado', $key)->assertConflict();
        $this->createTask($b, 'Primera tarea', $key)->assertConflict();
        $this->postJson(route('scheduling.mvp.admission.tasks.store', $a->id), [
            'text' => 'Otra tarea', 'request_key' => (string) Str::uuid(), 'total_pagado' => 900,
        ])->assertUnprocessable();
        $this->createTask($a, '  ')->assertUnprocessable();
        $this->createTask($a, str_repeat('A', 1001))->assertUnprocessable();
        $id = DB::table('appointment_admission_tasks')->value('id');
        $this->toggle($a, $id, false, true)->assertOk();
        $this->toggle($a, $id, false, true)->assertConflict();
        $this->toggle($b, $id, true, false)->assertNotFound();
        $this->patchJson(route('scheduling.mvp.admission.tasks.completion', [$a->id, $id]), [
            'expected_completed' => true, 'completed' => false, 'observaciones' => 'ALTERADO',
        ])->assertUnprocessable();
        $this->assertDatabaseCount('appointment_admission_tasks', 1);
        $this->assertDatabaseCount('appointment_admission_task_events', 2);
        $this->assertDatabaseCount('payments', 0);
    }
}
