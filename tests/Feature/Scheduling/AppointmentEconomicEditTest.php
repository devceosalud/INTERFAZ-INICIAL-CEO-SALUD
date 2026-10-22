<?php
namespace Tests\Feature\Scheduling;

use App\Models\{AdditionalRate,Appointment,AppointmentEvent,CashierShift,Payment,Voucher,VoucherItem};
use App\Services\Scheduling\{AppointmentEconomicEdit,AppointmentManagement,EconomicCorrectionSnapshot};
use App\Support\Scheduling\SchedulingCapability as C;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Schema};
use Illuminate\Support\Str;
use Spatie\Permission\Models\{Permission,Role};
use Tests\Concerns\BuildsRescheduleVisualData;
use Tests\TestCase;

class AppointmentEconomicEditTest extends TestCase
{
    use RefreshDatabase,BuildsRescheduleVisualData;
    private $actor,$a,$catalog;
    protected function setUp():void
    {
        parent::setUp();foreach($this->rescheduleVisualFixture() as $key=>$value)$this->{$key}=$value;
        $this->catalog['rate']->update(['tipo_tarifa'=>'MONTO_FIJO']);
        $this->actor->syncRoles([Role::findOrCreate('ADMINISTRADOR','web')]);
        $this->actor->givePermissionTo([C::EDIT_PAYMENT,C::EDIT_PRICING]);$this->actingAs($this->actor);
    }
    protected function tearDown():void {Carbon::setTestNow();parent::tearDown();}
    private function url(string $suffix=''):string{return route('scheduling.mvp.economic-edit.index',$this->a->id).$suffix;}
    private function draft(array $extra=[]):array{return $extra+['kind'=>'PAYMENT','expected_revision'=>AppointmentManagement::revision($this->a->fresh()),
        'reason'=>'Corrección respaldada con datos FICTICIOS','payment_id'=>Payment::firstOrFail()->id,'error_type'=>'amount','amount'=>'70'];}
    private function pricing(array $extra=[]):array{return $extra+['kind'=>'PRICING','expected_revision'=>AppointmentManagement::revision($this->a->fresh()),
        'reason'=>'Ajuste autorizado FICTICIO de la cita','pricing_action'=>'DISCOUNT','discount'=>['type'=>'FIXED','value'=>'20','reason'=>'Descuento FICTICIO autorizado']];}
    private function reviewed(array $d,?string $key=null):array
    {
        $r=$this->postJson($this->url('/review'),$d)->assertOk()->json();
        return $d+['request_key'=>$key??(string)Str::uuid(),'confirmed'=>true,'snapshot_hash'=>$r['snapshot_hash'],'review_token'=>$r['review_token'],'expires_at'=>$r['expires_at']];
    }
    private function submit(array $d){return $this->putJson($this->url(),$this->reviewed($d));}
    private function events(){return AppointmentEvent::where('event_type',AppointmentEconomicEdit::EVENT);}

    public function test_A_corrects_50_to_60_on_same_payment_without_fictitious_collection():void
    {
        $payment=Payment::firstOrFail();$payment->update(['monto'=>50]);$this->a->update(['total_pagado'=>50,'saldo_pendiente'=>70]);
        $this->submit($this->draft(['amount'=>'60']))->assertOk();
        $this->assertDatabaseCount('payments',1);$this->assertSame(60.0,(float)$payment->fresh()->monto);
        $this->assertSame(60.0,(float)$this->a->fresh()->saldo_pendiente);$this->assertSame(60.0,(float)CashierShift::firstOrFail()->calcularMontoSistema());
        $this->assertSame('PAYMENT',$this->events()->firstOrFail()->metadata['kind']);
    }
    public function test_B_discount_after_60_payment_updates_ticket_and_balance_to_40():void
    {
        $this->submit($this->pricing())->assertOk();
        $this->assertEquals(100,$this->a->fresh()->precio_programado);$this->assertEquals(40,$this->a->fresh()->saldo_pendiente);
        $this->assertEquals(60,Payment::sum('monto'));$this->assertEquals(100,Voucher::firstOrFail()->total);$this->assertEquals(100,VoucherItem::firstOrFail()->total);
        $this->assertEquals(60,CashierShift::firstOrFail()->calcularMontoSistema());
    }
    public function test_C_valid_catalog_surcharge_20_produces_140_and_80_without_payment():void
    {
        $rate=AdditionalRate::create(['nombre'=>'RECARGO FICTICIO MISMA CONSULTA','tipo_tarifa'=>'MONTO_FIJO','tarifa'=>20,'estado'=>'ACTIVO']);
        $d=$this->pricing(['pricing_action'=>'SURCHARGE','discount'=>[],'additional_rate_id'=>$rate->id]);
        $this->submit($d)->assertOk();$this->assertEquals(140,$this->a->fresh()->precio_programado);$this->assertEquals(80,$this->a->fresh()->saldo_pendiente);
        $this->assertDatabaseCount('payments',1);$this->assertEquals(60,Payment::sum('monto'));$this->assertEquals(20,$rate->fresh()->tarifa);
        $other=AdditionalRate::create(['nombre'=>'OTRO RECARGO FICTICIO','tipo_tarifa'=>'MONTO_FIJO','tarifa'=>30,'estado'=>'ACTIVO']);
        $this->submit($this->pricing(['pricing_action'=>'SURCHARGE','discount'=>[],'additional_rate_id'=>$other->id]))->assertOk();
        $this->assertEquals(150,$this->a->fresh()->precio_programado);
        $this->submit($this->pricing(['pricing_action'=>'SURCHARGE','discount'=>[],'additional_rate_id'=>null]))->assertOk();$this->assertEquals(120,$this->a->fresh()->precio_programado);
    }
    public function test_percent_discount_edit_and_remove_preserve_actual_money():void
    {
        $this->submit($this->pricing(['discount'=>['type'=>'PERCENT','value'=>'10','reason'=>'Beneficio ficticio']]))->assertOk();$this->assertEquals(108,$this->a->fresh()->precio_programado);
        $this->submit($this->pricing(['discount'=>['type'=>'FIXED','value'=>'15','reason'=>'Rectificación ficticia']]))->assertOk();$this->assertEquals(105,$this->a->fresh()->precio_programado);
        $this->submit($this->pricing(['discount'=>[]]))->assertOk();$this->assertEquals(120,$this->a->fresh()->precio_programado);$this->assertEquals(60,Payment::sum('monto'));$this->assertSame(3,$this->events()->count());
    }
    public function test_contract_edit_never_changes_catalog_or_original_payment():void
    {
        $catalog=$this->catalog['doctorService']->fresh()->getAttributes();$payment=Payment::firstOrFail()->getAttributes();
        $this->submit($this->pricing(['pricing_action'=>'CONTRACT','base_price'=>'150','additional_rate_id'=>null,'discount'=>[]]))->assertOk();
        $this->assertEquals(150,$this->a->fresh()->precio_programado);$this->assertEquals(90,$this->a->fresh()->saldo_pendiente);
        $this->assertSame($catalog,$this->catalog['doctorService']->fresh()->getAttributes());$this->assertSame($payment,Payment::firstOrFail()->getAttributes());
    }
    public function test_bank_reference_correction_and_duplicate_rejection():void
    {
        $p=Payment::firstOrFail();$p->update(['metodo_pago'=>'YAPE','numero_operacion'=>'FAKE-OLD']);
        $this->submit($this->draft(['error_type'=>'reference','operation'=>'FAKE-NEW']))->assertOk();$this->assertSame('FAKE-NEW',$p->fresh()->numero_operacion);
        Payment::create(['voucher_id'=>$p->voucher_id,'user_id'=>$this->actor->id,'cashier_shift_id'=>$p->cashier_shift_id,'monto'=>10,'metodo_pago'=>'YAPE','numero_operacion'=>'FAKE-DUP']);
        $this->a->update(['total_pagado'=>70,'saldo_pendiente'=>50]);
        $this->postJson($this->url('/review'),$this->draft(['error_type'=>'reference','operation'=>'FAKE-DUP']))->assertUnprocessable();
        $this->assertSame('FAKE-NEW',$p->fresh()->numero_operacion);$this->assertSame(1,$this->events()->count());
    }
    public function test_preview_without_confirmation_does_not_persist_any_operation():void
    {
        $before=DB::table('appointment_operations')->count();$d=$this->pricing();$this->postJson($this->url('/review'),$d)->assertOk();
        $this->assertEquals(120,$this->a->fresh()->precio_programado);$this->assertSame(0,$this->events()->count());$this->assertSame($before,DB::table('appointment_operations')->count());
        $payload=$this->reviewed($d);$payload['confirmed']=false;$this->putJson($this->url(),$payload)->assertUnprocessable();$this->assertSame(0,$this->events()->count());
    }
    public function test_requires_actual_new_permission_not_payment_registration_or_request_approval():void
    {
        $this->actor->revokePermissionTo([C::EDIT_PAYMENT,C::EDIT_PRICING]);
        $this->getJson($this->url())->assertForbidden();$this->postJson($this->url('/review'),$this->draft())->assertForbidden();
        foreach(['appointment.payment.correction.request','appointment.payment.correction.approve'] as $name)$this->actor->givePermissionTo(Permission::findOrCreate($name,'web'));
        $this->postJson($this->url('/review'),$this->draft())->assertForbidden();$this->assertSame(0,$this->events()->count());
    }
    public function test_administrator_direct_editor_needs_no_second_approver_or_create_payment_permission():void
    {
        $this->actor->revokePermissionTo([C::CREATE,C::SUBMIT_PAYMENT,C::RESCHEDULE]);
        $this->submit($this->draft())->assertOk();$this->assertSame($this->actor->id,$this->events()->firstOrFail()->actor_user_id);
        $this->assertDatabaseCount('payments',1);$this->assertEquals(70,Payment::sum('monto'));
    }

    public static function unauthorizedEconomicRoles():array
    {
        return ['COMERCIAL'=>['COMERCIAL'],'ADMISION'=>['ADMISION'],'RECEPCION'=>['RECEPCION']];
    }

    /** @dataProvider unauthorizedEconomicRoles */
    public function test_non_administrator_cannot_edit_even_with_both_direct_permissions(string $role):void
    {
        $this->actor->syncRoles([Role::findOrCreate($role,'web')]);
        $this->assertTrue($this->actor->fresh()->can(C::EDIT_PAYMENT));
        $this->assertTrue($this->actor->fresh()->can(C::EDIT_PRICING));
        $this->postJson($this->url('/review'),$this->draft())->assertForbidden();
        $this->postJson($this->url('/review'),$this->pricing())->assertForbidden();
        $this->putJson($this->url(),$this->draft())->assertForbidden();
        $this->assertDatabaseCount('payments',1);
        $this->assertSame(0,$this->events()->count());
    }

    public function test_idempotent_double_send_payload_collision_and_concurrent_review():void
    {
        $d=$this->reviewed($this->draft());$other=$this->reviewed($this->pricing());
        $this->putJson($this->url(),$d)->assertOk()->assertJsonPath('replayed',false);
        $this->putJson($this->url(),$d)->assertOk()->assertJsonPath('replayed',true);$this->assertSame(1,$this->events()->count());$this->assertDatabaseCount('payments',1);
        $bad=$d;$bad['amount']='80';$this->putJson($this->url(),$bad)->assertConflict();
        $this->putJson($this->url(),$other)->assertConflict();$this->assertEquals(120,$this->a->fresh()->precio_programado);
    }
    public function test_changed_financial_data_rate_permission_or_review_token_rejects_save():void
    {
        $d=$this->reviewed($this->draft());$bad=$d;$bad['review_token']=str_repeat('a',64);$this->putJson($this->url(),$bad)->assertConflict();
        Payment::firstOrFail()->update(['monto'=>65]);$this->putJson($this->url(),$d)->assertConflict();$this->assertSame(0,$this->events()->count());
        $this->actor->revokePermissionTo(C::EDIT_PAYMENT);$this->putJson($this->url(),$d)->assertForbidden();
    }
    public function test_audit_minimizes_data_and_final_hash_revision_match_committed_operation():void
    {
        $this->a->update(['motivo_consulta'=>'CLINICAL_SENTINEL_PRIVATE']);Voucher::firstOrFail()->update(['direccion_cliente'=>'ADDRESS_SENTINEL_PRIVATE']);
        $this->submit($this->draft())->assertOk();$event=$this->events()->firstOrFail();$m=$event->metadata;
        $this->assertEquals(60,$m['before']['position']['paid_cents']/100);$this->assertEquals(70,$m['after']['position']['paid_cents']/100);
        $this->assertSame(AppointmentManagement::revision($this->a->fresh()),$m['after']['appointment_revision']);
        $this->assertSame(EconomicCorrectionSnapshot::hash($m['after']),$m['after_hash']);$this->assertNotEmpty($m['request_key']);$this->assertNotEmpty($m['payment_id']);
        $serialized=json_encode($m);foreach(['CLINICAL_SENTINEL_PRIVATE','ADDRESS_SENTINEL_PRIVATE','private_path','sunat_respuesta'] as $text)$this->assertStringNotContainsString($text,$serialized);
    }
    public function test_excess_discount_100_and_payment_over_price_do_not_change_records():void
    {
        $p=$this->reviewed($this->draft(['amount'=>'130']));$this->putJson($this->url(),$p)->assertUnprocessable();
        $d=$this->reviewed($this->pricing(['discount'=>['type'=>'FIXED','value'=>'80','reason'=>'Caso ficticio de excedente']]));$this->putJson($this->url(),$d)->assertUnprocessable();
        $this->postJson($this->url('/review'),$this->pricing(['discount'=>['type'=>'PERCENT','value'=>'100','reason'=>'No permitido']]))->assertUnprocessable();
        $this->assertEquals(60,Payment::sum('monto'));$this->assertEquals(120,$this->a->fresh()->precio_programado);$this->assertSame(0,$this->events()->count());
    }
    public static function blockedCases():array{return array_map(fn($s)=>[$s],['closed','fiscal','derived','refund','credit','cancelled','multi-item','reconciled']);}
    /** @dataProvider blockedCases */
    public function test_unsupported_financial_context_is_preserved(string $case):void
    {
        $v=Voucher::firstOrFail();$shift=CashierShift::firstOrFail();
        if($case==='closed')$shift->update(['estado'=>'CERRADO','cerrado_en'=>now()]);
        if($case==='fiscal')$v->update(['requiere_sunat'=>true]);
        if($case==='derived'){$copy=$v->replicate();$copy->serie='DUPF';$copy->parent_voucher_id=$v->id;$copy->save();}
        if($case==='refund')DB::table('appointment_refund_requests')->insert(['appointment_id'=>$this->a->id,'voucher_id'=>$v->id,'amount'=>1,'status'=>'SOLICITADA','actor_user_id'=>$this->actor->id,'requested_at'=>now(),'operation_id'=>DB::table('appointment_operations')->value('id')]);
        if($case==='credit')DB::table('appointment_credit_applications')->insert(['source_appointment_id'=>$this->a->id,'destination_appointment_id'=>$this->a->id,'voucher_id'=>$v->id,'amount'=>1,'applied_by_user_id'=>$this->actor->id,'applied_at'=>now(),'operation_id'=>DB::table('appointment_operations')->value('id')]);
        if($case==='cancelled')$this->a->update(['estado_cita'=>'CANCELADO']);
        if($case==='multi-item')VoucherItem::firstOrFail()->replicate()->save();
        if($case==='reconciled')$shift->update(['monto_sistema'=>60]);
        $d=$this->reviewed($this->pricing());$this->putJson($this->url(),$d)->assertUnprocessable();
        $this->assertEquals(120,$this->a->fresh()->precio_programado);$this->assertEquals(60,Payment::sum('monto'));$this->assertSame(0,$this->events()->count());
    }
    public function test_reports_and_new_payment_flow_remain_consistent_after_edit():void
    {
        $this->submit($this->pricing())->assertOk();
        $this->postJson(route('scheduling.mvp.admission.feed'),['fecha'=>'2026-10-21','site_id'=>null])->assertOk()->assertJsonPath('totals.precio','100.00')->assertJsonPath('totals.pagos_reales','60.00')->assertJsonPath('totals.saldo','40.00');
        app(\App\Services\Scheduling\ReservationPaymentService::class)->submit($this->a->id,$this->actor,['request_key'=>(string)Str::uuid(),'payment'=>['amount'=>'40','method'=>'EFECTIVO']]);
        $this->assertEquals(100,Payment::sum('monto'));$this->assertEquals(0,$this->a->fresh()->saldo_pendiente);$this->assertEquals('PAGADO',$this->a->fresh()->estado_pagado);
    }
    public function test_permissions_migration_is_idempotent_but_commercial_grants_do_not_bypass_admin_only_editor():void
    {
        $migration=require base_path('database/migrations/2026_10_21_200000_define_direct_appointment_economic_permissions.php');
        $roles=DB::table('role_has_permissions')->get()->toJson();$migration->up();$migration->up();$migration->down();
        $this->assertSame($roles,DB::table('role_has_permissions')->get()->toJson());
        $this->assertSame(2,Permission::whereIn('name',[C::EDIT_PAYMENT,C::EDIT_PRICING])->count());
        $this->actor->revokePermissionTo([C::EDIT_PAYMENT,C::EDIT_PRICING]);$role=Role::findByName('COMERCIAL','web');$admin=$this->agendaReader('ADMINISTRADOR');$this->actingAs($admin);
        $this->get(route('admin.roles.edit',$role))->assertOk()->assertSee('Corregir adelantos registrados')->assertSee('Modificar economía de citas existentes');
        $this->put(route('admin.permissions.update',$role),['name'=>'COMERCIAL','permission_revision'=>\App\Http\Controllers\admin\master\role\RoleController::revision($role),
            'permissions_present'=>'1','permissions'=>Permission::whereIn('name',[C::EDIT_PAYMENT,C::EDIT_PRICING])->pluck('id')->all()])->assertRedirect();
        $this->assertTrue($this->actor->fresh()->can(C::EDIT_PAYMENT));$this->assertTrue($this->actor->fresh()->can(C::EDIT_PRICING));
        // A direct permission grant must never turn the COMERCIAL role into an economic editor.
        $this->actor->syncRoles([Role::findByName('COMERCIAL','web')]);
        $this->actingAs($this->actor);
        $this->postJson($this->url('/review'),$this->draft())->assertForbidden();
        $this->assertFalse(Schema::hasTable('appointment_economic_corrections'));
    }
    public function test_rate_is_catalog_based_and_changed_or_expired_rule_invalidates_review():void
    {
        $rate=AdditionalRate::create(['nombre'=>'DIEZ POR CIENTO FICTICIO','tipo_tarifa'=>'PORCENTAJE','tarifa'=>10,'estado'=>'ACTIVO']);
        $draft=$this->pricing(['pricing_action'=>'SURCHARGE','discount'=>[],'additional_rate_id'=>$rate->id]);
        $this->postJson($this->url('/review'),$draft)->assertOk()->assertJsonPath('after.price','132.00')->assertJsonPath('after.balance','72.00');
        $payload=$this->reviewed($draft);$rate->update(['tarifa'=>15]);$this->putJson($this->url(),$payload)->assertConflict();
        $rate->update(['fecha_fin'=>'2026-10-20']);$this->postJson($this->url('/review'),$draft)->assertUnprocessable();
        $this->assertEquals(120,$this->a->fresh()->precio_programado);$this->assertSame(0,$this->events()->count());
    }
    public function test_expired_review_requires_new_review_but_completed_operation_remains_idempotent():void
    {
        $payload=$this->reviewed($this->draft());Carbon::setTestNow(now()->addMinutes(11));$this->putJson($this->url(),$payload)->assertConflict();
        $payload=$this->reviewed($this->draft());$this->putJson($this->url(),$payload)->assertOk();Carbon::setTestNow(now()->addMinutes(11));
        $this->putJson($this->url(),$payload)->assertOk()->assertJsonPath('replayed',true);$this->assertSame(1,$this->events()->count());
    }
    public function test_only_selected_payment_changes_when_multiple_real_payments_exist():void
    {
        app(\App\Services\Scheduling\ReservationPaymentService::class)->submit($this->a->id,$this->actor,['request_key'=>(string)Str::uuid(),'payment'=>['amount'=>'10','method'=>'EFECTIVO']]);
        $second=Payment::orderByDesc('id')->firstOrFail();$this->submit($this->draft(['amount'=>'65']))->assertOk();
        $this->assertDatabaseCount('payments',2);$this->assertEquals(10,$second->fresh()->monto);$this->assertEquals(75,Payment::sum('monto'));$this->assertEquals(45,$this->a->fresh()->saldo_pendiente);
    }
    public function test_price_edit_without_payment_uses_same_reservation_without_confirming_it():void
    {
        Payment::query()->delete();VoucherItem::query()->delete();Voucher::query()->delete();
        $this->a->update(['total_pagado'=>0,'saldo_pendiente'=>120,'estado_pagado'=>'PENDIENTE','estado_agenda'=>'PENDIENTE_CONFIRMACION','economic_source'=>'LEGACY']);
        $this->submit($this->pricing())->assertOk();$this->assertEquals(100,$this->a->fresh()->precio_programado);
        $this->assertEquals('PENDIENTE_CONFIRMACION',$this->a->fresh()->estado_agenda);$this->assertDatabaseCount('payments',0);$this->assertDatabaseCount('vouchers',0);
    }
    public function test_hidden_appointment_is_not_discoverable_and_review_signature_is_actor_bound():void
    {
        $payload=$this->reviewed($this->draft());$other=$this->agendaReader('ADMINISTRADOR');$other->givePermissionTo([C::EDIT_PAYMENT,C::EDIT_PRICING]);
        $this->actingAs($other);$this->putJson($this->url(),$payload)->assertConflict();
        $this->a->update(['estado_agenda'=>'PENDIENTE_CONFIRMACION','responsible_user_id'=>$this->actor->id]);
        $this->getJson($this->url())->assertNotFound();$this->postJson($this->url('/review'),$this->draft())->assertNotFound();$this->assertSame(0,$this->events()->count());
    }
}
