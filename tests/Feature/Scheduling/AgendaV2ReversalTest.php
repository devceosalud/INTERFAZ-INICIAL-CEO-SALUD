<?php
namespace Tests\Feature\Scheduling;

use App\Services\Scheduling\AppointmentHistory;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class AgendaV2ReversalTest extends \Tests\Support\AgendaV2TestCase
{
    private function forward($a,string $state)
    {
        return $this->postJson(route('scheduling.mvp.admission.care',$a->id),[
            'state'=>$state,'expected_state'=>$a->estado_cita,'request_key'=>(string)Str::uuid()]);
    }
    private function reverse(array $extra=[],?array $offer=null)
    {
        $offer??=$this->detail()->json('care_reversal');
        return $this->postJson(route('scheduling.mvp.admission.care-reversal',$this->a->id),$extra+[
            'expected_state'=>$offer['from']??$this->a->fresh()->estado_cita,'expected_event_id'=>$offer['event_id']??1,
            'request_key'=>(string)Str::uuid(),'motivo'=>'Corrección ficticia de clic equivocado']);
    }
    public function test_reversal_restores_previous_state_color_and_audits_timestamps_without_money_changes(): void
    {
        $this->admin->givePermissionTo(Permission::findOrCreate(C::UPDATE,'web'));
        $this->select(['radiography'])->assertOk();$this->complete()->assertOk();
        $this->forward($this->a,'EN_ESPERA')->assertOk();
        $this->forward($this->a->fresh(),'LLAMANDO')->assertOk();
        $before=$this->a->fresh();$arrival=$before->hora_llegada;
        $offer=$this->detail()->assertOk()->json('care_reversal');$key=(string)Str::uuid();
        $this->reverse(['request_key'=>$key],$offer)->assertOk()->assertJsonPath('estado_cita','EN_ESPERA');
        $this->reverse(['request_key'=>$key],$offer)->assertOk();
        $a=$this->a->fresh();$this->assertEquals($arrival,$a->hora_llegada);$this->assertNull($a->hora_llamado);
        $event=$a->events()->where('event_type','ADMISION_ESTADO_REVERTIDO')->sole();
        $this->assertNotNull($event->metadata['preserved_timestamps']['hora_llamado']);
        $this->assertSame($offer['event_id'],$event->metadata['reversed_event_id']);
        $this->assertSame($before->only(['precio_programado','total_pagado','saldo_pendiente','estado_agenda']),$a->only(['precio_programado','total_pagado','saldo_pendiente','estado_agenda']));
        $this->assertDatabaseHas('appointment_operational_reviews',['appointment_id'=>$a->id,'status'=>'READY']);
        $this->assertDatabaseCount('payments',0);$this->assertDatabaseCount('vouchers',0);
    }
    public function test_stale_event_or_concurrent_state_rejects_even_if_state_returns_to_same_value(): void
    {
        $this->admin->givePermissionTo(Permission::findOrCreate(C::UPDATE,'web'));
        $this->forward($this->a,'EN_ESPERA')->assertOk();$offer=$this->detail()->json('care_reversal');
        app(AppointmentHistory::class)->record($this->a,'EDICION_HEREDADA',$this->admin->id);
        $this->reverse([],$offer)->assertConflict();
        $this->a->update(['estado_cita'=>'EN_ATENCION']);$this->reverse([],$offer)->assertConflict();
        $this->assertSame(0,$this->a->events()->where('event_type','ADMISION_ESTADO_REVERTIDO')->count());
    }
    public function test_sensitive_states_never_offer_or_accept_simple_reversal_and_commercial_is_denied(): void
    {
        $this->admin->givePermissionTo(Permission::findOrCreate(C::UPDATE,'web'));
        foreach(['ATENDIDO','CANCELADO','RETIRO','NO_ASISTIO'] as $state){\Illuminate\Support\Facades\DB::table('appointments')->where('id',$this->a->id)->update(['estado_cita'=>$state]);
            $this->detail()->assertOk()->assertJsonPath('care_reversal',null);$this->reverse()->assertConflict();}
        $this->a->forceFill(['estado_cita'=>'ATENDIDO','hora_salida'=>now()])->save();$this->reverse()->assertConflict();
        $this->commercial->givePermissionTo(Permission::findOrCreate(C::UPDATE,'web'));
        $this->actingAs($this->commercial);$this->reverse()->assertForbidden();
    }
}
