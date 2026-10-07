<?php
namespace Tests\Feature\Sales;

use App\Models\{Appointment, Payment};
use App\Services\Billing\AppointmentEconomicPosition;
use App\Support\Billing\BankPaymentIdentity as Identity;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

class BankPaymentIntegrityTest extends TestCase
{
    use RefreshDatabase, BuildsAgendaLifecycleData;
    private $actor; private $catalog; private $patient;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scheduling.enabled' => true, 'scheduling.pilot_payment_without_manual_cash_shift' => true, 'app.debug' => false]);
        $this->actor = $this->operator('ADMISION'); $this->catalog = $this->createAppointmentCatalog();
        $this->catalog['rate']->update(['nombre' => 'TARIFA ESTANDAR', 'tipo_tarifa' => 'MONTO_FIJO']);
        $this->patient = $this->createPatient($this->actor); $this->actingAs($this->actor);
    }

    private function operator(string $role)
    {
        $actor = $this->agendaReader($role);
        foreach ([C::CREATE, C::SUBMIT_PAYMENT, C::WITHDRAW, C::RESCHEDULE] as $cap) { $actor->givePermissionTo(Permission::findOrCreate($cap, 'web')); }
        return $actor;
    }

    private function payload(array $changes = []): array
    {
        return array_replace(['request_key' => (string) Str::uuid(), 'mode' => 'CONFIRM', 'booking_type' => 'REGULAR',
            'patient_id' => $this->patient->id, 'doctor_id' => $this->catalog['doctor']->id, 'service_id' => $this->catalog['service']->id,
            'fecha_cita' => $this->catalog['schedule']->fecha_cita, 'hora_cita' => '08:00', 'duracion_cita' => 30,
            'payment' => ['amount' => '50.00', 'method' => 'YAPE', 'origin' => 'BCP', 'operation' => 'ABC123']], $changes);
    }
    private function register(array $data) { return $this->postJson(route('scheduling.mvp.agenda.registrations'), $data); }

    public function test_pilot_origin_persists_and_same_request_is_idempotent(): void
    {
        $p = $this->payload(); $this->register($p)->assertCreated(); $this->register($p)->assertCreated();
        $this->assertDatabaseCount('payments', 1); $this->assertEquals(50, Payment::sum('monto'));
        $payment = Payment::firstOrFail(); $this->assertSame('BCP', $payment->entidad_origen);
        $this->assertSame(Identity::key('YAPE', 'BCP', 'ABC123'), $payment->bank_identity_key);
        $this->assertDatabaseCount('appointment_pilot_cash_contexts', 1);
        $this->assertArrayNotHasKey('bank_identity_key', $payment->toArray());
    }

    public function test_duplicate_other_ticket_actor_and_uuid_does_not_change_balance_or_capacity(): void
    {
        $this->register($this->payload())->assertCreated();
        $other = $this->operator('COMERCIAL'); $this->actingAs($other);
        $b = $this->register($this->payload(['mode' => 'RESERVE', 'hora_cita' => '08:30', 'payment' => null]))->assertCreated()->json('appointment.appointment_id');
        $position = app(AppointmentEconomicPosition::class)->forAppointment(Appointment::findOrFail($b));
        $capacityUrl = route('scheduling.mvp.agenda.regular-capacity', ['doctor_id' => $this->catalog['doctor']->id,
            'from' => $this->catalog['schedule']->fecha_cita, 'to' => $this->catalog['schedule']->fecha_cita]);
        $before = $this->getJson($capacityUrl)->assertOk()->json();
        $this->postJson(route('scheduling.mvp.agenda.payments', $b), ['request_key' => (string) Str::uuid(), 'confirm' => true,
            'payment' => ['amount' => '50.00', 'method' => 'YAPE', 'origin' => 'BBVA', 'operation' => ' abc123 ']])
            ->assertUnprocessable()->assertJsonPath('message', Identity::DUPLICATE);
        $this->assertDatabaseCount('payments', 1); $this->assertDatabaseCount('vouchers', 1);
        $this->assertDatabaseCount('appointment_pilot_cash_contexts', 1); $this->assertEquals(50, Payment::sum('monto'));
        $this->assertEquals($position, app(AppointmentEconomicPosition::class)->forAppointment(Appointment::findOrFail($b)));
        $this->assertEquals($before, $this->getJson($capacityUrl)->assertOk()->json());
    }

    public function test_transfer_distinct_banks_are_distinct_but_known_alias_and_case_cannot_bypass(): void
    {
        $pay = ['amount' => '50.00', 'method' => 'TRANSFERENCIA', 'origin' => 'BCP', 'operation' => 'ABC123'];
        $this->register($this->payload(['payment' => $pay]))->assertCreated();
        $pay['origin'] = ' banco de crédito del perú '; $pay['operation'] = 'abc123';
        $this->register($this->payload(['hora_cita' => '08:30', 'payment' => $pay]))->assertUnprocessable();
        $pay['origin'] = 'BBVA'; $this->register($this->payload(['hora_cita' => '08:30', 'payment' => $pay]))->assertCreated();
        $this->assertDatabaseCount('payments', 2); $this->assertEquals(100, Payment::sum('monto'));
        $this->assertSame(['BCP', 'BBVA'], Payment::orderBy('id')->pluck('entidad_origen')->all());
    }

    public function test_cash_has_no_bank_key_or_operation_and_other_bank_operation_is_accepted(): void
    {
        $this->register($this->payload())->assertCreated();
        $this->register($this->payload(['hora_cita' => '08:30', 'payment' => ['amount' => '50.00', 'method' => 'YAPE', 'operation' => 'ABC124']]))->assertCreated();
        foreach (['09:00', '09:30'] as $time) {
            $this->register($this->payload(['hora_cita' => $time, 'payment' => ['amount' => '50.00', 'method' => 'EFECTIVO']]))->assertCreated();
        }
        $cash = Payment::where('metodo_pago', 'EFECTIVO')->get(); $this->assertCount(2, $cash);
        foreach ($cash as $p) { $this->assertNull($p->numero_operacion); $this->assertNull($p->bank_identity_key); }
        $this->assertEquals(200, Payment::sum('monto'));
    }

    public function test_non_cash_requires_operation_and_transfer_card_require_entity(): void
    {
        foreach (['YAPE', 'PLIN', 'TRANSFERENCIA', 'TARJETA'] as $method) {
            $this->register($this->payload(['payment' => ['amount' => '50.00', 'method' => $method, 'origin' => 'BCP']]))->assertUnprocessable();
        }
        foreach (['TRANSFERENCIA', 'TARJETA'] as $method) {
            $this->register($this->payload(['payment' => ['amount' => '50.00', 'method' => $method, 'operation' => 'ABC123']]))
                ->assertUnprocessable()->assertJsonValidationErrors('payment.origin');
        }
        $this->assertDatabaseCount('payments', 0); $this->assertDatabaseCount('appointments', 0);
    }

    public function test_legacy_unknown_bank_remains_readable_and_requires_review_before_reuse(): void
    {
        $this->register($this->payload(['payment' => ['amount' => '50.00', 'method' => 'EFECTIVO']]))->assertCreated();
        DB::table('payments')->update(['metodo_pago' => ' transferencia ', 'numero_operacion' => 'abc123', 'entidad_origen' => null, 'bank_identity_key' => null]);
        $this->assertEquals(50, Payment::firstOrFail()->monto); $this->assertNull(Payment::first()->entidad_origen);
        $this->register($this->payload(['hora_cita' => '08:30', 'payment' => ['amount' => '50.00', 'method' => 'TRANSFERENCIA', 'origin' => 'BCP', 'operation' => 'ABC123']]))->assertUnprocessable();
        DB::table('payments')->update(['metodo_pago' => 'OTROS']);
        $this->register($this->payload(['hora_cita' => '08:30', 'payment' => ['amount' => '50.00', 'method' => 'TRANSFERENCIA', 'origin' => 'BCP', 'operation' => 'ABC123']]))->assertUnprocessable();
        $this->assertSame('OTROS', Payment::firstOrFail()->metodo_pago);
        $this->assertDatabaseCount('payments', 1); $this->assertEquals(50, Payment::sum('monto'));
    }

    public function test_unique_constraint_wins_after_advisory_check_and_returns_operational_error(): void
    {
        Payment::creating(function (Payment $p): void {
            // Another insert winning after the advisory check: exercise actual SQL constraint and translation.
            DB::table('payments')->insert($p->getAttributes() + ['created_at' => now(), 'updated_at' => now()]);
        });
        try {
            $this->register($this->payload())->assertUnprocessable()->assertJsonPath('message', Identity::DUPLICATE);
            $this->assertDatabaseCount('payments', 0); $this->assertDatabaseCount('vouchers', 0);
        } finally { Payment::getEventDispatcher()->forget('eloquent.creating: '.Payment::class); }
    }

    public function test_bank_withdrawal_credit_does_not_create_or_reuse_a_payment(): void
    {
        $a = $this->register($this->payload())->assertCreated()->json('appointment.appointment_id'); $payment = Payment::firstOrFail();
        $this->postJson(route('scheduling.mvp.agenda.withdraw', $a), ['request_key' => (string) Str::uuid(), 'motivo' => 'QA ficticio', 'requested_action' => 'REPROGRAMAR', 'was_present' => true])->assertOk();
        $this->postJson(route('scheduling.mvp.agenda.rebook-withdrawal', $a), ['request_key' => (string) Str::uuid(), 'fecha_cita' => $this->catalog['schedule']->fecha_cita,
            'hora_cita' => '08:30', 'duracion_cita' => 30, 'credits' => [['voucher_id' => $payment->voucher_id, 'amount' => '50.00']]])
            ->assertCreated()->assertJsonPath('economy.credito_aplicado', '50.00');
        $this->assertDatabaseCount('payments', 1); $this->assertEquals(50, Payment::sum('monto'));
        $this->assertSame($payment->bank_identity_key, $payment->fresh()->bank_identity_key);
    }
    public function test_sales_card_bank_persists_and_duplicate_rolls_back_ticket_and_stock(): void
    {
        $actor = $this->createUserWithRole('RECEPCION');
        $box = \App\Models\Cashier::create(['nombre' => 'Caja banco ficticia', 'estado' => 'ACTIVO']);
        \App\Models\CashierShift::create(['cashier_id' => $box->id, 'user_id' => $actor->id, 'estado' => 'ABIERTO', 'monto_apertura' => 0, 'abierto_en' => now()]);
        \App\Models\VoucherSerie::create(['cashier_id' => $box->id, 'tipo_comprobante' => 'TICKET', 'serie' => 'TB01', 'estado' => 'ACTIVO', 'correlativo_actual' => 0]);
        $item = \App\Models\Item::create(['nombre' => 'Producto ficticio banco', 'tipo' => 'PRODUCTO', 'vendible' => true,
            'afectacion_igv' => '10', 'unidad_medida' => 'UNIDAD', 'unidad_medida_sunat' => 'NIU', 'precio_venta' => 10, 'stock_actual' => 5, 'estado' => 'ACTIVO']);
        $cart = [['item_type' => 'item', 'item_id' => $item->id, 'descripcion' => $item->nombre, 'precio' => 10, 'cantidad' => 1,
            'afectacion_igv' => '10', 'codigo_sunat' => null, 'unidad_medida_sunat' => 'NIU', 'doctor_id' => null, 'comision_porcentaje' => 0]];
        for ($i = 0; $i < 2; $i++) {
            $component = \Livewire\Livewire::actingAs($actor)->test(\App\Http\Livewire\Sales::class)->set('atiendeId', $this->patient->id)
                ->set('tipoComprobante', 'TICKET')->set('pagoTarjeta', 10)->set('numeroOperacionTarjeta', 'CARD-ABC123')->set('entidadOrigen', 'BCP')
                ->set('carrito', $cart)->assertSee('Banco / billetera')->call('guardarVenta');
            if ($i === 0) { $component->assertDispatchedBrowserEvent('venta-guardada'); }
            else { $component->assertHasErrors('payment.operation')->assertSee(Identity::DUPLICATE); }
        }
        $this->assertDatabaseCount('payments', 1); $this->assertDatabaseCount('vouchers', 1);
        $this->assertSame('BCP', Payment::firstOrFail()->entidad_origen); $this->assertEquals(10, Payment::sum('monto'));
        $this->assertEquals(4, $item->fresh()->stock_actual);
        $this->assertEquals(1, \App\Models\VoucherSerie::firstOrFail()->correlativo_actual);
    }

}
