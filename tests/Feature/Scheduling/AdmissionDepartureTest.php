<?php

namespace Tests\Feature\Scheduling;

use App\Models\Appointment;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

class AdmissionDepartureTest extends TestCase
{
    use RefreshDatabase, BuildsAgendaLifecycleData;
    private $actor, $catalog;
    protected function setUp(): void
    {
        parent::setUp(); config(['scheduling.enabled'=>true]);
        $this->actor=$this->agendaReader('ADMISION');
        $this->actor->givePermissionTo(Permission::findOrCreate(C::UPDATE,'web'));
        $this->catalog=$this->createAppointmentCatalog();$this->actingAs($this->actor);
    }
    private function appointment(array $attributes=[]): Appointment
    {
        return $this->lifecycleAppointment($this->actor,$this->catalog,array_replace([
            'estado_cita'=>'ATENDIDO','fecha_cita'=>$this->catalog['schedule']->fecha_cita],$attributes));
    }
    private function depart(Appointment $a,array $changes=[])
    {
        return $this->postJson(route('scheduling.mvp.admission.care',$a->id),array_replace([
            'state'=>'SALIDA_REGISTRADA','expected_state'=>'ATENDIDO','request_key'=>(string)Str::uuid(),
            'motivo'=>'Salida física comprobada QA ficticia'],$changes));
    }
    public function test_explicit_departure_is_idempotent_audited_and_preserves_finances_and_reevaluation(): void
    {
        $a=$this->appointment();$a->forceFill(['hora_llegada'=>now()->subHour(),'hora_atendido'=>now()->subMinutes(10)])->save();
        $allowed=array_flip(['hora_salida','updated_at','updated_by_user_id']);
        $before=array_diff_key($a->fresh()->getRawOriginal(),$allowed);$key=(string)Str::uuid();
        $this->getJson(route('scheduling.mvp.admission.detail',$a->id))->assertOk()->assertJsonPath('care_actions',['SALIDA_REGISTRADA']);
        $this->depart($a,['request_key'=>$key])->assertOk()->assertJsonPath('estado_cita','ATENDIDO');
        $first=$a->fresh()->hora_salida;
        $this->travel(1)->minutes();$this->depart($a,['request_key'=>$key])->assertOk();
        $this->assertTrue($first->equalTo($a->fresh()->hora_salida));
        $this->assertSame($before,array_diff_key($a->fresh()->getRawOriginal(),$allowed));
        $event=$a->events()->where('event_type','ADMISION_SALIDA_REGISTRADA')->sole();
        $this->assertSame($this->actor->id,(int)$event->actor_user_id);$this->assertSame('Salida física comprobada QA ficticia',$event->motivo);
        $detail=$this->getJson(route('scheduling.mvp.admission.detail',$a->id))->assertOk()->assertJsonPath('care_actions',[])
            ->assertJsonPath('appointment.presentation.strikethrough',true)->assertJsonPath('appointment.presentation.fondo','#99f6e4');
        $this->assertNotNull($detail->json('appointment.tiempos.salida'));
        $this->depart($a)->assertConflict();$this->depart($a,['request_key'=>$key,'motivo'=>'Cambiar el mismo intento'])->assertConflict();
        $this->assertDatabaseCount('appointment_operations',1);$this->assertDatabaseCount('payments',0);
        $this->assertDatabaseCount('appointment_reevaluation_indications',0);
    }
    public function test_invalid_state_reserve_and_manipulated_fields_leave_no_writes(): void
    {
        foreach (['PROGRAMADO','EN_ESPERA','EN_ATENCION','CANCELADO','RETIRO'] as $state) {
            $this->depart($this->appointment(['estado_cita'=>$state]),['expected_state'=>$state])->assertUnprocessable();
        }
        $this->depart($this->appointment(['estado_agenda'=>'PENDIENTE_CONFIRMACION','responsible_user_id'=>$this->actor->id]))->assertUnprocessable();
        $a=$this->appointment();$this->depart($a,['expected_state'=>'EN_ATENCION'])->assertConflict();
        foreach (['total_pagado'=>999,'hora_salida'=>'2000-01-01','es_exonerado'=>true] as $field=>$value) $this->depart($a,[$field=>$value])->assertUnprocessable();
        $this->assertSame(0,Appointment::whereNotNull('hora_salida')->count());$this->assertDatabaseCount('appointment_operations',0);$this->assertDatabaseCount('appointment_events',0);
    }
    public function test_revoked_permissions_and_private_reservations_have_no_admin_bypass(): void
    {
        $a=$this->appointment();$this->actor->revokePermissionTo(C::UPDATE);$this->depart($a)->assertForbidden();
        $this->actor->givePermissionTo(C::UPDATE);$this->actor->revokePermissionTo(C::VIEW);$this->depart($a)->assertForbidden();
        $other=$this->agendaReader('COMERCIAL');$hidden=$this->lifecycleAppointment($other,$this->catalog,['estado_agenda'=>'PENDIENTE_CONFIRMACION','estado_cita'=>'ATENDIDO','responsible_user_id'=>$other->id]);
        $admin=$this->agendaReader('ADMINISTRADOR');$admin->givePermissionTo(Permission::findOrCreate(C::UPDATE,'web'));
        $this->actingAs($admin);$this->depart($hidden)->assertNotFound();$this->depart($this->appointment(),['request_key'=>'not-a-uuid'])->assertUnprocessable();
        $this->assertDatabaseCount('appointment_operations',0);$this->assertSame(0,Appointment::whereNotNull('hora_salida')->count());
    }
    public function test_schema_rollback_refuses_to_erase_recorded_departures(): void
    {
        $a=$this->appointment();$this->depart($a)->assertOk();
        $migration=require database_path('migrations/2026_10_15_231000_add_explicit_appointment_departure.php');
        try { $migration->down(); $this->fail('Rollback must preserve recorded departures.'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('Conservar salidas',$e->getMessage()); }
        $this->assertNotNull($a->fresh()->hora_salida);
        $this->assertDatabaseCount('appointment_events',1);
    }

}
