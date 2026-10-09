<?php
namespace Tests\Feature\Scheduling;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

class PilotDiagnosisTest extends TestCase
{
    use RefreshDatabase, BuildsAgendaLifecycleData;

    public function test_diagnosis_reads_effective_flags_and_capabilities_without_secrets_or_writes(): void
    {
        $this->withoutMockingConsoleOutput();
        $actor = $this->agendaReader();
        config(['apidatosperu.reniec_provider' => 'factiliza', 'apidatosperu.factiliza.token' => 'DO-NOT-EXPOSE',
            'scheduling.pilot_payment_without_manual_cash_shift' => true]);
        $before = [];
        foreach (['users', 'payments', 'cashier_shifts', 'vouchers', 'appointment_pilot_cash_contexts'] as $table) { $before[$table] = DB::table($table)->count(); }
        Artisan::call('pilot:diagnose', ['--user' => (string) $actor->id]);
        $output = Artisan::output(); $result = json_decode($output, true);
        $this->assertTrue($result['factiliza_token_present']);
        $this->assertTrue($result['pilot_cash_enabled']);
        $this->assertFalse($result['operator']['capabilities']['appointment.payment.submit']);
        $this->assertStringNotContainsString('DO-NOT-EXPOSE', $output);
        $this->assertStringNotContainsString($actor->email, $output);
        foreach ($before as $table => $count) { $this->assertSame($count, DB::table($table)->count()); }
        Http::assertNothingSent();
    }
}
