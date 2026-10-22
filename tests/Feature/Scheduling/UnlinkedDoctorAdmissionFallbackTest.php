<?php

namespace Tests\Feature\Scheduling;

use App\Models\{Appointment,User,UserAccountEvent};
use App\Services\Scheduling\UnlinkedDoctorAdmissionFallback as F;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsRescheduleVisualData;
use Tests\TestCase;

class UnlinkedDoctorAdmissionFallbackTest extends TestCase
{
    use RefreshDatabase, BuildsRescheduleVisualData;

    private User $admission;
    private Appointment $appointment;

    protected function setUp(): void
    {
        parent::setUp();
        $fixture=$this->rescheduleVisualFixture();
        $this->admission=$fixture['actor'];
        $this->appointment=$fixture['a'];
        $this->admission->givePermissionTo(Permission::findOrCreate(C::UPDATE,'web'));
        config(['scheduling.unlinked_doctor_admission_fallback'=>true]);
        $this->actingAs($this->admission);
    }

    protected function tearDown():void {\Carbon\Carbon::setTestNow(); parent::tearDown();}

    private function care(string $state,array $extra=[],?string $key=null)
    {
        return $this->postJson(route('scheduling.mvp.admission.care',$this->appointment->id),array_replace([
            'state'=>$state,'expected_state'=>$this->appointment->fresh()->estado_cita,
            'request_key'=>$key??(string)Str::uuid(),
        ],$extra));
    }

    private function arrival():void {$this->care('EN_ESPERA')->assertOk();}
    private function contingency(string $state,array $extra=[],?string $key=null)
    {
        return $this->care($state,array_replace(['confirmed'=>true,'motivo'=>'Contingencia FICTICIA autorizada'], $extra),$key);
    }

    public function test_unlinked_doctor_can_be_started_and_finished_by_admission_with_independent_audit():void
    {
        $money=$this->appointment->only(['precio_programado','total_pagado','saldo_pendiente','estado_pagado','estado_agenda']);
        $payments=DB::table('payments')->get()->toArray();
        $this->arrival();
        $this->getJson(route('scheduling.mvp.admission.detail',$this->appointment->id))
            ->assertOk()->assertJsonPath('care_fallback',true)
            ->assertJsonPath('care_actions.1','EN_ATENCION');

        $id=(string)Str::uuid();
        $this->contingency('EN_ATENCION',[],$id)->assertOk();
        $this->contingency('EN_ATENCION',['expected_state'=>'EN_ESPERA'],$id)->assertOk();
        $this->assertNotNull($this->appointment->fresh()->hora_atencion);
        $this->contingency('ATENDIDO')->assertOk();
        $this->assertNotNull($this->appointment->fresh()->hora_atendido);
        $this->assertNull($this->appointment->fresh()->hora_salida);
        $this->assertEquals($money,$this->appointment->fresh()->only(array_keys($money)));
        $this->assertEquals($payments,DB::table('payments')->get()->toArray());
        $this->assertSame(1,$this->appointment->events()->where('event_type',F::START)->count());
        $this->assertSame(1,$this->appointment->events()->where('event_type',F::FINISH)->count());
        foreach([F::START,F::FINISH] as $eventType){
            $e=$this->appointment->events()->where('event_type',$eventType)->firstOrFail();
            $this->assertSame($this->admission->id,$e->actor_user_id);
            $this->assertSame(false,$e->metadata['physician_signed']);
            $this->assertSame('ADMISION_FALLBACK_UNLINKED_DOCTOR_V1',$e->metadata['source']);
            $this->assertNotEmpty($e->motivo);
        }
    }

    public function test_contingency_needs_configuration_reason_confirmation_and_arrival():void
    {
        $this->contingency('EN_ATENCION')->assertUnprocessable();
        $this->arrival();
        $this->contingency('EN_ATENCION',['motivo'=>''])->assertUnprocessable();
        $this->contingency('EN_ATENCION',['confirmed'=>false])->assertUnprocessable();
        config(['scheduling.unlinked_doctor_admission_fallback'=>false]);
        $this->getJson(route('scheduling.mvp.admission.detail',$this->appointment->id))
            ->assertJsonPath('care_fallback',false);
        $this->contingency('EN_ATENCION')->assertForbidden();
        $this->assertSame(0,$this->appointment->events()->where('event_type',F::START)->count());
    }

    public function test_existing_linked_account_closes_fallback_even_when_inactive_and_without_medico_role():void
    {
        $this->arrival();
        $u=$this->createUser(['is_active'=>false]);$u->forceFill(['doctor_id'=>$this->appointment->doctor_id])->save();
        $this->getJson(route('scheduling.mvp.admission.detail',$this->appointment->id))
            ->assertJsonPath('care_fallback',false);
        $this->contingency('EN_ATENCION')->assertForbidden();
        $u->forceFill(['doctor_id'=>null])->save();
        UserAccountEvent::create([
            'target_user_id'=>$u->id,'actor_user_id'=>$this->admission->id,
            'event_type'=>'DOCTOR_LINK_CHANGED','request_key'=>(string)Str::uuid(),
            'payload_hash'=>hash('sha256','fictitious-link'),
            'metadata'=>['after'=>['doctor_id'=>(int)$this->appointment->doctor_id]]
        ]);
        $this->contingency('EN_ATENCION')->assertForbidden();
        $this->assertSame(0,$this->appointment->events()->where('event_type',F::START)->count());
    }

    public function test_role_and_effective_capability_are_required_for_the_fallback():void
    {
        $this->arrival();
        $this->admission->syncRoles(\Spatie\Permission\Models\Role::findOrCreate('COMERCIAL','web'));
        $this->contingency('EN_ATENCION')->assertForbidden();
        $this->admission->syncRoles(\Spatie\Permission\Models\Role::findOrCreate('ADMISION','web'));
        $this->admission->revokePermissionTo(C::UPDATE);
        $this->contingency('EN_ATENCION')->assertForbidden();
        $this->assertSame(0,$this->appointment->events()->where('event_type',F::START)->count());
    }

    public function test_contingency_never_allows_teleconsultation_or_private_pending_booking():void
    {
        $this->appointment->update(['attendance_mode'=>'TELECONSULTA']);
        $this->getJson(route('scheduling.mvp.admission.detail',$this->appointment->id))
            ->assertJsonPath('care_fallback',false);
        $this->contingency('EN_ATENCION')->assertForbidden();
        $this->appointment->update(['attendance_mode'=>'PRESENCIAL','estado_agenda'=>'PENDIENTE_CONFIRMACION']);
        $this->contingency('EN_ATENCION')->assertForbidden();
    }

    public function test_pending_medical_reevaluation_cannot_be_finished_by_contingency():void
    {
        $this->arrival();$this->contingency('EN_ATENCION')->assertOk();
        $this->appointment->events()->create([
            'event_type'=>\App\Services\Scheduling\AppointmentReevaluationService::INDICATED,
            'actor_user_id'=>$this->admission->id,'occurred_at'=>now(),
            'metadata'=>['version'=>1,'source'=>'MEDICAL_RETURN_SAME_APPOINTMENT_V1',
                'doctor_id'=>(int)$this->appointment->doctor_id],
        ]);
        $this->contingency('ATENDIDO')->assertUnprocessable();
        $this->assertSame('EN_ATENCION',$this->appointment->fresh()->estado_cita);
        $this->assertNull($this->appointment->fresh()->hora_atendido);
    }
}
