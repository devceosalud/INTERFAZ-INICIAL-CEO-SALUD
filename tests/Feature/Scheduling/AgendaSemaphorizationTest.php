<?php
namespace Tests\Feature\Scheduling;

use App\Models\Appointment;
use App\Services\Scheduling\DoctorAvailabilityService;
use App\Support\Scheduling\AgendaAppointmentPresentation as Presentation;
use App\Support\Scheduling\AgendaQuery;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

class AgendaSemaphorizationTest extends TestCase
{
    use RefreshDatabase,BuildsAgendaLifecycleData;
    private $actor,$catalog,$patient;
    protected function setUp():void{
        parent::setUp();config(['scheduling.enabled'=>true,'scheduling.pilot_payment_without_manual_cash_shift'=>true]);
        $this->actor=$this->agendaReader();foreach([C::CREATE,C::UPDATE,C::SUBMIT_PAYMENT] as $p)$this->actor->givePermissionTo(Permission::findOrCreate($p,'web'));
        $this->catalog=$this->createAppointmentCatalog();$this->catalog['schedule']->update(['hora_fin'=>'18:00']);$this->catalog['rate']->update(['nombre'=>'TARIFA ESTANDAR','tipo_tarifa'=>'MONTO_FIJO']);
        $this->patient=$this->createPatient($this->actor,['nombre'=>'PACIENTE_PRIVADO_SEMAFORO_QLZ']);$this->actingAs($this->actor);
    }
    private function payload(array $changes=[]):array{return array_replace(['request_key'=>(string)Str::uuid(),'mode'=>'RESERVE','booking_type'=>'REGULAR',
        'patient_id'=>$this->patient->id,'doctor_id'=>$this->catalog['doctor']->id,'service_id'=>$this->catalog['service']->id,
        'fecha_cita'=>$this->catalog['schedule']->fecha_cita,'hora_cita'=>'09:00','duracion_cita'=>30],$changes);}
    private function feed(string $view='dia'){return $this->getJson('/scheduling-mvp/agenda/feed?'.http_build_query(['vista'=>$view,'fecha'=>$this->catalog['schedule']->fecha_cita,'doctor_id'=>[$this->catalog['doctor']->id]]));}
    private function snapshot():array{$r=[];foreach(['appointments','payments','vouchers','voucher_items','appointment_operations','appointment_events'] as $t)$r[$t]=DB::table($t)->orderBy('id')->get()->toJson();return $r;}
    public static function singleSignals():array{return [
        ['PENDIENTE_CONFIRMACION',[],['paid_cents'=>0],'RESERVA_SIN_PAGO'],
        ['PENDIENTE_CONFIRMACION',[],['paid_cents'=>2000],'PAGO_REAL'],
        ['CONFIRMADA',[],['paid_cents'=>10000],'PAGO_REAL'],
        ['PENDIENTE_CONFIRMACION',[],['paid_cents'=>10000,'authority'=>'LEGACY_SNAPSHOT'],'PENDIENTE_CONFIRMACION'],
        ['PENDIENTE_CONFIRMACION',[],['paid_cents'=>10000,'authority'=>'UNALLOCATED','allocation_required'=>true],'PENDIENTE_CONFIRMACION'],
        ['PENDIENTE_CONFIRMACION',[],['paid_cents'=>0,'credit_cents'=>10000],'RESERVA_SIN_PAGO'],
        ['PENDIENTE_CONFIRMACION',['is_postoperative'=>true],['paid_cents'=>0],'POSTOPERATORIO'],
        ['PENDIENTE_CONFIRMACION',['has_diabetic_wound'=>true],['paid_cents'=>0],'HERIDA'],
        ['CONFIRMADA',['estado_cita'=>'EN_ESPERA','has_diabetic_wound'=>true,'is_postoperative'=>true],['paid_cents'=>2000],'PRESENTE'],
        ['CONFIRMADA',['estado_cita'=>'CANCELADO','hora_llegada'=>'2026-10-15 09:00:00','is_postoperative'=>true],['paid_cents'=>10000],'DESACTIVADA'],
        ['CONFIRMADA',['estado_cita'=>'RETIRO','hora_llegada'=>'2026-10-15 09:00:00','has_diabetic_wound'=>true],['paid_cents'=>2000],'DESACTIVADA'],
    ];}
    /** @dataProvider singleSignals */
    public function test_central_signals_use_real_money_and_explicit_flags(string $agenda,array $attributes,array $position,string $key):void{
        $a=new Appointment();$a->forceFill(array_replace(['estado_agenda'=>$agenda,'estado_cita'=>'PROGRAMADO','tipo_agendamiento'=>'REGULAR','estado_pagado'=>'PAGADO','total_pagado'=>999],$attributes));
        $before=$a->getAttributes();$p=Presentation::of($a,array_replace(['authority'=>'PAYMENTS','paid_cents'=>0,'allocation_required'=>false],$position));
        $this->assertSame($key,$p['clave']);$this->assertSame($before,$a->getAttributes());$this->assertFalse($p['strikethrough']);
        if($key==='PRESENTE'){$this->assertContains('POSTOPERATORIO',array_column($p['badges'],'clave'));$this->assertContains('HERIDA',array_column($p['badges'],'clave'));}
    }
    public function test_undefined_compound_priority_preserves_previous_base_and_all_labels_without_inventing_hierarchy():void{
        $a=new Appointment(['estado_agenda'=>'PENDIENTE_CONFIRMACION','is_postoperative'=>true,'has_diabetic_wound'=>true]);
        $p=Presentation::of($a,['authority'=>'PAYMENTS','paid_cents'=>2000]);
        $this->assertTrue($p['visual_priority_pending']);$this->assertSame('PENDIENTE_CONFIRMACION',$p['clave']);
        $this->assertSame(['PAGO_REAL','POSTOPERATORIO','HERIDA'],array_column($p['badges'],'clave'));
    }
    public function test_attended_does_not_imply_departure_or_a_reevaluation_decision():void{
        $a=new Appointment();$a->forceFill(['estado_cita'=>'ATENDIDO','hora_llegada'=>now(),'hora_atendido'=>now()]);
        $p=Presentation::of($a,['authority'=>'PAYMENTS','paid_cents'=>0]);$this->assertSame('PRESENTE',$p['clave']);$this->assertFalse($p['strikethrough']);
        $this->assertArrayNotHasKey('reevaluation',$p);$this->assertDatabaseCount('payments',0);
    }
    public function test_partial_real_payment_is_yellow_without_confirming_and_feed_does_not_write():void{
        $id=$this->postJson('/scheduling-mvp/agenda/registrations',$this->payload(['payment'=>['amount'=>'20','method'=>'EFECTIVO']]))->assertCreated()->json('appointment.appointment_id');
        $before=$this->snapshot();$event=collect($this->feed()->assertOk()->json('eventos'))->firstWhere('id','cita-'.$id);
        $this->assertSame('PAGO_REAL',$event['extendedProps']['leyenda']);$this->assertTrue($event['extendedProps']['presentation']['paid_real']);
        $this->assertSame('PENDIENTE_CONFIRMACION',$event['extendedProps']['estado_agenda']);$this->assertDatabaseCount('payments',1);
        $this->feed('semana')->assertOk();$month=$this->feed('mes')->assertOk();$this->assertStringNotContainsString($this->patient->nombre,$month->getContent());
        $this->assertSame($before,$this->snapshot());
    }
    public function test_admin_followup_remains_inside_visibility_and_never_changes_capacity():void{
        $date=$this->catalog['schedule']->fecha_cita;$other=$this->agendaReader();$admin=$this->agendaReader('ADMINISTRADOR');
        $unpaid=$this->postJson('/scheduling-mvp/agenda/registrations',$this->payload())->assertCreated()->json('appointment.appointment_id');
        $paid=$this->postJson('/scheduling-mvp/agenda/registrations',$this->payload(['hora_cita'=>'10:00','mode'=>'CONFIRM','payment'=>['amount'=>'50','method'=>'EFECTIVO']]))->assertCreated()->json('appointment.appointment_id');
        $privatePaid=$this->postJson('/scheduling-mvp/agenda/registrations',$this->payload(['hora_cita'=>'11:00','payment'=>['amount'=>'20','method'=>'EFECTIVO']]))->assertCreated()->json('appointment.appointment_id');
        $present=$this->lifecycleAppointment($other,$this->catalog,['fecha_cita'=>$date,'hora_cita'=>'12:00']);$present->forceFill(['hora_llegada'=>now()])->save();
        $cancel=$this->lifecycleAppointment($other,$this->catalog,['fecha_cita'=>$date,'hora_cita'=>'13:00','estado_cita'=>'CANCELADO']);
        $snapshot=$this->lifecycleAppointment($other,$this->catalog,['fecha_cita'=>$date,'hora_cita'=>'14:00','estado_pagado'=>'PAGADO','total_pagado'=>100,'economic_source'=>'LEGACY']);
        $query=new AgendaQuery([$this->catalog['doctor']->id],\Carbon\Carbon::parse($date),\Carbon\Carbon::parse($date));
        $before=app(DoctorAvailabilityService::class)->forRange($query)->map->toArray()->all();$rows=$this->snapshot();
        $this->actingAs($admin);$r=$this->feed()->assertOk()->assertJsonPath('vista_operativa','SEGUIMIENTO_ADMIN');
        $ids=collect($r->json('eventos'))->pluck('extendedProps.appointment_id')->filter()->all();
        $this->assertEqualsCanonicalizing([$paid,$present->id,$cancel->id],$ids);$this->assertNotContains($unpaid,$ids);$this->assertNotContains($privatePaid,$ids);$this->assertNotContains($snapshot->id,$ids);
        $event=collect($r->json('eventos'))->firstWhere('id','cita-'.$cancel->id);$this->assertTrue($event['extendedProps']['presentation']['informational']);$this->assertSame('DESACTIVADA',$event['extendedProps']['leyenda']);
        $this->assertSame($before,app(DoctorAvailabilityService::class)->forRange($query)->map->toArray()->all());$this->assertSame($rows,$this->snapshot());
        $this->actingAs($this->actor);$ids=collect($this->feed()->assertOk()->json('eventos'))->pluck('extendedProps.appointment_id')->filter()->all();$this->assertContains($unpaid,$ids);$this->assertContains($privatePaid,$ids);
    }
    public static function entryPoints():array{return [['operational'],['legacy']];}
    /** @dataProvider entryPoints */
    public function test_explicit_wound_is_audited_idempotent_and_never_inferred_or_financial(string $entry):void{
        $data=$this->payload(['has_diabetic_wound'=>true]);$url='/scheduling-mvp/agenda/registrations';
        if($entry==='legacy'){$url='/admissionist/appointment/store';$data['service_id']=$this->catalog['doctorService']->id;}
        $r=$this->postJson($url,$data)->assertSuccessful();$id=$entry==='legacy'?$r->json('appointment_id'):$r->json('appointment.appointment_id');
        $a=Appointment::findOrFail($id);$this->assertTrue($a->has_diabetic_wound);$this->assertFalse($a->is_postoperative);$this->assertEquals(100,$a->precio_programado);$this->assertDatabaseCount('payments',0);
        $this->postJson($url,$data)->assertSuccessful();$this->assertDatabaseCount('appointments',1);
        $this->postJson($url,array_replace($data,['has_diabetic_wound'=>false]))->assertConflict();
        $this->assertTrue($a->events()->where('event_type','PRECIO_REGISTRADO')->firstOrFail()->metadata['has_diabetic_wound']);
        $this->actor->revokePermissionTo(C::UPDATE);$this->patchJson('/scheduling-mvp/agenda/appointments/'.$id.'/notes',['has_diabetic_wound'=>false])->assertForbidden();$this->assertTrue($a->fresh()->has_diabetic_wound);
    }
    public function test_new_visual_signals_have_text_glyphs_and_accessible_contrast(): void
    {
        $luminance = function (string $hex): float {
            $rgb = array_map(function ($channel) {
                $v = hexdec($channel) / 255;
                return $v <= 0.04045 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
            }, str_split(ltrim($hex, '#'), 2));
            return $rgb[0] * 0.2126 + $rgb[1] * 0.7152 + $rgb[2] * 0.0722;
        };
        foreach (['CONCILIAR_PAGO', 'PAGO_REAL', 'RESERVA_SIN_PAGO', 'POSTOPERATORIO', 'HERIDA', 'PRESENTE', 'DESACTIVADA'] as $key) {
            $entry = \App\Support\Scheduling\AgendaLegend::of($key);
            $this->assertNotEmpty($entry['etiqueta']);
            $this->assertNotEmpty($entry['glifo']);
            $fg = $luminance($entry['color']); $bg = $luminance($entry['fondo']);
            $this->assertGreaterThanOrEqual(4.5, (max($fg, $bg) + 0.05) / (min($fg, $bg) + 0.05), $key);
        }
    }

    public function test_wound_is_not_inferred_from_service_and_updates_require_visibility_and_permission(): void
    {
        $this->catalog['service']->update(['nombre' => 'PIE DIABETICO']);
        $id = $this->postJson('/scheduling-mvp/agenda/registrations', $this->payload())->assertCreated()->json('appointment.appointment_id');
        $this->assertFalse(Appointment::findOrFail($id)->has_diabetic_wound);
        $this->patchJson('/scheduling-mvp/agenda/appointments/'.$id.'/notes', ['has_diabetic_wound' => true])->assertOk();
        $this->assertTrue(Appointment::findOrFail($id)->has_diabetic_wound);
        $this->assertDatabaseHas('appointment_events', ['appointment_id' => $id, 'event_type' => 'DATOS_OPERATIVOS_ACTUALIZADOS', 'actor_user_id' => $this->actor->id]);
        $other = $this->agendaReader('ADMINISTRADOR');
        $other->givePermissionTo(Permission::findOrCreate(C::UPDATE, 'web'));
        $this->actingAs($other)->patchJson('/scheduling-mvp/agenda/appointments/'.$id.'/notes', ['has_diabetic_wound' => false])->assertNotFound();
        $this->assertTrue(Appointment::findOrFail($id)->has_diabetic_wound);
        $this->assertDatabaseCount('payments', 0);
    }

}