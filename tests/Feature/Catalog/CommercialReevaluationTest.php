<?php

namespace Tests\Feature\Catalog;

use App\Models\{Appointment, Service, DoctorService};
use App\Services\Scheduling\ReevaluationIndicationService;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;
class CommercialReevaluationTest extends TestCase
{
    use RefreshDatabase, BuildsAgendaLifecycleData;
    /** @dataProvider cases */
    public function test_alias_keeps_canonical_service_clinical_rules_and_professional_price(string $entry, int $days, bool $foot, bool $referred): void
    {
        Storage::fake('local');
        config(['scheduling.enabled' => true]);
        $actor = $this->agendaReader('ADMISION');
        foreach ([C::CREATE, ReevaluationIndicationService::CAPABILITY] as $p) {
            $actor->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }
        $catalog = $this->createAppointmentCatalog();
        $patient = $this->createPatient($actor);
        $catalog['service']->update(['service_type' => 'CONSULTATION', 'pricing_policy' => 'PROFESSIONAL']);
        $catalog['doctorService']->update(['precio_primera_consulta' => 150, 'precio_reconsulta' => 120]);
        if ($foot) {
            $catalog['service'] = Service::create(['nombre' => 'CONSULTA PIE FICTICIO', 'specialty_id' => $catalog['specialty']->id, 'estado' => 'ACTIVO', 'service_type' => 'CONSULTATION', 'pricing_policy' => 'PROFESSIONAL']);
            $catalog['doctorService'] = DoctorService::create(['doctor_id' => $catalog['doctor']->id, 'service_id' => $catalog['service']->id, 'precio_primera_consulta' => 150, 'precio_reconsulta' => 120, 'estado' => 'ACTIVO']);
        }
        $alias = Service::create(['nombre' => 'REEVALUACIÓN FICTICIA ' . ($foot ? 'PIE' : 'TRAUMA'), 'specialty_id' => $catalog['specialty']->id, 'estado' => 'ACTIVO', 'service_type' => 'REEVALUATION', 'pricing_policy' => 'PROFESSIONAL', 'canonical_service_id' => $catalog['service']->id]);
        config(['scheduling.quiroz_referral' => ['doctor_id' => $catalog['doctor']->id, 'service_ids' => [$catalog['service']->id], 'additional_rate_ids' => []]]);
        $date = $catalog['schedule']->fecha_cita;
        $sourceDate = \Carbon\Carbon::parse($date)->subDays($days)->toDateString();
        $this->travelTo(\Carbon\Carbon::parse($date . ' 12:00', 'America/Lima'));
        $source = $this->lifecycleAppointment($actor, $catalog, ['service_id' => $catalog['service']->id, 'estado_cita' => 'ATENDIDO', 'estado_agenda' => 'LEGADO', 'fecha_cita' => $sourceDate, 'hora_cita' => '07:00'], $patient);
        $before = $source->fresh()->getAttributes();
        $path = 'qa/order-' . Str::uuid() . '.pdf';
        Storage::disk('local')->put($path, 'Orden escrita completamente ficticia');
        $doc = $source->documents()->create(['type' => 'OTHER', 'label' => 'Orden ficticia', 'private_path' => $path, 'actor_user_id' => $actor->id]);
        $i = app(ReevaluationIndicationService::class)->record($source->id, $actor, ['request_key' => (string) Str::uuid(), 'evidence_document_id' => $doc->id, 'evidence_type' => 'WRITTEN_ORDER', 'indicated_on' => $sourceDate, 'instruction_reference' => 'QA UNICA', 'doctor_order_attested' => true]);
        $data = ['doctor_id' => $catalog['doctor']->id, 'service_id' => $entry === 'legacy' ? $catalog['doctorService']->id : $catalog['service']->id, 'patient_id' => $patient->id, 'fecha_cita' => $date, 'price_modality' => 'REEVALUATION', 'commercial_service_id' => $alias->id, 'reevaluation_indication_id' => $i->id];
        if ($referred) {
            $data['referral'] = ['origin' => 'Derivación QA acreditada', 'attested' => true];
        }
        $price = $days === 0 ? 0 : ($referred ? $days <= 15 ? 220 : 250 : ($days <= 15 ? 120 : 150));
        $this->actingAs($actor)->postJson($entry === 'legacy' ? '/api/appointment/calculated' : '/scheduling-mvp/agenda/quote', $data)->assertOk()->assertJsonPath('tipo', $days <= 15 ? 'RECONSULTA' : 'PRIMERA_CONSULTA');
        $data += ['request_key' => (string) Str::uuid(), 'mode' => 'RESERVE', 'booking_type' => 'REGULAR', 'hora_cita' => '08:00', 'duracion_cita' => 30];
        $this->postJson($entry === 'legacy' ? '/admissionist/appointment/store' : '/scheduling-mvp/agenda/registrations', $data)->assertSuccessful();
        $a = Appointment::where('id', '<>', $source->id)->firstOrFail();
        $this->assertEquals($price, $a->precio_programado);
        $this->assertEquals($catalog['service']->id, $a->service_id);
        $this->assertEquals($alias->id, $a->commercial_service_id);
        $this->assertSame($before, $source->fresh()->getAttributes());
        $this->assertDatabaseCount('payments', 0);
        $this->postJson('/scheduling-mvp/agenda/quote', array_replace($data, ['service_id' => $catalog['service']->id]))->assertUnprocessable();
    }
    public static function cases(): array
    {
        $r = [];
        foreach (['operational', 'legacy'] as $entry) {
            foreach ([0, 1, 15, 16] as $days) {
                foreach ([false, true] as $foot) {
                    foreach ([false, true] as $ref) {
                        $r[] = [$entry, $days, $foot, $ref];
                    }
                }
            }
        }
        return $r;
    }
    public function test_linking_alias_with_historical_references_is_blocked_and_readers_keep_history(): void
    {
        $admin = $this->createUserWithRole('ADMINISTRADOR');
        $c = $this->createAppointmentCatalog();
        $c['service']->update(['service_type' => 'CONSULTATION', 'pricing_policy' => 'PROFESSIONAL']);
        $s = Service::create(['nombre' => 'REEVALUACION QA', 'specialty_id' => $c['specialty']->id, 'estado' => 'ACTIVO']);
        $history = $this->lifecycleAppointment($admin, $c, ['service_id' => $s->id]);
        $before = $history->fresh()->getAttributes();
        $this->actingAs($admin)->putJson('/master/admin/service/update', ['service_id_edit' => $s->id, 'nombre_edit' => $s->nombre, 'specialty_id_edit' => $s->specialty_id, 'service_type' => 'REEVALUATION', 'pricing_policy' => 'PROFESSIONAL', 'canonical_service_id' => $c['service']->id, 'catalog_version' => 0])->assertUnprocessable()->assertJsonValidationErrors('canonical_service_id');
        $this->assertNull($s->fresh()->canonical_service_id);
        $this->assertSame($before, $history->fresh()->getAttributes());
    }
}
