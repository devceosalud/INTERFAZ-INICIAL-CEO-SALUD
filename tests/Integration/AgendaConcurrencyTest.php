<?php
namespace Tests\Integration;

use App\Models\Appointment;
use App\Models\Cashier;
use App\Models\CashierShift;
use App\Models\Payment;
use App\Models\VoucherSerie;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Symfony\Component\Process\Process;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\Support\IsolatedAgendaQa;
use Tests\TestCase;

/** Fixtures are committed so independent PHP workers exercise real concurrent transactions. */
class AgendaConcurrencyTest extends TestCase
{
    use BuildsAgendaLifecycleData;
    private $actor; private $patient; private $catalog;
    public function createApplication() { return IsolatedAgendaQa::boot(); }
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        $this->actor = $this->agendaReader('ADMISION');
        foreach ([C::CREATE, C::SUBMIT_PAYMENT] as $name) { $this->actor->givePermissionTo(Permission::findOrCreate($name, 'web')); }
        $this->patient = $this->createPatient($this->actor);
        $this->catalog = $this->createAppointmentCatalog();
        $this->catalog['rate']->update(['nombre' => 'TARIFA ESTANDAR', 'tipo_tarifa' => 'MONTO_FIJO']);
        $box = Cashier::create(['nombre' => 'QA concurrency only', 'estado' => 'ACTIVO']);
        CashierShift::create(['cashier_id' => $box->id, 'user_id' => $this->actor->id, 'monto_apertura' => 0, 'abierto_en' => now(), 'estado' => 'ABIERTO']);
        VoucherSerie::create(['cashier_id' => $box->id, 'tipo_comprobante' => 'TICKET', 'serie' => 'QA01', 'correlativo_actual' => 0, 'estado' => 'ACTIVO']);
        $this->actingAs($this->actor);
    }
    private function payload(array $changes = []): array
    {
        return array_replace(['request_key' => (string) Str::uuid(), 'mode' => 'RESERVE', 'booking_type' => 'REGULAR',
            'patient_id' => $this->patient->id, 'doctor_id' => $this->catalog['doctor']->id, 'service_id' => $this->catalog['service']->id,
            'fecha_cita' => $this->catalog['schedule']->fecha_cita, 'hora_cita' => '08:00', 'duracion_cita' => 30], $changes);
    }
    private function race(string $path, array $payloads, array $actors = []): array
    {
        $dir = storage_path('app/qa-final/races/'.Str::uuid()); mkdir($dir, 0777, true);
        $release = $dir.'/release'; $processes = [];
        foreach ($payloads as $i => $payload) {
            $input = $dir.'/'.$i.'.input.json'; $output = $dir.'/'.$i.'.output.json';
            file_put_contents($input, json_encode(['actor' => $actors[$i] ?? $this->actor->id, 'release' => $release, 'path' => $path, 'payload' => $payload]));
            $process = new Process([PHP_BINARY, base_path('tests/Support/agenda-qa-request.php'), $input, $output], base_path(), null, null, 30);
            $process->start(); $processes[] = [$process, $output];
        }
        try {
            $deadline = microtime(true) + 15;
            do {
                $ready = true;
                foreach ($processes as [$process, $output]) { $ready = $ready && file_exists($output.'.ready'); }
                if ($ready) { break; } usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertTrue($ready, 'Workers did not reach the QA barrier.'); touch($release);
            $results = [];
            foreach ($processes as [$process, $output]) {
                $process->wait(); $this->assertTrue($process->isSuccessful(), $process->getOutput().$process->getErrorOutput());
                $results[] = json_decode(file_get_contents($output), true, 512, JSON_THROW_ON_ERROR);
            }
            return $results;
        } finally { foreach ($processes as [$process]) { if ($process->isRunning()) { $process->stop(); } } }
    }
    private function statuses(array $results): array { $statuses = array_column($results, 'status'); sort($statuses); return $statuses; }
    private function legacyPayload(array $data): array
    {
        return ['request_key' => $data['request_key'], 'mode' => $data['mode'], 'patient_id' => $data['patient_id'],
            'doctor_id' => $data['doctor_id'], 'service_id' => $this->catalog['doctorService']->id,
            'additional_rate_id' => $this->catalog['rate']->id, 'fecha_cita' => $data['fecha_cita'], 'hora_cita' => $data['hora_cita'],
            'total_pagado' => $data['payment']['amount'] ?? '0', 'metodo_pago' => $data['payment']['method'] ?? 'EFECTIVO',
            'numero_operacion' => $data['payment']['operation'] ?? null, 'entidad_origen' => $data['payment']['origin'] ?? null,
            'precio_programado' => '100.00'];
    }
    public function test_legacy_concurrent_retry_creates_one_payment_and_one_confirmation(): void
    {
        $data = $this->legacyPayload($this->payload(['mode' => 'CONFIRM', 'payment' => ['amount' => '50.00', 'method' => 'EFECTIVO']]));
        $results = $this->race('/admissionist/appointment/store', [$data, $data, $data, $data]);
        $this->assertSame([200, 200, 200, 200], $this->statuses($results));
        $this->assertDatabaseCount('appointments', 1); $this->assertDatabaseCount('payments', 1);
        $this->assertEquals(100, Appointment::first()->precio_programado); $this->assertEquals(50, Payment::sum('monto'));
        $this->assertSame(1, Appointment::occupyingInterval()->count());
    }
    public function test_legacy_competing_patients_cannot_confirm_the_same_slot(): void
    {
        $other = $this->createPatient($this->actor, ['numero_identidad' => '70000198', 'historia_clinica' => 'QA-LEGACY-OTHER']);
        $a = $this->legacyPayload($this->payload(['mode' => 'CONFIRM', 'payment' => ['amount' => '50.00', 'method' => 'EFECTIVO']]));
        $b = array_replace($a, ['patient_id' => $other->id, 'request_key' => (string) Str::uuid()]);
        $this->assertSame([200, 409], $this->statuses($this->race('/admissionist/appointment/store', [$a, $b])));
        $this->assertDatabaseCount('appointments', 1); $this->assertDatabaseCount('payments', 1);
    }
    public function test_legacy_bank_operation_with_different_keys_is_not_charged_twice(): void
    {
        $other = $this->createPatient($this->actor, ['numero_identidad' => '70000197', 'historia_clinica' => 'QA-LEGACY-BANK']);
        $a = $this->legacyPayload($this->payload(['payment' => ['amount' => '50.00', 'method' => 'YAPE', 'operation' => 'QA-LEGACY-BANK', 'origin' => 'YAPE']]));
        $b = array_replace($a, ['patient_id' => $other->id, 'hora_cita' => '09:00', 'request_key' => (string) Str::uuid()]);
        $this->assertSame([200, 422], $this->statuses($this->race('/admissionist/appointment/store', [$a, $b])));
        $this->assertDatabaseCount('appointments', 1); $this->assertDatabaseCount('payments', 1);
    }
    public function test_simultaneous_double_click_with_same_key_creates_one_paid_confirmation(): void
    {
        $data = $this->payload(['mode' => 'CONFIRM', 'payment' => ['amount' => '50.00', 'method' => 'EFECTIVO']]);
        $results = $this->race('/scheduling-mvp/agenda/appointments', [$data, $data, $data, $data]);
        $this->assertSame([201, 201, 201, 201], $this->statuses($results));
        $this->assertCount(1, array_unique(array_map(fn ($r) => $r['body']['appointment']['appointment_id'], $results)));
        $this->assertDatabaseCount('appointments', 1); $this->assertDatabaseCount('payments', 1); $this->assertDatabaseCount('vouchers', 1);
        $this->assertEquals(50, Payment::sum('monto')); $this->assertSame(1, Appointment::consumingRegularSlot()->count());
    }
    public function test_different_keys_cannot_create_duplicate_reservations_for_same_patient_and_interval(): void
    {
        $results = $this->race('/scheduling-mvp/agenda/appointments', [$this->payload(), $this->payload()]);
        $this->assertSame([201, 422], $this->statuses($results));
        $this->assertDatabaseCount('appointments', 1); $this->assertDatabaseCount('payments', 0);
        $this->assertSame(0, Appointment::occupyingInterval()->count());
    }
    public function test_competing_patients_cannot_both_confirm_the_same_regular_interval(): void
    {
        $other = $this->createPatient($this->actor, ['numero_identidad' => '70000199', 'historia_clinica' => 'QA-OTHER']);
        $a = $this->payload(['mode' => 'CONFIRM', 'payment' => ['amount' => '50.00', 'method' => 'EFECTIVO']]);
        $b = array_replace($a, ['request_key' => (string) Str::uuid(), 'patient_id' => $other->id]);
        $this->assertSame([201, 409], $this->statuses($this->race('/scheduling-mvp/agenda/appointments', [$a, $b])));
        $this->assertDatabaseCount('appointments', 1); $this->assertDatabaseCount('payments', 1); $this->assertDatabaseCount('vouchers', 1);
    }
    public function test_simultaneous_payment_retry_records_only_one_advance_and_leaves_reservation_pending(): void
    {
        $id = $this->postJson('/scheduling-mvp/agenda/appointments', $this->payload())->assertCreated()->json('appointment.appointment_id');
        $data = ['request_key' => (string) Str::uuid(), 'payment' => ['amount' => '50.00', 'method' => 'EFECTIVO']];
        $this->assertSame([200, 200], $this->statuses($this->race('/scheduling-mvp/agenda/appointments/'.$id.'/payments', [$data, $data])));
        $this->assertDatabaseCount('payments', 1); $this->assertEquals(50, Payment::sum('monto'));
        $this->assertSame('PENDIENTE_CONFIRMACION', Appointment::findOrFail($id)->estado_agenda);
    }
    public function test_same_bank_operation_with_distinct_request_keys_cannot_record_money_twice(): void
    {
        $id = $this->postJson('/scheduling-mvp/agenda/appointments', $this->payload())->assertCreated()->json('appointment.appointment_id');
        $a = ['request_key' => (string) Str::uuid(), 'confirm' => false,
            'payment' => ['amount' => '50.00', 'method' => 'YAPE', 'operation' => 'QA-CONCURRENT-ONLY', 'origin' => 'YAPE']];
        $b = array_replace($a, ['request_key' => (string) Str::uuid()]);
        $this->assertSame([200, 422], $this->statuses($this->race('/scheduling-mvp/agenda/appointments/'.$id.'/payments', [$a, $b])));
        $this->assertDatabaseCount('payments', 1); $this->assertEquals(50, Payment::sum('monto'));
    }
    public function test_same_uuid_with_competing_payloads_preserves_first_hash_and_never_charges_twice(): void
    {
        $a = $this->payload(['mode' => 'CONFIRM', 'payment' => ['amount' => '50.00', 'method' => 'EFECTIVO']]);
        $b = array_replace($a, ['payment' => ['amount' => '60.00', 'method' => 'EFECTIVO']]);
        $this->assertSame([201, 409], $this->statuses($this->race('/scheduling-mvp/agenda/appointments', [$a, $b])));
        $this->assertDatabaseCount('appointments', 1); $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('appointment_operations', 1); $this->assertContains((float) Payment::sum('monto'), [50.0, 60.0]);
    }

    public function test_parallel_confirmation_of_an_occupied_reservation_has_no_partial_effects(): void
    {
        $this->assertFalse($this->actor->can(C::CREATE_ADDITIONAL));
        $id = $this->postJson('/scheduling-mvp/agenda/appointments', $this->payload())->assertCreated()->json('appointment.appointment_id');
        $other = $this->createPatient($this->actor, ['numero_identidad' => '70000987', 'historia_clinica' => 'QA-OCCUPIED']);
        $occupied = $this->postJson('/scheduling-mvp/agenda/appointments', $this->payload(['patient_id' => $other->id, 'mode' => 'CONFIRM',
            'payment' => ['amount' => '50.00', 'method' => 'EFECTIVO']]))->assertCreated()->json('appointment.appointment_id');
        $tables = ['appointments', 'payments', 'vouchers', 'voucher_items', 'voucher_series', 'appointment_operations', 'appointment_events', 'cashier_shifts'];
        $before = []; foreach ($tables as $table) { $before[$table] = DB::table($table)->orderBy('id')->get()->toJson(); }
        $data = ['request_key' => (string) Str::uuid(), 'confirm' => true, 'payment' => ['amount' => '50.00', 'method' => 'EFECTIVO']];
        $path = '/scheduling-mvp/agenda/appointments/'.$id.'/payments';
        $this->assertSame([409, 409], $this->statuses($this->race($path, [$data, $data])));
        foreach ($tables as $table) { $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->toJson(), $table); }
        $data['request_key'] = (string) Str::uuid(); unset($data['confirm']);
        $this->assertSame([200, 200], $this->statuses($this->race($path, [$data, $data])));
        $this->assertDatabaseCount('payments', 2); $this->assertDatabaseCount('vouchers', 2); $this->assertEquals(100, Payment::sum('monto'));
        $this->assertSame('PENDIENTE_CONFIRMACION', Appointment::findOrFail($id)->estado_agenda);
        $this->assertSame('REGULAR', Appointment::findOrFail($id)->tipo_agendamiento);
        $this->assertSame([$occupied], Appointment::consumingRegularSlot()->pluck('id')->all());
    }

    public function test_parallel_direct_requests_without_create_permission_make_no_writes(): void
    {
        $this->actor->revokePermissionTo(C::CREATE);
        $this->assertSame([403, 403], $this->statuses($this->race('/scheduling-mvp/agenda/appointments', [$this->payload(), $this->payload()])));
        foreach (['appointments', 'payments', 'vouchers', 'appointment_operations'] as $table) { $this->assertDatabaseCount($table, 0); }
    }
    public function test_two_workers_with_different_doctors_cannot_bind_one_indication_twice():void
    {
        $cap=\App\Services\Scheduling\ReevaluationIndicationService::CAPABILITY;
        $this->actor->givePermissionTo(Permission::findOrCreate($cap,'web'));
        $source=$this->lifecycleAppointment($this->actor,$this->catalog,['estado_cita'=>'ATENDIDO','estado_agenda'=>'LEGADO','fecha_cita'=>now()->subDay()->toDateString(),'hora_cita'=>'08:00'],$this->patient);
        $path='qa-indications/'.Str::uuid().'.txt';
        \Illuminate\Support\Facades\Storage::disk('local')->put($path,'Indicación escrita exclusivamente ficticia QA');
        $doc=$source->documents()->create(['type'=>'PAYMENT_PROOF','label'=>'Orden médica ficticia','private_path'=>$path,'mime'=>'application/pdf','actor_user_id'=>$this->actor->id]);
        $indication=app(\App\Services\Scheduling\ReevaluationIndicationService::class)->record($source->id,$this->actor,
            ['request_key'=>(string)Str::uuid(),'evidence_document_id'=>$doc->id,'evidence_type'=>'WRITTEN_ORDER','indicated_on'=>now('America/Lima')->toDateString(),'instruction_reference'=>'Folio QA n.º 1','doctor_order_attested'=>true]);
        $otherWorker=$this->agendaReader('COMERCIAL');
        $otherWorker->givePermissionTo(Permission::findOrCreate(C::CREATE,'web'));
        $other=$this->createAppointmentCatalog();
        \App\Models\DoctorService::create(['doctor_id'=>$other['doctor']->id,'service_id'=>$this->catalog['service']->id,
            'precio_primera_consulta'=>100,'precio_reconsulta'=>50,'dias_reconsulta'=>15,'estado'=>'ACTIVO']);
        $a=$this->payload(['reevaluation_indication_id'=>$indication->id,'hora_cita'=>'09:00']);
        $b=array_replace($a,['request_key'=>(string)Str::uuid(),'doctor_id'=>$other['doctor']->id]);
        $results=$this->race('/scheduling-mvp/agenda/registrations',[$a,$b],[$this->actor->id,$otherWorker->id]);
        $this->assertSame([201,422],$this->statuses($results));
        $this->assertDatabaseCount('appointments',2);$this->assertDatabaseCount('payments',0);
        $this->assertNotNull($indication->fresh()->bound_appointment_id);
        $this->assertSame(1,DB::table('appointment_events')->where('event_type','INDICACION_REEVALUACION_VINCULADA')->count());
    }

}
