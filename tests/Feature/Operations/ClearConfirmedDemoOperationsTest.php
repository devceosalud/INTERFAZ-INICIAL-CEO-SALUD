<?php

namespace Tests\Feature\Operations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

class ClearConfirmedDemoOperationsTest extends TestCase
{
    use RefreshDatabase, BuildsAgendaLifecycleData;

    private function shift(int $user): int
    {
        $cashier = DB::table('cashiers')->insertGetId([
            'nombre' => 'Caja ficticia',
            'estado' => 'ACTIVO',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return DB::table('cashier_shifts')->insertGetId([
            'cashier_id' => $cashier, 'user_id' => $user,
            'monto_apertura' => 0, 'abierto_en' => now(), 'estado' => 'ABIERTO',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function voucher(int $patient, int $user, int $shift, string $type, string $serie, bool $sunat): int
    {
        return DB::table('vouchers')->insertGetId([
            'tipo_comprobante' => $type, 'serie' => $serie, 'correlativo' => 1,
            'patient_id' => $patient, 'user_id' => $user, 'cashier_shift_id' => $shift,
            'subtotal' => 50, 'igv' => 0, 'total' => 50,
            'estado' => 'PAGADO', 'condicion_pago' => 'CONTADO',
            'requiere_sunat' => $sunat, 'estado_sunat' => $sunat ? 'PENDIENTE' : 'NO_APLICA',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_deletes_only_fictitious_non_fiscal_rows_without_touching_catalogs_users_or_series(): void
    {
        $actor = $this->agendaReader('ADMINISTRADOR');
        $catalog = $this->createAppointmentCatalog();
        $demo = $this->lifecycleAppointment($actor, $catalog);
        $fiscal = $this->lifecycleAppointment($actor, $catalog);
        $demoShift = $this->shift($actor->id);
        $fiscalShift = $this->shift($actor->id);
        $demoVoucher = $this->voucher($demo->patient_id, $actor->id, $demoShift, 'TICKET', 'TDEM', false);
        $fiscalVoucher = $this->voucher($fiscal->patient_id, $actor->id, $fiscalShift, 'BOLETA', 'BFIS', true);
        foreach ([$demoVoucher => $demo->id, $fiscalVoucher => $fiscal->id] as $vid => $aid) {
            DB::table('voucher_items')->insert([
                'voucher_id' => $vid, 'item_id' => $aid, 'item_type' => 'cita',
                'descripcion' => 'Consulta ficticia', 'precio_unitario' => 50, 'total' => 50,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('payments')->insert([
            'voucher_id' => $demoVoucher, 'metodo_pago' => 'EFECTIVO', 'monto' => 50,
            'user_id' => $actor->id, 'cashier_shift_id' => $demoShift,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('appointment_admission_tasks')->insertGetId([
            'appointment_id' => $demo->id, 'created_by_user_id' => $actor->id,
            'request_key' => '20000000-0000-4000-8000-000000000001',
            'payload_hash' => str_repeat('a', 64), 'body' => 'Recordatorio ficticio',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('ceo:clear-confirmed-demo', [
            '--execute' => true, '--confirm' => 'INVALIDO',
        ])->assertFailed();
        $this->assertDatabaseHas('patients', ['id' => $demo->patient_id]);

        $this->artisan('ceo:clear-confirmed-demo', [
            '--execute' => true, '--confirm' => 'LIMPIAR-DATOS-FICTICIOS-CEO',
        ])->assertSuccessful();

        $this->assertDatabaseMissing('patients', ['id' => $demo->patient_id]);
        $this->assertDatabaseMissing('appointments', ['id' => $demo->id]);
        $this->assertDatabaseMissing('vouchers', ['id' => $demoVoucher]);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('appointment_admission_tasks', 0);
        $this->assertDatabaseMissing('cashier_shifts', ['id' => $demoShift]);

        $this->assertDatabaseHas('patients', ['id' => $fiscal->patient_id]);
        $this->assertDatabaseHas('appointments', ['id' => $fiscal->id]);
        $this->assertDatabaseHas('vouchers', ['id' => $fiscalVoucher, 'estado_sunat' => 'PENDIENTE']);
        $this->assertDatabaseHas('cashier_shifts', ['id' => $fiscalShift]);
        $this->assertDatabaseHas('users', ['id' => $actor->id]);
        $this->assertDatabaseHas('doctors', ['id' => $catalog['doctor']->id]);
        $this->assertDatabaseHas('services', ['id' => $catalog['service']->id]);
    }
}
