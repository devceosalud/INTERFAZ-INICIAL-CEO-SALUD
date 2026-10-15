<?php
namespace Tests\Feature\Scheduling;
use App\Models\DoctorSchedule;
use App\Support\Scheduling\DoctorScheduleAccess as Access;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;
class DoctorScheduleSafetyTest extends TestCase
{
    use RefreshDatabase,BuildsAgendaLifecycleData;
    private $actor;private $catalog;
    protected function setUp():void{parent::setUp();config(['scheduling.enabled'=>true,'app.debug'=>false]);$this->actor=$this->scheduleManager('ADMINISTRADOR');$this->catalog=$this->createAppointmentCatalog();$this->actingAs($this->actor);}
    private function updateData(array $extra=[]):array{$s=$this->catalog['schedule'];return array_replace([
        'doctor_schedule_id_edit'=>$s->id,'expected_version'=>$this->scheduleVersion($s->id),'doctor_id_edit'=>$s->doctor_id,
        'fecha_cita_edit'=>$s->fecha_cita,'hora_inicio_edit'=>'08:00','hora_fin_edit'=>'13:00','duracion_edit_cita'=>30],$extra);}
    private function storeData():array{return ['doctor_id'=>$this->catalog['doctor']->id,'scope'=>'single','fecha_cita'=>$this->catalog['schedule']->fecha_cita,'hora_inicio'=>'14:00','hora_fin'=>'16:00','duracion_cita'=>30];}
    public function test_each_approved_manager_role_needs_capability_and_revocation_blocks_existing_identity():void{
        foreach(['ADMINISTRADOR','ADMISION','COMERCIAL'] as $role){
            $u=$this->createUserWithRole($role);$this->actingAs($u);
            $this->postJson('/admissionist/doctor-schedule/store',$this->storeData())->assertForbidden();
            $this->putJson('/admissionist/doctor-schedule/update',$this->updateData())->assertForbidden();
            $this->postJson('/admissionist/doctor-schedule/delete',['id'=>$this->catalog['schedule']->id])->assertForbidden();
            $u->givePermissionTo(Permission::findOrCreate(Access::MANAGE,'web'));
            $this->putJson('/admissionist/doctor-schedule/update',$this->updateData())->assertOk()->assertJsonPath('code',1);
            $u->revokePermissionTo(Access::MANAGE);$before=DoctorSchedule::first()->getAttributes();
            $this->putJson('/admissionist/doctor-schedule/update',$this->updateData(['hora_fin_edit'=>'14:00']))->assertForbidden();
            $this->assertSame($before,DoctorSchedule::first()->getAttributes());
        }
        $this->assertDatabaseCount('doctor_schedule_events',3);$this->assertDatabaseCount('payments',0);
    }
    public function test_other_roles_cannot_manage_schedules_even_with_the_capability():void{
        foreach(['RECEPCION','CAJA','FACTURACION'] as $role){$this->actingAs($this->scheduleManager($role));$this->postJson('/admissionist/doctor-schedule/store',$this->storeData())->assertForbidden();}
        $this->assertDatabaseCount('doctor_schedule_events',0);
    }
    public function test_stale_version_cannot_overwrite_a_saved_change_and_audit_has_actor():void{
        $data=$this->updateData();$this->putJson('/admissionist/doctor-schedule/update',$data)->assertOk()->assertJsonPath('code',1);
        $before=DoctorSchedule::first()->getAttributes();
        $this->putJson('/admissionist/doctor-schedule/update',array_replace($data,['hora_fin_edit'=>'15:00']))->assertConflict();
        $this->assertSame($before,DoctorSchedule::first()->getAttributes());$this->assertSame(1,(int)DoctorSchedule::first()->lock_version);
        $this->assertDatabaseHas('doctor_schedule_events',['actor_user_id'=>$this->actor->id,'event_type'=>'UPDATED']);$this->assertDatabaseCount('doctor_schedule_events',1);
    }
    public function test_confirmed_upcoming_appointment_cannot_be_left_without_schedule():void{
        $a=$this->lifecycleAppointment($this->actor,$this->catalog,['fecha_cita'=>$this->catalog['schedule']->fecha_cita,'hora_cita'=>'08:00:00','estado_agenda'=>'LEGADO']);
        $before=$a->fresh()->getAttributes();$schedule=DoctorSchedule::first()->getAttributes();
        $this->putJson('/admissionist/doctor-schedule/update',$this->updateData(['hora_inicio_edit'=>'09:00']))->assertUnprocessable();
        $this->postJson('/admissionist/doctor-schedule/delete',['id'=>$this->catalog['schedule']->id,'expected_version'=>$this->scheduleVersion($this->catalog['schedule']->id)])->assertUnprocessable();
        $this->assertSame($before,$a->fresh()->getAttributes());$this->assertSame($schedule,DoctorSchedule::first()->getAttributes());$this->assertDatabaseCount('doctor_schedule_events',0);
    }
    public function test_hidden_funded_reservation_blocks_schedule_damage_without_disclosing_identity():void{
        $owner=$this->agendaReader('COMERCIAL');foreach([C::CREATE,C::SUBMIT_PAYMENT] as $p)$owner->givePermissionTo(Permission::findOrCreate($p,'web'));
        $a=$this->lifecycleAppointment($owner,$this->catalog,['fecha_cita'=>$this->catalog['schedule']->fecha_cita,'hora_cita'=>'08:00:00','estado_agenda'=>'PENDIENTE_CONFIRMACION','tipo_agendamiento'=>'REGULAR','economic_source'=>'VOUCHER']);
        $box=\App\Models\Cashier::create(['nombre'=>'QA horario','estado'=>'ACTIVO']);$shift=\App\Models\CashierShift::create(['cashier_id'=>$box->id,'user_id'=>$owner->id,'monto_apertura'=>0,'abierto_en'=>now(),'estado'=>'ABIERTO']);
        \App\Models\VoucherSerie::create(['cashier_id'=>$box->id,'tipo_comprobante'=>'TICKET','serie'=>'QH01','correlativo_actual'=>0,'estado'=>'ACTIVO']);
        $this->actingAs($owner)->postJson('/scheduling-mvp/agenda/appointments/'.$a->id.'/payments',['request_key'=>(string)Str::uuid(),'payment'=>['amount'=>'20','method'=>'EFECTIVO']])->assertOk();
        $before=$a->fresh()->getAttributes();$this->actingAs($this->actor);
        $r=$this->putJson('/admissionist/doctor-schedule/update',$this->updateData(['hora_inicio_edit'=>'09:00']))->assertUnprocessable();
        $this->assertStringNotContainsString($a->patient->numero_identidad,$r->getContent());$this->assertStringNotContainsString('appointment_id',$r->getContent());
        $this->assertSame($before,$a->fresh()->getAttributes());$this->assertDatabaseCount('payments',1);$this->assertEquals(20,\App\Models\Payment::sum('monto'));$this->assertDatabaseCount('doctor_schedule_events',0);
        $a->update(['tipo_agendamiento'=>'ADICIONAL']);$addBefore=$a->fresh()->getAttributes();
        $this->putJson('/admissionist/doctor-schedule/update',$this->updateData(['hora_inicio_edit'=>'09:00']))->assertUnprocessable();
        $this->assertSame($addBefore,$a->fresh()->getAttributes());$owner->givePermissionTo(Permission::findOrCreate(C::CREATE_ADDITIONAL,'web'));
        $this->actingAs($owner)->postJson('/scheduling-mvp/agenda/appointments/'.$a->id.'/payments',['request_key'=>(string)Str::uuid(),'confirm'=>true,'payment'=>['amount'=>'30','method'=>'EFECTIVO']])->assertOk();
        $this->assertSame('CONFIRMADA',$a->fresh()->estado_agenda);$confirmed=$a->fresh()->getAttributes();$this->actingAs($this->actor);
        $this->putJson('/admissionist/doctor-schedule/update',$this->updateData(['hora_inicio_edit'=>'09:00']))->assertUnprocessable();
        $this->assertSame($confirmed,$a->fresh()->getAttributes());$this->assertDatabaseCount('payments',2);$this->assertEquals(50,\App\Models\Payment::sum('monto'));$this->assertDatabaseCount('doctor_schedule_events',0);
    }
}
