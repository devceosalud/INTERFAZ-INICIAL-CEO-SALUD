<?php

namespace Tests\Feature\Catalog;

use App\Models\{Appointment, Doctor, DoctorService, Service, AdditionalRate, Cashier, CashierShift, VoucherSerie, Payment};
use App\Services\Catalog\{ServicePriceResolver, ServiceCatalogDefinition};
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;
class ServicePriceCatalogTest extends TestCase
{
    use RefreshDatabase, BuildsAgendaLifecycleData;
    protected $actor;
    protected array $catalog;
    protected function setUp(): void
    {
        parent::setUp();
        config(['scheduling.enabled' => true, 'app.debug' => false]);
        $this->actor = $this->agendaReader('ADMISION');
        $this->catalog = $this->createAppointmentCatalog();
        $this->actingAs($this->actor);
        $this->catalog['doctorService']->update(['precio_primera_consulta' => 150, 'precio_reconsulta' => 120]);
    }
    private function quote(string $entry = 'operational', array $extra = [])
    {
        return $this->postJson($entry === 'legacy' ? '/api/appointment/calculated' : '/scheduling-mvp/agenda/quote', array_replace(['doctor_id' => $this->catalog['doctor']->id, 'service_id' => $entry === 'legacy' ? $this->catalog['doctorService']->id : $this->catalog['service']->id, 'fecha_cita' => $this->catalog['schedule']->fecha_cita], $extra));
    }
    /** @dataProvider standards */
    public function test_normal_price_is_independent_of_missing_duplicate_or_invalid_standard(string $entry, string $state): void
    {
        $rate = $this->catalog['rate'];
        if ($state === 'missing') {
            $rate->update(['nombre' => 'PRECIO MAL CLASIFICADO', 'tarifa' => 100]);
        } elseif ($state === 'inactive') {
            $rate->update(['nombre' => 'TARIFA ESTANDAR', 'estado' => 'INACTIVO']);
        } elseif ($state === 'invalid') {
            $rate->update(['nombre' => 'TARIFA ESTANDAR', 'tarifa' => 100]);
        } else {
            $rate->update(['nombre' => 'TARIFA ESTANDAR', 'tarifa' => 0]);
            AdditionalRate::create(['nombre' => 'TARIFA ESTANDAR', 'tarifa' => 0, 'tipo_tarifa' => 'MONTO_FIJO', 'estado' => 'ACTIVO']);
        }
        $response = $this->quote($entry)->assertOk()->assertJsonPath('price_source', 'PROFESSIONAL');
        $this->assertEquals(150, $response->json('precio_programado'));
        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('payments', 0);
    }
    public static function standards(): array
    {
        $r = [];
        foreach (['operational', 'legacy'] as $e) {
            foreach (['missing', 'inactive', 'invalid', 'duplicate'] as $s) {
                $r[] = [$e, $s];
            }
        }
        return $r;
    }
    public function test_explicit_invalid_or_ambiguous_standard_returns_controlled_error(): void
    {
        $this->catalog['rate']->update(['nombre' => 'TARIFA ESTANDAR', 'tipo_tarifa' => 'MONTO_FIJO', 'tarifa' => 150]);
        $this->quote(extra: ['additional_rate_id' => $this->catalog['rate']->id])->assertUnprocessable()->assertJsonPath('code', 'CATALOG_CONFIGURATION_REQUIRED');
        $this->catalog['rate']->update(['tarifa' => 0]);
        AdditionalRate::create(['nombre' => 'TARIFA ESTANDAR', 'tarifa' => 0, 'tipo_tarifa' => 'MONTO_FIJO', 'estado' => 'ACTIVO']);
        $this->quote(extra: ['additional_rate_id' => $this->catalog['rate']->id])->assertUnprocessable();
        $this->quote(extra: ['additional_rate_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('additional_rate_id');
    }
    public function test_extra_is_an_adjustment_not_a_replacement_for_the_base_price(): void
    {
        $this->catalog['rate']->update(['tipo_tarifa' => 'MONTO_FIJO', 'tarifa' => 50]);
        $this->quote(extra: ['additional_rate_id' => $this->catalog['rate']->id])->assertOk()->assertJsonPath('precio_programado', '200.00');
        $this->quote()->assertOk()->assertJsonPath('precio_programado', '150.00');
    }
    public function test_multiple_services_and_professionals_resolve_their_own_prices(): void
    {
        $other = Doctor::create(['specialty_id' => $this->catalog['specialty']->id, 'nombre' => 'PROFESIONAL FICTICIO B', 'estado' => 'ACTIVO']);
        DoctorService::create(['doctor_id' => $other->id, 'service_id' => $this->catalog['service']->id, 'precio_primera_consulta' => 100, 'precio_reconsulta' => 80, 'estado' => 'ACTIVO']);
        $this->quote(extra: ['doctor_id' => $other->id])->assertOk()->assertJsonPath('precio_programado', '100.00');
        $procedure = Service::create(['specialty_id' => $this->catalog['specialty']->id, 'nombre' => 'PROCEDIMIENTO FICTICIO', 'estado' => 'ACTIVO', 'service_type' => 'PROCEDURE', 'pricing_policy' => 'INSTITUTIONAL', 'institutional_price' => 345]);
        foreach ([$other->id, $this->catalog['doctor']->id] as $doctorId) {
            DoctorService::create(['doctor_id' => $doctorId, 'service_id' => $procedure->id, 'precio_primera_consulta' => 999, 'estado' => 'ACTIVO']);
            $this->quote(extra: ['doctor_id' => $doctorId, 'service_id' => $procedure->id])->assertOk()->assertJsonPath('precio_programado', '345.00')->assertJsonPath('price_source', 'INSTITUTIONAL');
        }
        $this->catalog['doctorService']->update(['precio_primera_consulta' => null]);
        $this->catalog['service']->update(['institutional_price' => 130]);
        $this->quote()->assertOk()->assertJsonPath('precio_programado', '130.00')->assertJsonPath('price_source', 'INSTITUTIONAL_DEFAULT');
    }
    public function test_institutional_price_does_not_authorize_an_unassigned_professional(): void
    {
        $this->catalog['service']->update(['pricing_policy' => 'INSTITUTIONAL', 'institutional_price' => 200]);
        $other = Doctor::create(['specialty_id' => $this->catalog['specialty']->id, 'nombre' => 'SIN ASIGNACION FICTICIO', 'estado' => 'ACTIVO']);
        $this->quote(extra: ['doctor_id' => $other->id])->assertUnprocessable()->assertJsonValidationErrors('service_id');
    }
    public function test_missing_and_zero_prices_fail_closed_and_do_not_fall_back_to_frontend_values(): void
    {
        $this->catalog['service']->update(['pricing_policy' => 'INSTITUTIONAL', 'service_type' => 'PROCEDURE']);
        $this->quote(extra: ['precio_programado' => 123])->assertUnprocessable()->assertJsonValidationErrors('service_id');
        $this->catalog['service']->update(['institutional_price' => 0]);
        $this->quote()->assertUnprocessable();
        $this->catalog['service']->update(['institutional_price' => 125, 'price_valid_from' => '2099-01-01']);
        $this->quote()->assertUnprocessable();
    }
    public function test_reevaluation_selection_without_accredited_indication_is_rejected(): void
    {
        $this->quote(extra: ['price_modality' => 'REEVALUATION'])->assertUnprocessable()->assertJsonValidationErrors('reevaluation_indication_id');
        $this->quote(extra: ['price_modality' => 'REEVALUATION', 'reevaluation_indication_id' => 999999, 'commercial_service_id' => $this->catalog['service']->id])->assertUnprocessable();
        $this->quote(extra: ['price_modality' => 'CONSULTATION', 'reevaluation_indication_id' => 999999])->assertUnprocessable();
    }
    /** @dataProvider entries */
    public function test_paid_confirmation_and_price_snapshot_survive_catalogue_edits(string $entry): void
    {
        if($entry==='legacy')$this->actor->assignRole(\Spatie\Permission\Models\Role::findOrCreate('ADMINISTRADOR','web'));
        foreach ([C::CREATE, C::SUBMIT_PAYMENT] as $p) {
            $this->actor->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }
        $p = $this->createPatient($this->actor);
        $box = Cashier::create(['nombre' => 'CAJA FICTICIA', 'estado' => 'ACTIVO']);
        CashierShift::create(['cashier_id' => $box->id, 'user_id' => $this->actor->id, 'monto_apertura' => 0, 'abierto_en' => now(), 'estado' => 'ABIERTO']);
        VoucherSerie::create(['cashier_id' => $box->id, 'tipo_comprobante' => 'TICKET', 'serie' => 'Q001', 'correlativo_actual' => 0, 'estado' => 'ACTIVO']);
        $d = ['request_key' => (string) Str::uuid(), 'mode' => 'CONFIRM', 'booking_type' => 'REGULAR', 'patient_id' => $p->id, 'doctor_id' => $this->catalog['doctor']->id, 'service_id' => $entry === 'legacy' ? $this->catalog['doctorService']->id : $this->catalog['service']->id, 'fecha_cita' => $this->catalog['schedule']->fecha_cita, 'hora_cita' => '08:00', 'duracion_cita' => 30, 'payment' => ['amount' => '30.00', 'method' => 'EFECTIVO'], 'total_pagado' => '30.00', 'metodo_pago' => 'EFECTIVO'];
        $url = $entry === 'legacy' ? '/admissionist/appointment/store' : '/scheduling-mvp/agenda/registrations';
        $this->postJson($url, $d)->assertSuccessful();
        $a = Appointment::firstOrFail();
        $before = $a->getAttributes();
        $this->assertSame('CONFIRMADA', $a->estado_agenda);
        $this->assertEquals(120, $a->saldo_pendiente);
        $this->assertEquals(30, Payment::sum('monto'));
        $this->assertNull($a->additional_rate_id);
        $this->assertSame('PROFESSIONAL', $a->price_source);
        $this->postJson($url, $d)->assertSuccessful();
        $this->assertDatabaseCount('payments', 1);
        $this->catalog['doctorService']->update(['precio_primera_consulta' => 300]);
        $this->quote()->assertOk()->assertJsonPath('precio_programado', '300.00');
        $this->assertSame($before, $a->fresh()->getAttributes());
    }
    public static function entries(): array
    {
        return [['operational'], ['legacy']];
    }
    public function test_admin_price_edit_is_real_audited_revision_checked_and_does_not_create_duplicate(): void
    {
        $admin = $this->createUserWithRole('ADMINISTRADOR');
        $row = $this->catalog['doctorService'];
        $data = ['doctor_service_id' => $row->id, 'doctor_id' => $row->doctor_id, 'service_id' => $row->service_id, 'precio_estandar' => '165.25', 'reconsulta' => '125.50', 'dias' => $row->dias_reconsulta, 'catalog_version' => 0];
        $this->putJson('/master/admin/doctor/services/update', $data)->assertForbidden();
        $this->actingAs($admin)->putJson('/master/admin/doctor/services/update', $data)->assertOk();
        $this->assertEquals('165.25', $row->fresh()->precio_primera_consulta);
        $this->assertEquals('125.50', $row->fresh()->precio_reconsulta);
        $this->assertDatabaseCount('catalog_price_events', 1);
        $this->assertDatabaseCount('doctor_services', 1);
        $this->putJson('/master/admin/doctor/services/update', $data)->assertUnprocessable()->assertJsonValidationErrors('catalog_version');
        $this->assertDatabaseCount('catalog_price_events', 1);
        unset($data['doctor_service_id'], $data['catalog_version']);
        $this->postJson('/master/admin/doctor/services/store', $data)->assertUnprocessable();
    }
    public function test_institutional_master_requires_price_or_inactive_state_and_preserves_inactive_duplicate_history(): void
    {
        $admin = $this->createUserWithRole('ADMINISTRADOR');
        $this->actingAs($admin);
        $d = ['nombre' => 'INFORME MÉDICO FICTICIO', 'specialty_id' => $this->catalog['specialty']->id, 'service_type' => 'DOCUMENT', 'pricing_policy' => 'INSTITUTIONAL'];
        $this->postJson('/master/admin/service/store', $d)->assertUnprocessable()->assertJsonValidationErrors('institutional_price');
        $this->postJson('/master/admin/service/store', $d + ['estado' => 'INACTIVO'])->assertOk();
        $s = Service::where('nombre', $d['nombre'])->firstOrFail();
        $this->postJson('/master/admin/service/store', $d + ['institutional_price' => 10])->assertUnprocessable()->assertJsonValidationErrors('nombre');
        Service::create(['nombre' => $d['nombre'], 'specialty_id' => $s->specialty_id, 'estado' => 'INACTIVO']);
        $this->putJson('/master/admin/service/update', $d + ['service_id_edit' => $s->id, 'catalog_version' => 1, 'estado' => 'INACTIVO', 'institutional_price' => '10.00'])->assertOk();
        $this->assertDatabaseCount('services', 3);
    }
    public function test_catalog_report_covers_16_entries_without_creating_or_reclassifying_data(): void
    {
        $before = Service::all()->toArray();
        $r = app(ServiceCatalogDefinition::class)->report();
        $this->assertCount(16, $r);
        $this->assertSame($before, Service::all()->toArray());
        $this->assertSame('REEVALUATION_MODALITY', $r[2]['representation']);
        $this->assertDatabaseCount('catalog_price_events', 0);
    }
    public function test_future_valid_price_remains_selectable_but_quote_checks_the_actual_attention_date(): void
    {
        $date = $this->catalog['schedule']->fecha_cita;
        $this->catalog['service']->update(['price_valid_from' => $date]);
        $services = $this->get('/scheduling-mvp/agenda')->assertOk()->viewData('doctorServices');
        $this->assertTrue($services[$this->catalog['doctor']->id][0]['disponible']);
        $this->quote()->assertOk()->assertJsonPath('precio_programado', '150.00');
        $this->quote('operational', ['fecha_cita' => \Carbon\Carbon::parse($date)->subDay()->toDateString()])->assertUnprocessable()->assertJsonValidationErrors('service_id');
        $this->assertDatabaseCount('appointments', 0);
    }
    public function test_price_resolver_reloads_a_stale_assignment_before_quote_and_locked_write(): void
    {
        $stale = $this->catalog['doctorService'];
        DB::table('doctor_services')->where('id', $stale->id)->update(['precio_primera_consulta' => '175.25']);
        $this->assertEquals(150, $stale->precio_primera_consulta);
        $resolver = app(ServicePriceResolver::class);
        $date = $this->catalog['schedule']->fecha_cita;
        $this->assertSame('175.25', $resolver->resolve($stale, $date)['price']);
        $locked = DB::transaction(fn() => $resolver->resolve($stale, $date, false, true));
        $this->assertSame('175.25', $locked['price']);
        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('payments', 0);
    }
}
