<?php

namespace Tests\Feature\Sales;

use App\Http\Livewire\Sales;
use App\Models\Appointment;
use App\Models\Cashier;
use App\Models\CashierShift;
use App\Models\User;
use App\Models\Voucher;
use App\Support\Scheduling\AppointmentAgendaLifecycle as Lifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

class AppointmentSalesPrivacyTest extends TestCase
{
    use BuildsAgendaLifecycleData;
    use RefreshDatabase;

    private User $owner;
    private User $other;
    private array $catalog;
    private CashierShift $shift;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);
        $this->owner = $this->agendaReader('RECEPCION');
        $this->other = $this->agendaReader('RECEPCION');
        $this->catalog = $this->createAppointmentCatalog();
        $cashier = Cashier::create(['nombre' => 'Caja privacidad', 'estado' => 'ACTIVO']);
        $this->shift = CashierShift::create([
            'cashier_id' => $cashier->id, 'user_id' => $this->other->id,
            'monto_apertura' => 0, 'abierto_en' => now(), 'estado' => 'ABIERTO',
        ]);
        // Exercise the existing MySQL search expression against the isolated SQLite DB.
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::connection()->getPdo()->sqliteCreateFunction('CONCAT_WS', static function ($separator, ...$parts) {
                return implode($separator, array_filter($parts, fn ($part) => $part !== null));
            });
        }
    }

    public function test_search_filters_before_limit_and_only_the_creator_finds_a_pending_appointment(): void
    {
        for ($i = 0; $i < 11; $i++) {
            $this->lifecycleAppointment($this->owner, $this->catalog, ['estado_agenda' => Lifecycle::PENDING_CONFIRMATION]);
        }
        $legacy = $this->lifecycleAppointment($this->owner, $this->catalog);
        $confirmed = $this->lifecycleAppointment($this->owner, $this->catalog, ['estado_agenda' => Lifecycle::CONFIRMED]);
        $own = $this->lifecycleAppointment($this->other, $this->catalog, ['estado_agenda' => Lifecycle::PENDING_CONFIRMATION]);
        $component = Livewire::actingAs($this->other)->test(Sales::class)->set('buscarCita', 'PRIVACY-');
        $this->assertEqualsCanonicalizing([$legacy->id, $confirmed->id, $own->id], collect($component->get('resultadosCitas'))->pluck('appointment_id')->all());

        $ownerSearch = Livewire::actingAs($this->owner)->test(Sales::class)->set('buscarCita', 'PRIVACY-');
        $ids = collect($ownerSearch->get('resultadosCitas'))->pluck('appointment_id');
        $this->assertCount(10, $ids);
        $this->assertNotContains($own->id, $ids->all());
        $this->assertTrue(Appointment::whereIn('id', $ids)->where('estado_agenda', Lifecycle::PENDING_CONFIRMATION)->exists());
    }

    public function test_appointment_id_actions_treat_hidden_and_missing_as_404(): void
    {
        $hidden = $this->lifecycleAppointment($this->owner, $this->catalog, ['estado_agenda' => Lifecycle::PENDING_CONFIRMATION]);
        foreach ([$hidden->id, 999999] as $id) {
            Livewire::actingAs($this->other)->test(Sales::class)
                ->call('agregarCitaAlCarrito', $id, $hidden->patient_id, 100, $hidden->doctor_id)->assertNotFound();
            Livewire::actingAs($this->other)->test(Sales::class)
                ->call('agregarAlCarrito', ['tipo_origen' => 'cita', 'id' => $id])->assertNotFound();
        }
        Livewire::actingAs($this->owner)->test(Sales::class)
            ->call('agregarCitaAlCarrito', $hidden->id, $hidden->patient_id, 100, $hidden->doctor_id)->assertStatus(200)
            ->assertSet('atiendeId', $hidden->patient_id);
    }

    public function test_manipulated_public_cart_properties_cannot_bypass_appointment_visibility(): void
    {
        $hidden = $this->lifecycleAppointment($this->owner, $this->catalog, ['estado_agenda' => Lifecycle::PENDING_CONFIRMATION]);
        foreach (['cita', Appointment::class] as $type) {
            Livewire::actingAs($this->other)->test(Sales::class)->set('carrito', [[
                'item_type' => $type, 'item_id' => $hidden->id, 'descripcion' => 'Entrada manipulada',
                'precio' => 100, 'cantidad' => 1, 'afectacion_igv' => '10',
                'doctor_id' => $hidden->doctor_id, 'comision_porcentaje' => 0,
            ]])->assertNotFound();
        }
        $this->assertSame(0, Voucher::count());
    }

    public function test_ticket_lists_lookups_source_ticket_and_printing_exclude_hidden_appointments(): void
    {
        $patient = $this->createPatient($this->owner);
        $hidden = $this->lifecycleAppointment($this->owner, $this->catalog, ['estado_agenda' => Lifecycle::PENDING_CONFIRMATION], $patient);
        $legacy = $this->lifecycleAppointment($this->owner, $this->catalog, [], $patient);
        $confirmed = $this->lifecycleAppointment($this->owner, $this->catalog, ['estado_agenda' => Lifecycle::CONFIRMED], $patient);
        $privateTicket = $this->ticket($hidden);
        $sharedTicket = $this->ticket($legacy);
        $confirmedTicket = $this->ticket($confirmed);
        $mixed = $this->ticket($legacy);
        $mixed->items()->create($this->line($hidden));
        $classLink = $this->ticket($hidden, ['item_type' => Appointment::class]);
        $child = $this->ticket($legacy);
        $child->update(['parent_voucher_id' => $privateTicket->id, 'tipo_comprobante' => 'BOLETA']);
        $hiddenChild = $this->ticket($hidden);
        $hiddenChild->update(['parent_voucher_id' => $sharedTicket->id, 'tipo_comprobante' => 'BOLETA']);

        $component = Livewire::actingAs($this->other)->test(Sales::class)->set('atiendeId', $patient->id);
        $this->assertEqualsCanonicalizing([$sharedTicket->id, $confirmedTicket->id], $component->instance()->ticketsPendientes->pluck('id')->all());
        $this->assertSame(0, Voucher::visibleToAgendaUser($this->other->id)->whereKey([$privateTicket->id, $mixed->id, $classLink->id, $child->id, $hiddenChild->id])->count());
        $this->assertSame(5, Voucher::visibleToAgendaUser($this->owner->id)->whereKey([$privateTicket->id, $mixed->id, $classLink->id, $child->id, $hiddenChild->id])->count());

        foreach ([$privateTicket->id, $mixed->id, $classLink->id, $child->id, $hiddenChild->id, 999999] as $id) {
            $this->actingAs($this->other)->get('/receptionist/'.$id.'/imprimir')->assertNotFound();
            Livewire::actingAs($this->other)->test(Sales::class)->call('liquidarTicket', $id)->assertNotFound();
            Livewire::actingAs($this->other)->test(Sales::class)->set('ticketOrigenId', $id)
                ->call('getMontoACobrarProperty')->assertNotFound();
        }
        foreach ([$sharedTicket, $confirmedTicket] as $ticket) {
            $this->actingAs($this->other)->get('/receptionist/'.$ticket->id.'/imprimir')->assertOk();
        }
        $this->actingAs($this->owner)->get('/receptionist/'.$privateTicket->id.'/imprimir')->assertOk();
        Livewire::actingAs($this->owner)->test(Sales::class)->call('liquidarTicket', $privateTicket->id)->assertStatus(200)->assertSet('ticketOrigenId', $privateTicket->id);
    }

    public function test_standalone_sales_and_orphaned_legacy_links_keep_their_existing_visibility(): void
    {
        $appointment = $this->lifecycleAppointment($this->owner, $this->catalog);
        $standalone = $this->ticket($appointment, ['item_type' => 'servicio', 'item_id' => $this->catalog['service']->id]);
        $orphan = $this->ticket($appointment, ['item_id' => 999999]);
        $this->assertEqualsCanonicalizing([$standalone->id, $orphan->id], Voucher::visibleToAgendaUser($this->other->id)->pluck('id')->all());
        $this->actingAs($this->other)->get('/receptionist/'.$standalone->id.'/imprimir')->assertOk();
    }

    private function ticket(Appointment $appointment, array $lineOverrides = []): Voucher
    {
        $voucher = Voucher::create([
            'tipo_comprobante' => 'TICKET', 'serie' => 'T001', 'correlativo' => Voucher::count() + 1,
            'patient_id' => $appointment->patient_id, 'subtotal' => 100, 'total' => 100,
            'cashier_shift_id' => $this->shift->id, 'user_id' => $this->owner->id,
        ]);
        $voucher->items()->create(array_replace($this->line($appointment), $lineOverrides));

        return $voucher;
    }

    private function line(Appointment $appointment): array
    {
        return ['item_type' => 'cita', 'item_id' => $appointment->id, 'descripcion' => 'Consulta privada de prueba',
            'precio_unitario' => 100, 'total' => 100, 'doctor_id' => $appointment->doctor_id];
    }
}
