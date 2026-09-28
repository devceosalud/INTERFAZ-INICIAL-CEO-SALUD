<?php

namespace Tests\Feature\Baseline;

use App\Http\Livewire\CashierShifts;
use App\Http\Livewire\Sales;
use App\Models\Cashier;
use App\Models\Item;
use App\Models\Voucher;
use App\Models\VoucherSerie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

class CashierSalesAndPaymentsSmokeTest extends TestCase
{
    use BuildsBaselineData;
    use RefreshDatabase;

    public function test_current_livewire_flow_opens_cashier_and_records_sale_voucher_item_and_payment(): void
    {
        $user = $this->createUser();
        $patient = $this->createPatient($user);
        $cashier = Cashier::create(['nombre' => 'Caja baseline', 'estado' => 'ACTIVO']);

        Livewire::actingAs($user)
            ->test(CashierShifts::class)
            ->set('cajaId', $cashier->id)
            ->set('montoApertura', 100)
            ->call('abrirTurno');

        $shift = $cashier->shifts()->firstOrFail();
        $this->assertSame('ABIERTO', $shift->estado);

        VoucherSerie::create([
            'tipo_comprobante' => 'BOLETA',
            'serie' => 'B001',
            'correlativo_actual' => 0,
            'cashier_id' => $cashier->id,
            'estado' => 'ACTIVO',
        ]);
        $item = Item::create([
            'nombre' => 'Producto baseline',
            'tipo' => 'PRODUCTO',
            'vendible' => true,
            'afectacion_igv' => '10',
            'unidad_medida' => 'UNIDAD',
            'unidad_medida_sunat' => 'NIU',
            'precio_venta' => 118,
            'stock_actual' => 10,
            'estado' => 'ACTIVO',
        ]);

        Livewire::actingAs($user)
            ->test(Sales::class)
            ->set('atiendeId', $patient->id)
            ->set('tipoComprobante', 'BOLETA')
            ->set('pagoEfectivo', 118)
            ->set('carrito', [[
                'item_type' => 'item',
                'item_id' => $item->id,
                'descripcion' => $item->nombre,
                'precio' => 118,
                'cantidad' => 1,
                'afectacion_igv' => '10',
                'codigo_sunat' => null,
                'unidad_medida_sunat' => 'NIU',
                'doctor_id' => null,
                'comision_porcentaje' => 0,
            ]])
            ->call('guardarVenta')
            ->assertDispatchedBrowserEvent('venta-guardada');

        $voucher = Voucher::with(['items', 'payments'])->firstOrFail();
        $this->assertSame('BOLETA', $voucher->tipo_comprobante);
        $this->assertSame('B001', $voucher->serie);
        $this->assertSame(1, (int) $voucher->correlativo);
        $this->assertSame('118.00', $voucher->total);
        $this->assertSame('PAGADO', $voucher->estado);
        $this->assertCount(1, $voucher->items);
        $this->assertCount(1, $voucher->payments);
        $this->assertSame('EFECTIVO', $voucher->payments->first()->metodo_pago);
        $this->assertSame('118.00', $voucher->payments->first()->monto);
        $this->assertSame(9, (int) $item->fresh()->stock_actual);
        $this->assertSame(1, (int) VoucherSerie::firstOrFail()->correlativo_actual);
    }
}
