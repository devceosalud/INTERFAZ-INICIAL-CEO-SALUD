<?php
namespace Tests\Feature\Scheduling;

use App\Models\Appointment;
use App\Support\Scheduling\MvpPermissionBootstrap as B;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

class MvpAllRolesFlowTest extends TestCase
{
    use RefreshDatabase,BuildsAgendaLifecycleData;
    public function rolesAndEntries(): array
    {
        $cases=[];foreach(['ADMINISTRADOR','ADMISION','COMERCIAL','RECEPCION','CAJA','FACTURACION','OTRO_VIGENTE'] as $r){foreach(['legacy','operational'] as $e){$cases[]=[$r,$e];}}return $cases;
    }
    /** @dataProvider rolesAndEntries */
    public function test_existing_and_unknown_mvp_profiles_complete_the_normal_flow_without_financial_bypass(string $role,string $entry): void
    {
        config(['scheduling.enabled'=>true,'scheduling.pilot_payment_without_manual_cash_shift'=>true,'app.debug'=>false]);Storage::fake('local');
        foreach(B::REQUIRED as $p){Permission::findOrCreate($p,'web');}
        $actor=$this->createUserWithRole($role);$b=app(B::class);$b->apply([],$b->plan()['plan_hash'],'QA-FLOW');$this->actingAs($actor->fresh());
        $catalog=$this->createAppointmentCatalog();$catalog['rate']->update(['nombre'=>'TARIFA ESTANDAR','tipo_tarifa'=>'MONTO_FIJO']);
        $this->get('/dashboard')->assertOk();$this->get('/scheduling-mvp/agenda')->assertOk()->assertSee('id="agenda-draft-save"',false);
        $this->get('/admissionist/appointment')->assertOk();
        $patientId=$this->postJson('/patients',['tipo_identificacion'=>'DNI','numero_identidad'=>'70001313','nombre'=>'QA MVP',
            'apellido_paterno'=>'FICTICIO','apellido_materno'=>'LOCAL','genero'=>'HOMBRE','telefono_prefijo'=>'+51','telefono_numero'=>'999000131'])->assertCreated()->json('patient.patient_id');
        $patient=\App\Models\Patient::findOrFail($patientId);
        $history=$this->lifecycleAppointment($actor,$catalog,['estado_agenda'=>'LEGADO','estado_cita'=>'ATENDIDO',
            'fecha_cita'=>now()->subDays(20)->toDateString(),'precio_programado'=>137],$patient);$snapshot=$history->fresh()->getAttributes();
        $payload=['request_key'=>(string)Str::uuid(),'mode'=>'RESERVE','patient_id'=>$patientId,'doctor_id'=>$catalog['doctor']->id,
            'service_id'=>$entry==='legacy'?$catalog['doctorService']->id:$catalog['service']->id,'additional_rate_id'=>$catalog['rate']->id,
            'fecha_cita'=>$catalog['schedule']->fecha_cita,'hora_cita'=>'09:00','booking_type'=>'REGULAR','duracion_cita'=>30];
        if($entry==='legacy'){$payload+=['total_pagado'=>'0','metodo_pago'=>'EFECTIVO','precio_programado'=>'100.00'];}
        $url=$entry==='legacy'?'/admissionist/appointment/store':'/scheduling-mvp/agenda/registrations';
        $quoteUrl=$entry==='legacy'?'/api/appointment/calculated':'/scheduling-mvp/agenda/quote';
        $this->postJson($quoteUrl,$payload)->assertOk()->assertJsonPath('tipo','PRIMERA_CONSULTA');
        $this->postJson($url,array_replace($payload,['precio_programado'=>'0.01']))->assertUnprocessable();
        $result=$this->postJson($url,$payload)->assertSuccessful();$id=$entry==='legacy'?$result->json('appointment_id'):$result->json('appointment.appointment_id');
        $this->assertSame('PENDIENTE_CONFIRMACION',Appointment::findOrFail($id)->estado_agenda);$this->assertDatabaseCount('payments',0);
        $this->postJson('/scheduling-mvp/agenda/appointments/'.$id.'/payments',['request_key'=>(string)Str::uuid(),
            'confirm'=>true,'payment'=>['amount'=>'49.00','method'=>'EFECTIVO']])->assertUnprocessable();$this->assertDatabaseCount('payments',0);
        $payment=['request_key'=>(string)Str::uuid(),'payment'=>['amount'=>'50.00','method'=>'YAPE','operation'=>'QA-ONLY-131','origin'=>'YAPE']];
        $this->postJson('/scheduling-mvp/agenda/appointments/'.$id.'/payments',$payment)->assertOk();
        $this->postJson('/scheduling-mvp/agenda/appointments/'.$id.'/payments',$payment)->assertOk();$this->assertDatabaseCount('payments',1);
        $this->assertSame('PENDIENTE_CONFIRMACION',Appointment::findOrFail($id)->estado_agenda);
        $this->postJson('/scheduling-mvp/agenda/appointments/'.$id.'/payments',array_replace($payment,['request_key'=>(string)Str::uuid()]))->assertUnprocessable();$this->assertDatabaseCount('payments',1);
        $this->postJson('/scheduling-mvp/agenda/appointments/'.$id.'/payments',['request_key'=>(string)Str::uuid(),'confirm'=>true])->assertOk();
        $this->assertSame('CONFIRMADA',Appointment::findOrFail($id)->estado_agenda);
        $this->getJson('/scheduling-mvp/agenda/appointments/'.$id.'/economy')->assertOk();
        $this->postJson('/admissionist/appointment/update',['appointment_id'=>$id,'estado_cita'=>'CONFIRMADO'])->assertOk();
        $this->postJson('/admissionist/schedule/update',['appointment_id'=>$id,'estado_cita'=>'CONFIRMADO',
            'doctor_id_edit'=>$catalog['doctor']->id,'service_id_edit'=>$catalog['doctorService']->id,
            'fecha_cita_edit'=>$catalog['schedule']->fecha_cita,'hora_cita_edit'=>'09:30','motivo_consulta_edit'=>'QA SIN DATOS REALES'])->assertOk();
        $this->patchJson('/scheduling-mvp/agenda/appointments/'.$id.'/reschedule',['fecha_cita'=>$catalog['schedule']->fecha_cita,
            'hora_cita'=>'10:00','expected_fecha_cita'=>$catalog['schedule']->fecha_cita,'expected_hora_cita'=>'09:30'])->assertOk();
        $this->putJson('/patients/'.$patientId,['tipo_identificacion'=>'DNI','numero_identidad'=>'70001313','nombre'=>'QA EDITADO',
            'apellido_paterno'=>'FICTICIO','apellido_materno'=>'LOCAL','genero'=>'HOMBRE'])->assertOk();
        foreach(['dia','semana','mes'] as $view){$this->getJson('/scheduling-mvp/agenda/feed?vista='.$view.'&fecha='.$catalog['schedule']->fecha_cita)->assertOk();}
        $this->postJson($url,array_replace($payload,['request_key'=>(string)Str::uuid(),'hora_cita'=>'11:00','es_exonerado'=>true,'autorizado_por'=>'QA']))->assertForbidden();
        $this->postJson('/scheduling-mvp/agenda/appointments/'.$id.'/cancel',['request_key'=>(string)Str::uuid(),'motivo'=>'QA'])->assertForbidden();
        $this->assertDatabaseCount('payments',1);$this->assertDatabaseCount('vouchers',1);
        $this->assertEquals(50,\App\Models\Payment::sum('monto'));$this->assertEquals(100,Appointment::findOrFail($id)->precio_programado);
        $this->assertSame($snapshot,$history->fresh()->getAttributes());$this->assertFalse($actor->fresh()->can(C::APPROVE_ZERO_COST));
    }
    public function test_granted_profile_cannot_see_or_edit_another_workers_private_reservation(): void
    {
        config(['scheduling.enabled'=>true,'app.debug'=>false]);Storage::fake('local');
        foreach(B::REQUIRED as $p){Permission::findOrCreate($p,'web');}
        $owner=$this->createUserWithRole('ADMISION');$reader=$this->createUserWithRole('OTRO_VIGENTE');
        $b=app(B::class);$b->apply([],$b->plan()['plan_hash'],'QA-PRIVACY');$catalog=$this->createAppointmentCatalog();
        $a=$this->lifecycleAppointment($owner,$catalog,['estado_agenda'=>'PENDIENTE_CONFIRMACION']);$before=$a->fresh()->getAttributes();
        $this->actingAs($reader);$hidden=$this->getJson('/scheduling-mvp/agenda/appointments/'.$a->id.'/economy')->assertNotFound();
        $missing=$this->getJson('/scheduling-mvp/agenda/appointments/999999/economy')->assertNotFound();$this->assertSame($missing->json(),$hidden->json());
        $this->postJson('/admissionist/appointment/update',['appointment_id'=>$a->id,'estado_cita'=>'CONFIRMADO'])->assertNotFound();
        $this->assertSame($before,$a->fresh()->getAttributes());
    }
    public function test_role_without_setup_and_reader_without_write_caps_cannot_bypass_access(): void
    {
        config(['scheduling.enabled'=>true]);$u=$this->createUserWithRole('OTRO_VIGENTE');$this->actingAs($u);
        $this->get('/dashboard')->assertForbidden();$this->postJson('/patients',[])->assertForbidden();$this->postJson('/admissionist/appointment/store',[])->assertForbidden();
        foreach([C::MVP_ACCESS,C::VIEW] as $p){$u->givePermissionTo(Permission::findOrCreate($p,'web'));}
        $this->get('/dashboard')->assertOk();$this->postJson('/patients',[])->assertForbidden();$this->postJson('/admissionist/appointment/store',[])->assertForbidden();
        $this->get('/admin/role')->assertForbidden();
    }
}
