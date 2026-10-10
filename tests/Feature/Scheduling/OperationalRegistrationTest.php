<?php
namespace Tests\Feature\Scheduling;

use App\Models\Appointment;
use App\Models\Cashier;
use App\Models\CashierShift;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Voucher;
use App\Models\VoucherSerie;
use App\Services\Billing\AppointmentEconomicPosition;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

class OperationalRegistrationTest extends TestCase
{
    use RefreshDatabase, BuildsAgendaLifecycleData;
    private $actor; private $patient; private $catalog;
    protected function setUp(): void
    {
        parent::setUp(); config(['scheduling.enabled' => true, 'app.debug' => false]);
        $this->actor = $this->actor('ADMISION'); $this->patient = $this->createPatient($this->actor);
        $this->catalog = $this->createAppointmentCatalog();
        $this->catalog['rate']->update(['nombre' => 'TARIFA ESTANDAR', 'tipo_tarifa' => 'MONTO_FIJO']);
        $this->actingAs($this->actor);
    }
    private function actor(string $role)
    {
        $u = $this->agendaReader($role);
        foreach ([C::CREATE, C::SUBMIT_PAYMENT, C::CREATE_ADDITIONAL] as $p) { $u->givePermissionTo(Permission::findOrCreate($p, 'web')); }
        return $u;
    }
    private function shift($actor): void
    {
        $c = Cashier::create(['nombre' => 'Caja ficticia '.$actor->id, 'estado' => 'ACTIVO']);
        CashierShift::create(['cashier_id' => $c->id, 'user_id' => $actor->id, 'monto_apertura' => 0, 'abierto_en' => now(), 'estado' => 'ABIERTO']);
        VoucherSerie::create(['cashier_id' => $c->id, 'tipo_comprobante' => 'TICKET', 'serie' => 'T'.str_pad($actor->id, 3, '0', STR_PAD_LEFT), 'correlativo_actual' => 0, 'estado' => 'ACTIVO']);
    }
    private function payload(array $changes = []): array
    {
        return array_replace(['request_key' => (string) Str::uuid(), 'mode' => 'RESERVE', 'booking_type' => 'REGULAR',
            'patient_id' => $this->patient->id, 'doctor_id' => $this->catalog['doctor']->id, 'service_id' => $this->catalog['service']->id,
            'fecha_cita' => $this->catalog['schedule']->fecha_cita, 'hora_cita' => '08:00', 'duracion_cita' => 30,
            'motivo_consulta' => 'Consulta operativa ficticia', 'observaciones' => 'Seguimiento ficticio'], $changes);
    }
    private function create(array $p) { return $this->postJson(route('scheduling.mvp.agenda.registrations'), $p); }
    private function pay(int $id, array $data) { return $this->postJson(route('scheduling.mvp.agenda.payments', $id), $data); }

    public function test_new_operational_patient_requires_main_phone_and_rolls_back_patient_and_reservation(): void
    {
        $new = ['tipo_identificacion' => 'DNI', 'numero_identidad' => '70000901', 'nombre' => 'QA',
            'apellido_paterno' => 'LOCAL', 'apellido_materno' => 'FICTICIO', 'genero' => 'HOMBRE'];
        $before = Patient::count();
        $this->create($this->payload(['patient_id' => null, 'patient' => $new]))->assertUnprocessable()
            ->assertJsonValidationErrors('patient.telefono_numero');
        $this->assertSame($before, Patient::count()); $this->assertDatabaseCount('appointments', 0);
        $new['telefono_numero'] = '999000001'; $new['telefono_prefijo'] = '+51';
        $this->create($this->payload(['patient_id' => null, 'patient' => $new]))->assertCreated();
    }

    public function test_same_patient_active_interval_rejects_new_uuid_but_released_states_allow_another_reservation(): void
    {
        $id = $this->create($this->payload())->assertCreated()->json('appointment.appointment_id');
        Appointment::find($id)->update(['hora_cita' => '08:00:00']);
        $this->create($this->payload())->assertUnprocessable()->assertJsonPath('message', 'Este paciente ya tiene una cita o reserva en esta hora.');
        $this->assertDatabaseCount('appointments', 1);
        foreach (['RETIRO', 'CANCELADO', 'NO_ASISTIO'] as $released) {
            // Isolated historical state fixture; withdrawal mutation itself is tested through its workflow.
            \Illuminate\Support\Facades\DB::table('appointments')->where('id', $id)->update(['estado_cita' => $released]);
            $id = $this->create($this->payload())->assertCreated()->json('appointment.appointment_id');
        }
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_private_reservation_freezes_price_and_owner_without_consuming_slot_or_allowing_spoofing(): void
    {
        $p = $this->payload(); $id = $this->create($p)->assertCreated()->json('appointment.appointment_id');
        $a = Appointment::findOrFail($id);
        $this->assertSame('PROGRAMADO', $a->estado_cita); $this->assertSame('PENDIENTE_CONFIRMACION', $a->estado_agenda);
        $this->assertSame('REGULAR', $a->tipo_agendamiento); $this->assertSame($this->actor->id, (int) $a->responsible_user_id);
        $this->assertSame('Consulta operativa ficticia', $a->motivo_consulta); $this->assertEquals(100, $a->precio_programado);
        $this->assertSame(0, Appointment::consumingRegularSlot()->count());
        $this->create($p)->assertCreated(); $this->assertDatabaseCount('appointments', 1);
        $other = $this->actor('COMERCIAL');
        $this->create($this->payload(['responsible_user_id' => $other->id]))->assertForbidden();
        $this->actingAs($other)->getJson(route('scheduling.mvp.agenda.economy', $id))->assertNotFound();
        $this->getJson(route('scheduling.mvp.agenda.economy', 999999))->assertNotFound();
        $this->create($this->payload())->assertUnprocessable()->assertJsonPath('message', 'Este paciente ya tiene una cita o reserva en esta hora.');
        $otherPatient = $this->createPatient($other, ['numero_identidad' => '70000902', 'historia_clinica' => 'QA-2']);
        $this->create($this->payload(['patient_id' => $otherPatient->id]))->assertCreated(); $this->assertDatabaseCount('appointments', 2);
    }
    public function test_delegated_private_owner_is_responsible_with_no_administrator_visibility_bypass(): void
    {
        $commercial = $this->actor('COMERCIAL'); $admin = $this->actor('ADMINISTRADOR');
        $admin->givePermissionTo(Permission::findOrCreate(C::ASSIGN_RESPONSIBLE, 'web'));
        $this->shift($admin);
        $id = $this->actingAs($admin)->create($this->payload(['responsible_user_id' => $commercial->id, 'payment' => ['amount' => '50.00', 'method' => 'EFECTIVO']]))->assertCreated()->json('appointment.appointment_id');
        $this->getJson(route('scheduling.mvp.agenda.economy', $id))->assertNotFound();
        $this->assertSame(0, Voucher::visibleToAgendaUser($admin->id)->count());
        $this->actingAs($commercial)->getJson(route('scheduling.mvp.agenda.economy', $id))->assertOk();
        $this->assertSame(1, Voucher::visibleToAgendaUser($commercial->id)->count());
        $fallback = $this->actingAs($admin)->create($this->payload(['hora_cita' => '08:30']))->assertCreated()->json('appointment.appointment_id');
        $this->getJson(route('scheduling.mvp.agenda.economy', $fallback))->assertOk();
    }
    public function test_real_ticket_payment_is_idempotent_and_cash_does_not_invent_operation_number(): void
    {
        $this->shift($this->actor);
        $p = $this->payload(['mode' => 'CONFIRM', 'payment' => ['amount' => '50.00', 'method' => 'EFECTIVO'], 'precio_programado' => 1]);
        $id = $this->create($p)->assertCreated()->assertJsonPath('economy.pago_real', '50.00')->assertJsonPath('economy.saldo', '50.00')->json('appointment.appointment_id');
        $this->create($p)->assertCreated();
        $this->assertDatabaseCount('payments', 1); $this->assertDatabaseCount('vouchers', 1);
        $this->assertNull(Payment::first()->numero_operacion); $this->assertEquals(100, Appointment::find($id)->precio_programado);
        $this->assertSame('CONFIRMADA', Appointment::find($id)->estado_agenda);
        $this->assertSame('PAYMENTS', app(AppointmentEconomicPosition::class)->forAppointment(Appointment::find($id))['authority']);
    }
    public function test_under_fifty_payment_stays_private_and_confirmation_adds_only_the_missing_money(): void
    {
        $this->shift($this->actor);
        $id = $this->create($this->payload(['payment' => ['amount' => '40.00', 'method' => 'YAPE', 'operation' => 'FICTICIA-40']]))
            ->assertCreated()->assertJsonPath('economy.asegurada', false)->json('appointment.appointment_id');
        $this->pay($id, ['request_key' => (string) Str::uuid(), 'confirm' => true])->assertUnprocessable();
        $this->assertEquals(40, Payment::sum('monto'));
        $this->pay($id, ['request_key' => (string) Str::uuid(), 'confirm' => false,
            'payment' => ['amount' => '1.00', 'method' => 'YAPE', 'operation' => 'FICTICIA-40']])->assertUnprocessable();
        $this->assertEquals(40, Payment::sum('monto'));
        $key = (string) Str::uuid(); $payment = ['request_key' => $key, 'confirm' => true, 'payment' => ['amount' => '10.00', 'method' => 'EFECTIVO']];
        $this->pay($id, $payment)->assertOk()->assertJsonPath('appointment.estado_agenda', 'CONFIRMADA');
        $this->pay($id, $payment)->assertOk(); $this->assertEquals(50, Payment::sum('monto')); $this->assertDatabaseCount('payments', 2);
    }
    public function test_two_paid_regular_reservations_never_convert_implicitly_even_with_additional_permission(): void
    {
        $this->shift($this->actor); $other = $this->actor('COMERCIAL'); $this->shift($other);
        $payment = ['amount' => '50.00', 'method' => 'EFECTIVO'];
        $one = $this->create($this->payload(['payment' => $payment]))->assertCreated()->json('appointment.appointment_id');
        $otherPatient = $this->createPatient($other, ['numero_identidad' => '70000903', 'historia_clinica' => 'QA-3']);
        $two = $this->actingAs($other)->create($this->payload(['patient_id' => $otherPatient->id, 'payment' => $payment]))->assertCreated()->json('appointment.appointment_id');
        $this->actingAs($this->actor)->pay($one, ['request_key' => (string) Str::uuid(), 'confirm' => true])->assertOk()->assertJsonPath('appointment.tipo_agendamiento', 'REGULAR');
        $before = Appointment::findOrFail($two)->getAttributes();
        $this->actingAs($other)->pay($two, ['request_key' => (string) Str::uuid(), 'confirm' => true])->assertConflict();
        $this->assertSame($before, Appointment::findOrFail($two)->getAttributes());
        $this->assertDatabaseCount('payments', 2);
        $this->assertSame(1, Appointment::consumingRegularSlot()->count()); $this->assertEquals(100, Payment::sum('monto'));
    }
    public function test_missing_shift_operation_or_insufficient_confirmation_rolls_back_every_write(): void
    {
        $p = $this->payload(['mode' => 'CONFIRM', 'payment' => ['amount' => '50.00', 'method' => 'YAPE']]);
        $this->create($p)->assertUnprocessable(); $this->assertDatabaseCount('appointments', 0);
        $this->shift($this->actor); $this->create($p)->assertUnprocessable();
        $this->create($this->payload(['mode' => 'CONFIRM', 'payment' => ['amount' => '49.00', 'method' => 'EFECTIVO']]))->assertUnprocessable();
        $this->assertDatabaseCount('payments', 0); $this->assertDatabaseCount('vouchers', 0); $this->assertDatabaseCount('appointments', 0);
    }
    public function test_authorization_is_not_money_and_requires_explicit_capability(): void
    {
        $p = $this->payload(['mode' => 'CONFIRM', 'es_exonerado' => true, 'autorizado_por' => 'DR QUIROZ']);
        $this->create($p)->assertForbidden();
        $this->actor->givePermissionTo(Permission::findOrCreate(C::APPROVE_ZERO_COST, 'web'));
        $this->create($p)->assertCreated()->assertJsonPath('economy.pago_real', '0.00')->assertJsonPath('economy.saldo', '0.00')->assertJsonPath('economy.asegurada', false);
        $this->assertDatabaseCount('payments', 0);
    }
    public function test_patient_capture_and_new_patient_are_atomic_with_unique_document_and_current_hce(): void
    {
        $channel = \App\Models\Channel::create(['nombre' => 'CEO SALUD TEST', 'estado' => 'ACTIVO']);
        $medium = \App\Models\InteractionMedium::create(['nombre' => 'WhatsApp TEST', 'estado' => 'ACTIVO']);
        $new = ['tipo_identificacion' => 'DNI', 'numero_identidad' => '70000888', 'genero' => 'HOMBRE', 'nombre' => 'PRUEBA', 'apellido_paterno' => 'OPERATIVA',
            'apellido_materno' => 'LOCAL', 'telefono' => '+51999888777', 'telefono_secundario' => '+51999111222', 'channel_id' => $channel->id, 'interaction_medium_id' => $medium->id];
        $p = $this->payload(['patient_id' => null, 'patient' => $new]);
        $this->create($p)->assertCreated()->assertJsonPath('patient.telefono_secundario', '+51999111222');
        $saved = Patient::where('numero_identidad', '70000888')->firstOrFail(); $this->assertNotEmpty($saved->historia_clinica);
        $this->create($this->payload(['patient_id' => null, 'patient' => $new]))->assertUnprocessable();
        $this->create($this->payload(['patient_capture' => ['telefono_secundario' => '+51999111444', 'channel_id' => $channel->id, 'interaction_medium_id' => $medium->id]]))->assertCreated();
        $this->assertSame('+51999111444', $this->patient->fresh()->telefono_secundario);
    }
    public function test_blank_capture_fields_do_not_erase_existing_patient_data_on_registration(): void
    {
        $channel = \App\Models\Channel::create(['nombre' => 'CEO TEST', 'estado' => 'ACTIVO']);
        $this->patient->update(['telefono' => '+51999000001', 'telefono_secundario' => '+51999000002', 'channel_id' => $channel->id]);
        $this->create($this->payload(['patient_capture' => [
            'telefono' => '', 'telefono_secundario' => null, 'channel_id' => null,
        ]]))->assertCreated();
        $patient = $this->patient->fresh();
        $this->assertSame('+51999000001', $patient->telefono);
        $this->assertSame('+51999000002', $patient->telefono_secundario);
        $this->assertEquals($channel->id, $patient->channel_id);
        $this->assertDatabaseCount('patients', 1);
        $this->assertDatabaseCount('appointments', 1);
    }
    public function test_private_proof_download_and_external_links_reject_pii_exposure_and_unsafe_content(): void
    {
        Storage::fake('local');
        $id = $this->create($this->payload())->assertCreated()->json('appointment.appointment_id');
        $route = route('scheduling.mvp.documents.store', $id);
        $link = $this->postJson($route, ['label' => 'Drive de prueba', 'url' => 'https://drive.google.com/file/test'])->assertCreated()->json('id');
        $this->postJson($route, ['label' => 'Inseguro', 'url' => 'javascript:alert(1)'])->assertUnprocessable();
        $this->putJson(route('scheduling.mvp.documents.update', [$id, $link]), ['label' => 'Editado', 'url' => 'https://docs.google.com/document/test'])->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'proof'); file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aHYsAAAAASUVORK5CYII='));
        $file = new UploadedFile($path, 'untrusted.exe', null, null, true);
        $proof = $this->post($route, ['label' => 'Prueba', 'file' => $file], ['Accept' => 'application/json'])->assertCreated()->json('id');
        $d = \App\Models\AppointmentDocument::findOrFail($proof);
        $this->assertStringStartsWith('appointment-documents/', $d->private_path); $this->assertStringEndsWith('.png', $d->private_path);
        $this->get(route('scheduling.mvp.documents.download', [$id, $proof]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $other = $this->actor('COMERCIAL'); $this->actingAs($other)->getJson(route('scheduling.mvp.documents.index', $id))->assertNotFound();
        $this->getJson(route('scheduling.mvp.documents.download', [$id, $proof]))->assertNotFound();
        $this->getJson(route('patients.operational.appointment-documents', $this->patient->id))->assertOk()->assertJsonCount(0, 'appointments');
        $this->actingAs($this->actor)->getJson(route('patients.operational.appointment-documents', $this->patient->id))->assertOk()->assertJsonCount(1, 'appointments');
        $this->actingAs($this->actor)->deleteJson(route('scheduling.mvp.documents.destroy', [$id, $link]))->assertNoContent();
        $this->assertSoftDeleted('appointment_documents', ['id' => $link]);
        unlink($path);
        $bad = UploadedFile::fake()->createWithContent('pretend.jpg', '<?php echo "not an image";');
        $this->post($route, ['label' => 'Malicioso', 'file' => $bad], ['Accept' => 'application/json'])->assertUnprocessable();
    }
    public function test_real_money_overrides_snapshot_and_multi_item_finance_is_not_guessed(): void
    {
        $this->shift($this->actor); $id = $this->create($this->payload(['payment' => ['amount' => '40.00', 'method' => 'EFECTIVO']]))->assertCreated()->json('appointment.appointment_id');
        $a = Appointment::find($id); $a->update(['total_pagado' => 99, 'estado_agenda' => 'CONFIRMADA']);
        $p = app(AppointmentEconomicPosition::class)->forAppointment($a); $this->assertSame(4000, $p['paid_cents']); $this->assertFalse($p['secured']);
        Voucher::first()->items()->create(['item_type' => 'item', 'item_id' => 999, 'descripcion' => 'Otra línea', 'cantidad' => 1, 'precio_unitario' => 1, 'total' => 1, 'afectacion_igv' => '10']);
        $this->assertTrue(app(AppointmentEconomicPosition::class)->forAppointment($a)['allocation_required']);
        $this->pay($id, ['request_key' => (string) Str::uuid(), 'payment' => ['amount' => '1.00', 'method' => 'EFECTIVO']])->assertUnprocessable();
        $this->assertEquals(40, Payment::sum('monto'));
    }
    public function test_payment_and_private_proof_are_atomic_and_idempotent_in_pilot_mode(): void
    {
        Storage::fake('local'); config(['scheduling.pilot_payment_without_manual_cash_shift' => true]);
        $id = $this->create($this->payload())->assertCreated()->json('appointment.appointment_id');
        $path = tempnam(sys_get_temp_dir(), 'proof');
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aHYsAAAAASUVORK5CYII='));
        try {
            $payload = ['request_key' => (string) Str::uuid(), 'confirm' => false, 'payment' => ['amount' => '50.00', 'method' => 'YAPE', 'operation' => 'LOCAL-PROOF-50']];
            $url = route('scheduling.mvp.agenda.payments', $id);
            for ($i = 0; $i < 2; $i++) {
                $this->post($url, ['payload' => json_encode($payload), 'proof' => new UploadedFile($path, 'untrusted.exe', null, null, true)], ['Accept' => 'application/json'])
                    ->assertOk()->assertJsonPath('economy.pago_real', '50.00');
            }
            $this->assertDatabaseCount('payments', 1); $this->assertDatabaseCount('appointment_documents', 1);
            $document = \App\Models\AppointmentDocument::firstOrFail();
            $this->assertSame($id, (int) $document->appointment_id); $this->assertSame('image/png', $document->mime);
            Storage::disk('local')->assertExists($document->private_path);
            $this->assertCount(1, Storage::disk('local')->allFiles('appointment-documents'));
            $payload['request_key'] = (string) Str::uuid(); $payload['payment']['operation'] = 'LOCAL-BAD-PROOF';
            $this->post($url, ['payload' => json_encode($payload), 'proof' => UploadedFile::fake()->createWithContent('bad.png', '<?php invalid')], ['Accept' => 'application/json'])->assertUnprocessable();
            $this->assertEquals(50, Payment::sum('monto')); $this->assertDatabaseCount('appointment_documents', 1);
        } finally { unlink($path); }
    }

    public function test_failure_after_proof_storage_rolls_back_money_context_and_private_file(): void
    {
        Storage::fake('local'); config(['scheduling.pilot_payment_without_manual_cash_shift' => true]);
        $id = $this->create($this->payload())->assertCreated()->json('appointment.appointment_id');
        $path = tempnam(sys_get_temp_dir(), 'proof');
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aHYsAAAAASUVORK5CYII='));
        \App\Models\AppointmentEvent::creating(function ($event) {
            if ($event->event_type === 'PAGO_REGISTRADO') { throw new \RuntimeException('QA failure after storage'); }
        });
        try {
            $this->withoutExceptionHandling();
            try {
                $this->post(route('scheduling.mvp.agenda.payments', $id), ['payload' => json_encode(['request_key' => (string) Str::uuid(), 'confirm' => false,
                    'payment' => ['amount' => '50.00', 'method' => 'EFECTIVO']]), 'proof' => new UploadedFile($path, 'proof.png', null, null, true)], ['Accept' => 'application/json']);
                $this->fail('The simulated history failure must propagate.');
            } catch (\RuntimeException $error) { $this->assertSame('QA failure after storage', $error->getMessage()); }
            $this->assertDatabaseCount('payments', 0); $this->assertDatabaseCount('vouchers', 0);
            $this->assertDatabaseCount('appointment_pilot_cash_contexts', 0); $this->assertDatabaseCount('appointment_documents', 0);
            $this->assertSame([], Storage::disk('local')->allFiles('appointment-documents'));
            $this->assertEquals(0, Appointment::findOrFail($id)->total_pagado);
        } finally { \App\Models\AppointmentEvent::flushEventListeners(); unlink($path); }
    }

    public function test_a_second_private_reservation_on_the_same_hour_keeps_the_new_patient_and_does_not_consume_the_slot(): void
    {
        $first = $this->create($this->payload())->assertCreated()->json('appointment.appointment_id');
        $other = $this->createPatient($this->actor, [
            'historia_clinica' => 'HC-RESERVA-B',
            'numero_identidad' => '70000111',
            'email' => 'reserva-b@example.invalid',
            'nombre' => 'Paciente',
            'apellido_paterno' => 'Distinto',
        ]);
        $created = $this->create($this->payload(['patient_id' => $other->id]));
        $created->assertCreated()->assertJsonPath('appointment.estado_agenda', 'PENDIENTE_CONFIRMACION')->assertJsonPath('appointment.tipo_agendamiento', 'REGULAR');
        $second = Appointment::findOrFail($created->json('appointment.appointment_id'));
        $this->assertNotSame($first, $second->id);
        $this->assertSame($other->id, (int) $second->patient_id);
        $this->assertSame('70000111', $other->fresh()->numero_identidad);
        $this->assertSame(0, Appointment::consumingRegularSlot()->count());
    }

    public function test_an_additional_booking_stays_confirmed_without_consuming_the_regular_slot(): void
    {
        $this->create($this->payload())->assertCreated();
        $other = $this->createPatient($this->actor, [
            'historia_clinica' => 'HC-ADICIONAL-B',
            'numero_identidad' => '70000222',
            'email' => 'adicional-b@example.invalid',
        ]);
        $created = $this->create($this->payload([
            'patient_id' => $other->id,
            'mode' => 'CONFIRM',
            'booking_type' => 'ADICIONAL',
        ]));
        $created->assertCreated()->assertJsonPath('appointment.estado_agenda', 'CONFIRMADA')->assertJsonPath('appointment.tipo_agendamiento', 'ADICIONAL');
        $this->assertSame($other->id, (int) Appointment::findOrFail($created->json('appointment.appointment_id'))->patient_id);
        $this->assertSame(0, Appointment::consumingRegularSlot()->count());
    }

}
