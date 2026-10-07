<?php

namespace Tests\Feature\Sales;

use App\Http\Livewire\Sales;
use App\Models\Cashier;
use App\Models\CashierShift;
use App\Models\Doctor;
use App\Models\DoctorService;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

class DoctorServicePricingTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAgendaLifecycleData;

    private $actor;
    private array $catalog;
    private DoctorService $current;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = $this->agendaReader('RECEPCION');
        $this->catalog = $this->createAppointmentCatalog();
        $this->catalog['doctorService']->update(['estado' => 'INACTIVO']);
        $this->current = DoctorService::create(['doctor_id' => $this->catalog['doctor']->id,
            'service_id' => $this->catalog['service']->id, 'precio_primera_consulta' => 150,
            'precio_reconsulta' => 120, 'dias_reconsulta' => 15, 'estado' => 'ACTIVO']);
        $cashier = Cashier::create(['nombre' => 'Pricing smoke', 'estado' => 'ACTIVO']);
        CashierShift::create(['cashier_id' => $cashier->id, 'user_id' => $this->actor->id,
            'monto_apertura' => 0, 'abierto_en' => now(), 'estado' => 'ABIERTO']);
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::connection()->getPdo()->sqliteCreateFunction('CONCAT_WS', fn ($separator, ...$parts) => implode($separator, array_filter($parts, fn ($v) => $v !== null)));
        }
    }

    public function test_services_require_a_doctor_and_use_only_that_doctors_active_assignment(): void
    {
        $other = Doctor::create(['specialty_id' => $this->catalog['specialty']->id, 'nombre' => 'General', 'estado' => 'ACTIVO']);
        DoctorService::create(['doctor_id' => $other->id, 'service_id' => $this->current->service_id,
            'precio_primera_consulta' => 100, 'precio_reconsulta' => 80, 'estado' => 'ACTIVO']);
        $c = Livewire::actingAs($this->actor)->test(Sales::class)->set('busqueda', 'baseline');
        $this->assertSame([], $c->get('resultadosBusqueda'));
        $c->set('filtroDoctorId', $this->current->doctor_id);
        $this->assertCount(1, $c->get('resultadosBusqueda'));
        $this->assertEquals(150, $c->get('resultadosBusqueda')[0]['precio']);
        $c->set('filtroDoctorId', $other->id);
        $this->assertEquals(100, $c->get('resultadosBusqueda')[0]['precio']);
    }

    public function test_active_duplicates_fail_explicitly_even_if_one_price_is_identical(): void
    {
        DB::table('doctor_services')->insert(['doctor_id' => $this->current->doctor_id, 'service_id' => $this->current->service_id,
            'precio_primera_consulta' => 150, 'estado' => 'ACTIVO']);
        Livewire::actingAs($this->actor)->test(Sales::class)->set('filtroDoctorId', $this->current->doctor_id)
            ->set('busqueda', 'baseline')->assertHasErrors('catalog')->assertSet('resultadosBusqueda', []);
    }

    public function test_selecting_a_service_revalidates_current_price_and_rejects_an_inactive_stale_result(): void
    {
        $c = Livewire::actingAs($this->actor)->test(Sales::class)->set('filtroDoctorId', $this->current->doctor_id);
        $line = ['tipo_origen' => 'servicio', 'id' => $this->current->service_id, 'nombre' => 'Consulta',
            'precio' => 100, 'cantidad' => 1, 'afectacion_igv' => '10', 'codigo_sunat' => null,
            'unidad_medida_sunat' => 'ZZ', 'comision' => 0];
        $c->call('agregarAlCarrito', $line);
        $this->assertEquals(150, $c->get('carrito')[0]['precio']);
        $this->current->update(['estado' => 'INACTIVO']);
        $c->call('agregarAlCarrito', $line)->assertHasErrors('catalog');
        $this->assertCount(1, $c->get('carrito'));
    }

    public function test_service_selection_cannot_bypass_ambiguity_or_missing_doctor(): void
    {
        $line = ['tipo_origen' => 'servicio', 'id' => $this->current->service_id];
        $c = Livewire::actingAs($this->actor)->test(Sales::class);
        $c->call('agregarAlCarrito', $line)->assertHasErrors('catalog')->assertSet('carrito', []);
        DB::table('doctor_services')->insert(['doctor_id' => $this->current->doctor_id, 'service_id' => $this->current->service_id, 'estado' => 'ACTIVO']);
        $c->set('filtroDoctorId', $this->current->doctor_id)->call('agregarAlCarrito', $line)
            ->assertHasErrors('catalog')->assertSet('carrito', []);
    }

    public function test_existing_appointment_uses_its_100_snapshot_without_duplicate_rows_or_repricing(): void
    {
        $a = $this->lifecycleAppointment($this->actor, $this->catalog, ['precio_programado' => 100]);
        $this->catalog['rate']->update(['tarifa' => 25]);
        $before = $a->fresh()->getAttributes();
        $c = Livewire::actingAs($this->actor)->test(Sales::class)->set('buscarCita', $a->numero_cita);
        $this->assertCount(1, $c->get('resultadosCitas'));
        $this->assertEquals(100, $c->get('resultadosCitas')[0]['precio_total']);
        $c->call('agregarCitaAlCarrito', $a->id, $a->patient_id, 150, $a->doctor_id);
        $this->assertEquals(100, $c->get('carrito')[0]['precio']);
        $this->assertSame($before, $a->fresh()->getAttributes());
        $this->assertDatabaseCount('vouchers', 0);
    }

    public function test_catalogue_inactivation_does_not_hide_or_reprice_an_existing_appointment(): void
    {
        $a = $this->lifecycleAppointment($this->actor, $this->catalog, ['precio_programado' => 150]);
        $this->current->update(['estado' => 'INACTIVO']);
        $c = Livewire::actingAs($this->actor)->test(Sales::class)->set('buscarCita', $a->numero_cita);
        $this->assertCount(1, $c->get('resultadosCitas'));
        $this->assertEquals(150, $c->get('resultadosCitas')[0]['precio_total']);
    }

    public function test_product_search_remains_available_without_a_doctor(): void
    {
        $item = Item::create(['nombre' => 'Producto independiente', 'tipo' => 'PRODUCTO', 'vendible' => true,
            'precio_venta' => 118, 'stock_actual' => 2, 'controla_stock' => true,
            'afectacion_igv' => '10', 'estado' => 'ACTIVO']);
        $c = Livewire::actingAs($this->actor)->test(Sales::class)->set('busqueda', 'independiente');
        $rows = $c->get('resultadosBusqueda');
        $this->assertCount(1, $rows);
        $this->assertEquals($item->id, $rows[0]['id']);
        $this->assertSame('item', $rows[0]['tipo_origen']);
        $c->call('agregarAlCarrito', $rows[0])->assertHasNoErrors('catalog');
        $this->assertEquals(118, $c->get('carrito')[0]['precio']);
    }
}
