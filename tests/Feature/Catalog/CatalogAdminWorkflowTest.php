<?php

namespace Tests\Feature\Catalog;

use App\Http\Livewire\Sales;
use App\Models\{Service, DoctorService, Voucher, VoucherSerie};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;
class CatalogAdminWorkflowTest extends TestCase
{
    use RefreshDatabase, BuildsAgendaLifecycleData;
    public function test_admin_can_filter_edit_reload_and_quote_without_repricing_history(): void
    {
        config(['scheduling.enabled' => true]);
        $admin = $this->agendaReader('ADMINISTRADOR');
        $c = $this->createAppointmentCatalog();
        $row = $c['doctorService'];
        $history = $this->lifecycleAppointment($admin, $c);
        $before = $history->fresh()->getAttributes();
        $this->actingAs($admin)->get('/master/admin/doctor/services?doctor_id=' . $row->doctor_id)->assertOk()->assertSee('formUpdateDoctorService', false)->assertSee('Ver servicios y precios');
        $this->get('/master/admin/service')->assertOk()->assertSee('institutional_price', false);
        $this->get('/master/admin/additional-rate')->assertOk()->assertSee('catalog_version', false);
        $this->putJson('/master/admin/doctor/services/update', ['doctor_service_id' => $row->id, 'doctor_id' => $row->doctor_id, 'service_id' => $row->service_id, 'precio_estandar' => '175.00', 'reconsulta' => '125.00', 'catalog_version' => 0])->assertOk();
        $this->get('/master/admin/doctor/services?doctor_id=' . $row->doctor_id)->assertOk()->assertSee('175');
        $this->postJson('/scheduling-mvp/agenda/quote', ['doctor_id' => $row->doctor_id, 'service_id' => $row->service_id, 'fecha_cita' => $c['schedule']->fecha_cita])->assertOk()->assertJsonPath('precio_programado', '175.00');
        $this->assertSame($before, $history->fresh()->getAttributes());
        $this->assertEquals($row->dias_reconsulta, $row->fresh()->dias_reconsulta);
        $this->assertDatabaseCount('catalog_price_events', 1);
    }
    public function test_institutional_price_defaults_and_reactivation_are_editable_without_doctor_override(): void
    {
        $admin = $this->createUserWithRole('ADMINISTRADOR');
        $c = $this->createAppointmentCatalog();
        $s = $c['service'];
        $row = $c['doctorService'];
        $this->actingAs($admin);
        $data = ['service_id_edit' => $s->id, 'nombre_edit' => $s->nombre, 'specialty_id_edit' => $s->specialty_id, 'service_type' => 'PROCEDURE', 'pricing_policy' => 'INSTITUTIONAL', 'institutional_price' => '280.50', 'catalog_version' => 0];
        $this->putJson('/master/admin/service/update', $data)->assertOk();
        $this->putJson('/master/admin/doctor/services/update', ['doctor_service_id' => $row->id, 'doctor_id' => $row->doctor_id, 'service_id' => $s->id, 'precio_estandar' => '1.00', 'catalog_version' => 0])->assertUnprocessable();
        $this->postJson('/master/admin/doctor/services/delete', ['id' => $row->id, 'catalog_version' => 0])->assertOk();
        $this->putJson('/master/admin/doctor/services/update', ['doctor_service_id' => $row->id, 'doctor_id' => $row->doctor_id, 'service_id' => $s->id, 'estado' => 'ACTIVO', 'catalog_version' => 1])->assertOk();
        $price = app(\App\Services\Catalog\ServicePriceResolver::class)->resolve($row->fresh(), $c['schedule']->fecha_cita);
        $this->assertSame('280.50', $price['price']);
        $this->assertSame('INSTITUTIONAL', $price['source']);
        $this->assertDatabaseCount('doctor_services', 1);
        $this->assertDatabaseCount('catalog_price_events', 3);
    }
    public function test_sales_rejects_tampered_or_stale_service_prices_before_writing_money(): void
    {
        $u = $this->agendaReader('RECEPCION');
        $c = $this->createAppointmentCatalog();
        $patient = $this->createPatient($u);
        $box = \App\Models\Cashier::create(['nombre' => 'QA CATALOG', 'estado' => 'ACTIVO']);
        \App\Models\CashierShift::create(['cashier_id' => $box->id, 'user_id' => $u->id, 'monto_apertura' => 0, 'abierto_en' => now(), 'estado' => 'ABIERTO']);
        VoucherSerie::create(['cashier_id' => $box->id, 'tipo_comprobante' => 'TICKET', 'serie' => 'QCAT', 'correlativo_actual' => 0, 'estado' => 'ACTIVO']);
        if (DB::getDriverName() === 'sqlite') {
            DB::connection()->getPdo()->sqliteCreateFunction('CONCAT_WS', fn($s, ...$p) => implode($s, array_filter($p, fn($v) => $v !== null)));
        }
        $line = ['item_type' => 'servicio', 'item_id' => $c['service']->id, 'doctor_id' => $c['doctor']->id, 'descripcion' => 'SERVICIO FICTICIO', 'precio' => 1, 'cantidad' => 1, 'afectacion_igv' => '20', 'codigo_sunat' => null, 'unidad_medida_sunat' => 'ZZ', 'comision_porcentaje' => 0];
        $live = Livewire::actingAs($u)->test(Sales::class)->set('atiendeId', $patient->id)->set('tipoComprobante', 'TICKET')->set('pagoEfectivo', 30)->set('carrito', [$line]);
        $live->call('guardarVenta')->assertHasErrors('catalog');
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('vouchers', 0);
        $line['precio'] = 100;
        $live->set('carrito', [$line]);
        $c['doctorService']->update(['precio_primera_consulta' => 150]);
        $live->call('guardarVenta')->assertHasErrors('catalog');
        $this->assertDatabaseCount('payments', 0);
        $line['precio'] = 150;
        $live->set('carrito', [$line])->call('guardarVenta')->assertHasNoErrors()->assertDispatchedBrowserEvent('venta-guardada');
        $this->assertSame('150.00', Voucher::sole()->total);
        $this->assertEquals(30, \App\Models\Payment::sum('monto'));
        $this->assertDatabaseCount('voucher_items', 1);
    }
    public function test_linked_consultation_cannot_be_reclassified_as_a_document(): void
    {
        $admin = $this->createUserWithRole('ADMINISTRADOR');
        $c = $this->createAppointmentCatalog();
        $s = $c['service'];
        $s->update(['service_type' => 'CONSULTATION', 'pricing_policy' => 'PROFESSIONAL']);
        $alias = Service::create(['nombre' => 'REEVALUACION FICTICIA', 'specialty_id' => $s->specialty_id, 'service_type' => 'REEVALUATION', 'pricing_policy' => 'PROFESSIONAL', 'canonical_service_id' => $s->id, 'estado' => 'ACTIVO']);
        $this->actingAs($admin)->putJson('/master/admin/service/update', ['service_id_edit' => $s->id, 'nombre_edit' => $s->nombre, 'specialty_id_edit' => $s->specialty_id, 'service_type' => 'DOCUMENT', 'pricing_policy' => 'INSTITUTIONAL', 'institutional_price' => '35', 'catalog_version' => 0])->assertUnprocessable()->assertJsonValidationErrors('service_type');
        $this->assertSame('CONSULTATION', $s->fresh()->service_type);
        $this->assertEquals($s->id, $alias->fresh()->canonical_service_id);
        $this->assertDatabaseCount('catalog_price_events', 0);
    }
    public function test_valid_until_without_a_start_is_editable_and_audited(): void
    {
        $admin = $this->createUserWithRole('ADMINISTRADOR');
        $c = $this->createAppointmentCatalog();
        $this->actingAs($admin)->postJson('/master/admin/service/store', ['nombre' => 'DOCUMENTO FICTICIO CON VIGENCIA', 'specialty_id' => $c['specialty']->id, 'service_type' => 'DOCUMENT', 'pricing_policy' => 'INSTITUTIONAL', 'institutional_price' => '35', 'price_valid_until' => now()->addMonth()->toDateString()])->assertOk();
        $this->assertDatabaseCount('catalog_price_events', 1);
        $this->assertDatabaseHas('services', ['nombre' => 'DOCUMENTO FICTICIO CON VIGENCIA', 'institutional_price' => 35, 'price_valid_from' => null]);
    }
}
