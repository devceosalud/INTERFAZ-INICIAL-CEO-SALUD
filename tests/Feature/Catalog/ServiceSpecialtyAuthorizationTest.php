<?php

namespace Tests\Feature\Catalog;

use App\Models\{Appointment, Doctor, DoctorService, Service, Specialty};
use App\Services\Catalog\{ActiveDoctorServiceResolver, ServicePriceResolver};
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;
class ServiceSpecialtyAuthorizationTest extends TestCase
{
    use RefreshDatabase, BuildsAgendaLifecycleData;
    private function catalog(): array
    {
        $c = $this->createAppointmentCatalog();
        $specialty = new Specialty();
        $specialty->forceFill(['id' => 5, 'nombre' => 'MFR FICTICIO QA', 'estado' => 'ACTIVO'])->save();
        $doctor = new Doctor();
        $doctor->forceFill(['id' => 9, 'specialty_id' => 5, 'nombre' => 'KEYLINN FICTICIA QA', 'estado' => 'ACTIVO'])->save();
        $wrong = DoctorService::create(['doctor_id' => 9, 'service_id' => $c['service']->id, 'precio_primera_consulta' => 100, 'precio_reconsulta' => 80, 'estado' => 'ACTIVO']);
        $mfr = Service::create(['specialty_id' => 5, 'nombre' => 'CONSULTA MFR FICTICIA', 'service_type' => 'CONSULTATION', 'pricing_policy' => 'PROFESSIONAL', 'estado' => 'ACTIVO']);
        $right = DoctorService::create(['doctor_id' => 9, 'service_id' => $mfr->id, 'precio_primera_consulta' => 130, 'precio_reconsulta' => 90, 'estado' => 'ACTIVO']);
        return $c + compact('doctor', 'specialty', 'wrong', 'mfr', 'right');
    }
    public function test_inconsistent_legacy_assignment_is_preserved_but_not_bookable_or_exposed_as_available(): void
    {
        config(['scheduling.enabled' => true]);
        $c = $this->catalog();
        $actor = $this->agendaReader('ADMISION');
        $actor->givePermissionTo(Permission::findOrCreate(C::CREATE, 'web'));
        $history = $this->lifecycleAppointment($actor, $c, ['doctor_id' => 9, 'service_id' => $c['service']->id, 'precio_programado' => 137]);
        $before = $history->fresh()->getAttributes();
        $resolver = app(ActiveDoctorServiceResolver::class);
        $this->assertSame([$c['right']->id], $resolver->query()->where('doctor_id', 9)->pluck('id')->all());
        $board = $this->actingAs($actor)->get('/scheduling-mvp/agenda')->assertOk();
        $services = $board->viewData('doctorServices')[9];
        $this->assertSame([$c['mfr']->id], array_column($services, 'service_id'));
        $this->postJson('/scheduling-mvp/agenda/quote', ['doctor_id' => 9, 'service_id' => $c['service']->id, 'fecha_cita' => $c['schedule']->fecha_cita])->assertUnprocessable();
        $this->postJson('/api/appointment/calculated', ['doctor_id' => 9, 'service_id' => $c['wrong']->id, 'fecha_cita' => $c['schedule']->fecha_cita])->assertUnprocessable();
        $this->postJson('/scheduling-mvp/agenda/quote', ['doctor_id' => 9, 'service_id' => $c['mfr']->id, 'fecha_cita' => $c['schedule']->fecha_cita])->assertOk()->assertJsonPath('precio_programado', '130.00');
        $this->assertSame($before, $history->fresh()->getAttributes());
        $this->assertSame('ACTIVO', $c['wrong']->fresh()->estado);
        $this->assertDatabaseCount('catalog_price_events', 0);
    }
    public function test_explicit_shared_document_keeps_one_id_and_price_and_still_requires_individual_assignment(): void
    {
        $c = $this->catalog();
        $admin = $this->createUserWithRole('ADMINISTRADOR');
        $s = Service::create(['nombre' => 'INFORME MEDICO COMPARTIDO QA', 'specialty_id' => $c['service']->specialty_id, 'service_type' => 'DOCUMENT', 'pricing_policy' => 'INSTITUTIONAL', 'institutional_price' => 35, 'estado' => 'ACTIVO']);
        $payload = ['doctor_id' => 9, 'service_id' => $s->id];
        $this->actingAs($admin)->postJson('/master/admin/doctor/services/store', $payload)->assertUnprocessable();
        $data = ['service_id_edit' => $s->id, 'nombre_edit' => $s->nombre, 'specialty_id_edit' => $s->specialty_id, 'service_type' => 'DOCUMENT', 'pricing_policy' => 'INSTITUTIONAL', 'institutional_price' => '35', 'shared_specialty_ids' => [5], 'catalog_version' => 0];
        $this->putJson('/master/admin/service/update', $data)->assertUnprocessable()->assertJsonValidationErrors('specialty_authorization_reason');
        $this->putJson('/master/admin/service/update', $data + ['specialty_authorization_reason' => 'Documento compartido aprobado QA'])->assertOk();
        $this->assertSame(0, app(ActiveDoctorServiceResolver::class)->query()->where('doctor_id', 9)->where('service_id', $s->id)->count());
        $this->postJson('/master/admin/doctor/services/store', $payload)->assertOk();
        $row = app(ActiveDoctorServiceResolver::class)->resolve(9, $s->id);
        $this->assertSame('35.00', app(ServicePriceResolver::class)->resolve($row, now()->toDateString())['price']);
        $this->assertSame('35.00', DB::transaction(fn() => app(ServicePriceResolver::class)->resolve($row, now()->toDateString(), false, true))['price']);
        $this->assertDatabaseCount('services', 3);
        $this->assertDatabaseCount('service_specialties', 1);
        $this->assertDatabaseCount('catalog_price_events', 2);
        $data['catalog_version'] = 1;
        $data['shared_specialty_ids'] = [];
        $this->putJson('/master/admin/service/update', $data + ['specialty_authorization_reason' => 'Retirar autorización compartida QA'])->assertOk();
        $this->assertSame(0, app(ActiveDoctorServiceResolver::class)->query()->where('doctor_id', 9)->where('service_id', $s->id)->count());
        $this->assertSame('ACTIVO', $row->fresh()->estado);
    }
    public function test_cross_specialty_exception_requires_admin_reason_and_is_invalidated_by_changed_specialty(): void
    {
        $c = $this->catalog();
        $row = $c['wrong'];
        $admin = $this->createUserWithRole('ADMINISTRADOR');
        $worker = $this->createUserWithRole('COMERCIAL');
        $data = ['doctor_service_id' => $row->id, 'doctor_id' => 9, 'service_id' => $row->service_id, 'precio_estandar' => '100', 'reconsulta' => '80', 'catalog_version' => 0];
        $this->actingAs($worker)->putJson('/master/admin/doctor/services/update', $data + ['specialty_exception_authorized' => true, 'specialty_exception_reason' => 'Intento QA'])->assertForbidden();
        $this->actingAs($admin)->putJson('/master/admin/doctor/services/update', $data)->assertUnprocessable();
        $this->putJson('/master/admin/doctor/services/update', $data + ['specialty_exception_authorized' => true])->assertUnprocessable()->assertJsonValidationErrors('specialty_exception_reason');
        $this->putJson('/master/admin/doctor/services/update', $data + ['specialty_exception_authorized' => true, 'specialty_exception_reason' => 'Excepción específica autorizada QA'])->assertOk();
        $this->assertEquals($admin->id, $row->fresh()->specialty_exception_approved_by_user_id);
        $this->assertEquals($row->id, app(ActiveDoctorServiceResolver::class)->resolve(9, $row->service_id)->id);
        $this->assertDatabaseCount('catalog_price_events', 1);
        $other = Specialty::create(['nombre' => 'OTRA FICTICIA', 'estado' => 'ACTIVO']);
        Doctor::find(9)->update(['specialty_id' => $other->id]);
        $this->assertSame(0, app(ActiveDoctorServiceResolver::class)->query()->whereKey($row->id)->count());
    }
    public function test_catalogue_audit_reports_the_inconsistency_without_writing_data(): void
    {
        $c = $this->catalog();
        $before = DoctorService::orderBy('id')->get()->toArray();
        $this->artisan('catalog:services-audit', ['--assignments' => true])->assertSuccessful();
        $this->assertSame($before, DoctorService::orderBy('id')->get()->toArray());
        $this->assertDatabaseCount('catalog_price_events', 0);
        $report = app(\App\Services\Catalog\ServiceCatalogDefinition::class)->report();
        $this->assertCount(16, $report);
    }
}
