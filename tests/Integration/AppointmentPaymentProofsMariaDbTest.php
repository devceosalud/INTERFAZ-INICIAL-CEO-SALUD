<?php
namespace Tests\Integration;
class AppointmentPaymentProofsMariaDbTest extends \Tests\Feature\Scheduling\AppointmentPaymentProofsTest
{
    public function createApplication() { return \Tests\Support\IsolatedAgendaQa::boot(); }
    public function refreshDatabase(): void
    {
        // Retry tests must own the outer transaction, as HTTP requests do. No enclosing test savepoint.
        $this->artisan('migrate:fresh',['--force'=>true])->assertExitCode(0);
        $this->beforeApplicationDestroyed(function(): void {
            $this->artisan('migrate:fresh',['--force'=>true])->assertExitCode(0);
            \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated=false;
        });
    }
}
