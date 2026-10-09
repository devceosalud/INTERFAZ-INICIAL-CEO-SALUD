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

class AppointmentEndingTest extends TestCase
{
    use RefreshDatabase, BuildsAgendaLifecycleData;
    private function fixture(): array
    {
        config(['scheduling.enabled' => true]);
        $actor = $this->agendaReader('COMERCIAL');
        foreach ([C::CANCEL, C::MARK_NO_SHOW, C::WITHDRAW] as $cap) $actor->givePermissionTo(Permission::findOrCreate($cap, 'web'));
        $a = $this->lifecycleAppointment($actor, $this->createAppointmentCatalog(), [
            'estado_agenda' => 'CONFIRMADA', 'fecha_cita' => '2026-10-09', 'hora_cita' => '13:20:00', 'duracion_cita' => 20, 'total_pagado' => 0]);
        $this->actingAs($actor);
        return [$actor, $a];
    }
    private function data(): array { return ['request_key' => (string) Str::uuid(), 'motivo' => 'Error de registro ficticio']; }
    private function endpoint($a, $name): string { return route('scheduling.mvp.agenda.'.$name, $a->id); }

    public function test_cancel_has_its_own_capability_reason_history_and_releases_without_deleting(): void
    {
        [$actor, $a] = $this->fixture(); $payload = $this->data();
        $before = $a->fresh()->only(['patient_id','doctor_id','service_id','fecha_cita','hora_cita','precio_programado','total_pagado']);
        $this->postJson($this->endpoint($a, 'cancel'), ['request_key' => $payload['request_key']])->assertUnprocessable();
        $actor->revokePermissionTo(C::CANCEL);
        $this->postJson($this->endpoint($a, 'cancel'), $payload)->assertForbidden();
        $actor->givePermissionTo(C::CANCEL);
        $this->postJson($this->endpoint($a, 'cancel'), $payload)->assertOk()->assertJsonPath('estado_cita', 'CANCELADO');
        $this->postJson($this->endpoint($a, 'cancel'), $payload)->assertOk();
        $this->assertSame($before, $a->fresh()->only(array_keys($before)));
        $this->assertSame(0, Appointment::consumingRegularSlot()->count());
        $this->assertDatabaseCount('appointments', 1); $this->assertDatabaseCount('appointment_events', 1);
        $this->assertDatabaseHas('appointment_events', ['event_type' => 'CANCELADO', 'actor_user_id' => $actor->id, 'motivo' => $payload['motivo']]);
        $this->assertDatabaseCount('payments', 0);
    }
    public function test_no_show_waits_until_interval_end_and_never_overrides_presence(): void
    {
        [$actor, $a] = $this->fixture();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-09 13:39:59','America/Lima'));
        $this->postJson($this->endpoint($a, 'no-show'), $this->data())->assertStatus(409);
        $this->travelTo(\Carbon\Carbon::parse('2026-10-09 13:40:00','America/Lima'));
        $a->forceFill(['hora_llegada' => now()])->save();
        $this->postJson($this->endpoint($a, 'no-show'), $this->data())->assertStatus(409);
        $a->forceFill(['hora_llegada' => null])->save();
        $this->postJson($this->endpoint($a, 'no-show'), $this->data())->assertOk()->assertJsonPath('estado_cita','NO_ASISTIO');
        $this->assertDatabaseHas('appointment_events',['event_type'=>'NO_ASISTIO','actor_user_id'=>$actor->id]);
        $this->assertSame(0, Appointment::consumingRegularSlot()->count());
        $this->travelBack();
    }
    public function test_money_and_financial_documents_require_review_and_are_never_modified(): void
    {
        [$actor, $a] = $this->fixture();
        $a->update(['total_pagado' => 50]);
        $this->postJson($this->endpoint($a, 'cancel'), $this->data())->assertUnprocessable();
        $a->update(['total_pagado' => 0]);
        $box = \App\Models\Cashier::create(['nombre'=>'Caja ficticia','estado'=>'ACTIVO']);
        $shift = \App\Models\CashierShift::create(['cashier_id'=>$box->id,'user_id'=>$actor->id,'monto_apertura'=>0,'estado'=>'ABIERTO','abierto_en'=>now()]);
        $voucher = \App\Models\Voucher::create(['tipo_comprobante'=>'TICKET','serie'=>'TQA1','correlativo'=>1,'patient_id'=>$a->patient_id,
            'subtotal'=>100,'total'=>100,'estado'=>'PENDIENTE','cashier_shift_id'=>$shift->id,'user_id'=>$actor->id]);
        $voucher->items()->create(['item_type'=>'cita','item_id'=>$a->id,'descripcion'=>'Consulta ficticia','cantidad'=>1,'precio_unitario'=>100,'total'=>100]);
        $voucher->payments()->create(['metodo_pago'=>'EFECTIVO','monto'=>50,'user_id'=>$actor->id,'cashier_shift_id'=>$shift->id]);
        $before = $voucher->fresh()->getAttributes();
        $this->postJson($this->endpoint($a, 'cancel'), $this->data())->assertUnprocessable();
        $this->assertSame($before, $voucher->fresh()->getAttributes());
        $this->assertSame('PROGRAMADO', $a->fresh()->estado_cita);
        $this->assertDatabaseCount('payments',1); $this->assertEquals(50,\App\Models\Payment::sum('monto'));
        foreach (['cash_movements','appointment_events','appointment_credit_applications','appointment_refund_requests'] as $table) {
            $this->assertSame(0, DB::table($table)->count());
        }
    }
    public function test_hidden_id_equals_missing_and_withdraw_presence_rule_is_unchanged(): void
    {
        [$actor, $a] = $this->fixture(); $a->update(['estado_agenda' => 'PENDIENTE_CONFIRMACION']);
        $other = $this->agendaReader(); $other->givePermissionTo(Permission::findOrCreate(C::CANCEL,'web'));
        $this->actingAs($other)->postJson($this->endpoint($a,'cancel'),$this->data())->assertNotFound();
        $this->postJson(route('scheduling.mvp.agenda.cancel',99999),$this->data())->assertNotFound();
        $this->actingAs($actor)->postJson($this->endpoint($a,'withdraw'), $this->data()+['requested_action'=>'PENDIENTE'])->assertUnprocessable()->assertJsonValidationErrors('was_present');
    }
    public function test_legacy_endpoints_cannot_bypass_closing_capability_presence_money_or_reopen_history(): void
    {
        [$actor,$a]=$this->fixture(); $payload=['appointment_id'=>$a->id,'estado_cita'=>'CANCELADO',
            'doctor_id_edit'=>$a->doctor_id,'service_id_edit'=>1,'fecha_cita_edit'=>$a->fecha_cita,'hora_cita_edit'=>'13:20'];
        foreach (['/admissionist/appointment/update','/admissionist/schedule/update'] as $url) {
            $this->postJson($url,$payload)->assertUnprocessable()->assertJsonValidationErrors('estado_cita');
        }
        $this->postJson($this->endpoint($a,'cancel'),$this->data())->assertOk();
        $payload['estado_cita']='PROGRAMADO';
        foreach (['/admissionist/appointment/update','/admissionist/schedule/update'] as $url) {
            $this->postJson($url,$payload)->assertUnprocessable();
        }
        $this->assertSame('CANCELADO',$a->fresh()->estado_cita);
        $this->assertDatabaseCount('appointment_events',1);
    }
}
