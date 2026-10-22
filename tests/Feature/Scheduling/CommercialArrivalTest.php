<?php

namespace Tests\Feature\Scheduling;

use App\Models\Appointment;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

/** Permission boundary: COMERCIAL only records arrival, never a clinical action. */
class CommercialArrivalTest extends TestCase
{
    use RefreshDatabase, BuildsAgendaLifecycleData;

    private $commercial;
    private $catalog;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scheduling.enabled'=>true,'app.debug'=>false]);
        $this->commercial=$this->agendaReader('COMERCIAL');
        $this->catalog=$this->createAppointmentCatalog();
        $this->actingAs($this->commercial);
    }

    private function care(Appointment $a,string $state,?string $key=null,array $extra=[])
    {
        return $this->postJson(route('scheduling.mvp.admission.care',$a->id),array_replace([
            'state'=>$state,'expected_state'=>$a->estado_cita,
            'request_key'=>$key??(string) Str::uuid(),
        ],$extra));
    }

    public function test_commercial_records_arrival_for_another_workers_confirmed_patient_without_update_permission():void
    {
        $creator=$this->agendaReader('ADMISION');
        $a=$this->lifecycleAppointment($creator,$this->catalog,[
            'estado_agenda'=>'CONFIRMADA','estado_cita'=>'PROGRAMADO',
            'total_pagado'=>0,'saldo_pendiente'=>100,'estado_pagado'=>'PENDIENTE',
        ]);
        $financial=$a->only(['precio_programado','total_pagado','saldo_pendiente','estado_pagado','estado_agenda']);
        $this->assertFalse($this->commercial->can(C::UPDATE));
        $this->getJson(route('scheduling.mvp.admission.detail',$a->id))
            ->assertOk()->assertJsonPath('care_actions.0','EN_ESPERA')->assertJsonCount(1,'care_actions');
        $requestKey=(string)Str::uuid();
        $this->care($a,'EN_ESPERA',$requestKey)->assertOk()->assertJsonPath('estado_cita','EN_ESPERA');
        // Retrying the identical request must not duplicate the event or the timestamp.
        $this->care($a,'EN_ESPERA',$requestKey)->assertOk();
        $fresh=$a->fresh();
        $this->assertNotNull($fresh->hora_llegada);
        foreach(['hora_llamado','hora_atencion','hora_atendido','hora_salida'] as $field)$this->assertNull($fresh->{$field});
        $this->assertSame($financial,$fresh->only(array_keys($financial)));
        $this->assertDatabaseCount('payments',0);
        $this->assertSame(1,$fresh->events()->where('event_type','ADMISION_ESTADO_ACTUALIZADO')->count());
        $event=$fresh->events()->where('event_type','ADMISION_ESTADO_ACTUALIZADO')->firstOrFail();
        $this->assertSame($this->commercial->id,$event->actor_user_id);
        $this->assertSame('COMERCIAL_PATIENT_ARRIVAL_V1',$event->metadata['source']);
        $this->assertSame('EN_ESPERA',$event->metadata['to']);
        $this->getJson(route('scheduling.mvp.admission.detail',$a->id))
            ->assertOk()->assertJsonCount(0,'care_actions');
        foreach(['LLAMANDO','EN_ATENCION','ATENDIDO','SALIDA_REGISTRADA'] as $state){
            $this->care($fresh,$state)->assertForbidden();
        }
        $this->assertSame('EN_ESPERA',$a->fresh()->estado_cita);
    }

    public function test_commercial_cannot_arrive_private_pending_unconfirmed_or_teleconsultation_appointments():void
    {
        $pending=$this->lifecycleAppointment($this->commercial,$this->catalog,[
            'estado_agenda'=>'PENDIENTE_CONFIRMACION','estado_cita'=>'PROGRAMADO'
        ]);
        $this->getJson(route('scheduling.mvp.admission.detail',$pending->id))
            ->assertOk()->assertJsonCount(0,'care_actions');
        $this->care($pending,'EN_ESPERA')->assertUnprocessable();

        $other=$this->agendaReader('ADMISION');
        $hidden=$this->lifecycleAppointment($other,$this->catalog,[
            'estado_agenda'=>'PENDIENTE_CONFIRMACION','estado_cita'=>'PROGRAMADO'
        ]);
        $this->getJson(route('scheduling.mvp.admission.detail',$hidden->id))->assertNotFound();
        $this->care($hidden,'EN_ESPERA')->assertNotFound();

        $remote=$this->lifecycleAppointment($other,$this->catalog,[
            'attendance_mode'=>'TELECONSULTA'
        ]);
        $this->getJson(route('scheduling.mvp.admission.detail',$remote->id))
            ->assertOk()->assertJsonCount(0,'care_actions');
        $this->care($remote,'EN_ESPERA')->assertUnprocessable();
        $this->assertDatabaseCount('appointment_operations',0);
    }

    public function test_commercial_cannot_check_in_without_mvp_or_view_or_after_closed_or_existing_arrival():void
    {
        $a=$this->lifecycleAppointment($this->commercial,$this->catalog);
        $this->commercial->revokePermissionTo(C::VIEW);
        $this->care($a,'EN_ESPERA')->assertForbidden();
        $this->commercial->givePermissionTo(Permission::findOrCreate(C::VIEW,'web'));
        $this->commercial->revokePermissionTo(C::MVP_ACCESS);
        $this->care($a,'EN_ESPERA')->assertForbidden();
        $this->commercial->givePermissionTo(Permission::findOrCreate(C::MVP_ACCESS,'web'));

        foreach(['ATENDIDO','CANCELADO','NO_ASISTIO','RETIRO','EN_ATENCION'] as $status){
            $closed=$this->lifecycleAppointment($this->commercial,$this->catalog,['estado_cita'=>$status]);
            $this->care($closed,'EN_ESPERA')->assertUnprocessable();
        }
        $already=$this->lifecycleAppointment($this->commercial,$this->catalog,['hora_llegada'=>now()]);
        $this->getJson(route('scheduling.mvp.admission.detail',$already->id))
            ->assertOk()->assertJsonCount(0,'care_actions');
        $this->care($already,'EN_ESPERA')->assertUnprocessable();
        $this->assertDatabaseCount('payments',0);
    }

    public function test_other_read_only_roles_and_commercial_with_update_are_still_restricted():void
    {
        $a=$this->lifecycleAppointment($this->commercial,$this->catalog);
        $this->commercial->givePermissionTo(Permission::findOrCreate(C::UPDATE,'web'));
        $this->care($a,'LLAMANDO')->assertForbidden();
        $this->care($a,'EN_ATENCION')->assertForbidden();
        $this->care($a,'EN_ESPERA')->assertOk();
        $this->care($a->fresh(),'LLAMANDO')->assertForbidden();

        $reception=$this->agendaReader('RECEPCION');
        $this->actingAs($reception);
        $another=$this->lifecycleAppointment($this->commercial,$this->catalog);
        $this->getJson(route('scheduling.mvp.admission.detail',$another->id))
            ->assertOk()->assertJsonCount(0,'care_actions');
        $this->care($another,'EN_ESPERA')->assertForbidden();
    }
}
