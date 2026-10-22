<?php

namespace Tests\Feature\Scheduling;

use App\Models\Appointment;
use App\Models\User;
use App\Services\Scheduling\AppointmentReevaluationService;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

class JulioAdmissionReevaluationTest extends TestCase
{
    use RefreshDatabase, BuildsAgendaLifecycleData;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scheduling.enabled'=>true,'app.debug'=>false]);
    }

    private function doctorLinkColumn(): void
    {
        // The existing production DB has users.doctor_id. The old main branch
        // lacks that historical migration, so reproduce the real schema only
        // in this disposable test database (no production migrations).
        if (!Schema::hasColumn('users','doctor_id')) {
            // SQLite can roll back test-only DDL inside RefreshDatabase.
            // MariaDB DDL implicitly commits and destroys PHPUnit savepoints:
            // do not alter its schema mid-test. The production link was
            // verified separately; the MariaDB authorization-negative and
            // redirect coverage still run without schema alterations.
            if (\Illuminate\Support\Facades\DB::connection()->getDriverName() !== 'sqlite') {
                $this->markTestSkipped('La migración heredada users.doctor_id no figura en main; no alterar MariaDB de pruebas dentro de una transacción.');
            }
            Schema::table('users',static function(Blueprint $table){
                $table->unsignedBigInteger('doctor_id')->nullable();
            });
        }
    }

    private function physician(): array
    {
        $this->doctorLinkColumn();
        $u=$this->agendaReader('ADMINISTRADOR');
        Role::firstOrCreate(['name'=>'MEDICO','guard_name'=>'web']);
        $u->assignRole('MEDICO');
        $u->givePermissionTo(Permission::findOrCreate(C::UPDATE,'web'));
        $catalog=$this->createAppointmentCatalog();
        $u->forceFill(['doctor_id'=>$catalog['doctor']->id])->save();
        $this->actingAs($u);
        $a=$this->lifecycleAppointment($u,$catalog,[
            'fecha_cita'=>now('America/Lima')->toDateString(),
            'estado_cita'=>'EN_ATENCION','estado_agenda'=>'CONFIRMADA',
        ]);
        $a->forceFill(['hora_llegada'=>now(),'hora_atencion'=>now()])->save();
        return [$u,$a];
    }

    private function indicate(Appointment $a,string $action='indicate',array $extra=[])
    {
        return $this->postJson(route('scheduling.mvp.admission.physician-reevaluation',
            ['appointmentId'=>$a->id,'action'=>$action]),array_merge([
                'expected_state'=>'EN_ATENCION','confirmed'=>true,'request_key'=>(string)Str::uuid()
            ],$extra));
    }

    private function finish(Appointment $a)
    {
        return $this->postJson(route('scheduling.mvp.admission.care',$a->id),[
            'state'=>'ATENDIDO','expected_state'=>'EN_ATENCION','request_key'=>(string)Str::uuid()
        ]);
    }

    public function test_linked_physician_indicates_on_same_admission_and_finishes_after_manual_return():void
    {
        [$u,$a]=$this->physician();
        $this->getJson(route('scheduling.mvp.admission.detail',$a->id))
            ->assertOk()->assertJsonPath('care_actions.0','INDICAR_REEVALUACION')
            ->assertJsonPath('care_actions.1','ATENDIDO');
        $before=$a->fresh()->only(['precio_programado','total_pagado','saldo_pendiente','estado_pagado']);
        $key=(string)Str::uuid();
        $this->indicate($a,'indicate',['request_key'=>$key])->assertOk();
        $this->indicate($a,'indicate',['request_key'=>$key])->assertOk();
        $a=$a->fresh();
        $this->assertSame('EN_ATENCION',$a->estado_cita);
        $this->assertSame(1,$a->events()->where('event_type',AppointmentReevaluationService::MEDICAL_INDICATED)->count());
        $this->getJson(route('scheduling.mvp.admission.detail',$a->id))
            ->assertJsonPath('medical_reevaluation.pending',true)
            ->assertJsonPath('care_actions.0','RETIRAR_REEVALUACION');
        $this->postJson(route('scheduling.mvp.admission.feed'),[
            'fecha'=>now('America/Lima')->toDateString(),'only_pending_reevaluations'=>true
        ])->assertOk()->assertJsonPath('kpis.reevaluaciones_pendientes',1);
        $this->finish($a)->assertUnprocessable();
        $this->patchJson(route('scheduling.mvp.admission.manual-reevaluation',$a->id),[
            'marked'=>true,'expected_marked'=>false,'expected_version'=>0,
            'request_key'=>(string)Str::uuid()
        ])->assertOk();
        $this->getJson(route('scheduling.mvp.admission.detail',$a->id))
            ->assertJsonPath('medical_reevaluation.ready',true)
            ->assertJsonPath('care_actions.0','ATENDIDO');
        $this->finish($a)->assertOk()->assertJsonPath('estado_cita','ATENDIDO');
        $fresh=$a->fresh();
        $this->assertNull($fresh->hora_salida);
        $this->assertNotNull($fresh->hora_atendido);
        $this->assertSame($before,$fresh->only(array_keys($before)));
        $this->assertDatabaseCount('payments',0);
    }

    public function test_other_administrator_or_different_linked_physician_cannot_sign_julios_indication():void
    {
        [$u,$a]=$this->physician();
        $other=$this->agendaReader('ADMINISTRADOR');
        $other->givePermissionTo(Permission::findOrCreate(C::UPDATE,'web'));
        $this->actingAs($other);
        $this->indicate($a)->assertForbidden();
        $this->actingAs($u);
        $u->forceFill(['doctor_id'=>999])->save();
        $this->indicate($a)->assertForbidden();
        $this->assertDatabaseMissing('appointment_events',[
            'appointment_id'=>$a->id,'event_type'=>AppointmentReevaluationService::MEDICAL_INDICATED
        ]);
    }

    public function test_clinical_indication_fails_closed_without_a_verified_existing_link():void
    {
        $u=$this->agendaReader('ADMINISTRADOR');
        Role::firstOrCreate(['name'=>'MEDICO','guard_name'=>'web']);
        $u->assignRole('MEDICO');
        $catalog=$this->createAppointmentCatalog();
        $a=$this->lifecycleAppointment($u,$catalog,[
            'estado_cita'=>'EN_ATENCION','estado_agenda'=>'CONFIRMADA',
        ]);
        $a->forceFill(['hora_llegada'=>now(),'hora_atencion'=>now()])->save();
        $this->actingAs($u);
        $this->indicate($a)->assertForbidden();
        $this->assertDatabaseMissing('appointment_events',[
            'appointment_id'=>$a->id,
            'event_type'=>AppointmentReevaluationService::MEDICAL_INDICATED,
        ]);
    }

    public function test_only_account_two_redirects_to_admission_at_login():void
    {
        $this->agendaReader('ADMINISTRADOR');
        $julio=$this->agendaReader('ADMINISTRADOR');
        $this->assertSame(2,(int)$julio->id);
        Role::firstOrCreate(['name'=>'MEDICO','guard_name'=>'web']);
        $julio->assignRole('MEDICO');
        $julio->forceFill(['name'=>'GERENTE JULIO QUIROZ',
            'password'=>Hash::make('Test-Only-Complex-Password')])->save();
        $this->post('/admin/SingIn',['email'=>$julio->email,'password'=>'Test-Only-Complex-Password'])
            ->assertRedirect(route('scheduling.mvp.admission'));
        auth()->logout();
        $other=$this->agendaReader('ADMINISTRADOR');
        $other->forceFill(['password'=>Hash::make('Another-Test-Only-Password')])->save();
        $this->post('/admin/SingIn',['email'=>$other->email,'password'=>'Another-Test-Only-Password'])
            ->assertRedirect(route('admin.dashboard.index'));
    }
}
