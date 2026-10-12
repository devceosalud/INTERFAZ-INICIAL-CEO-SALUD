<?php
namespace Tests\Integration;

use Tests\Feature\Scheduling\LegacyRegistrationParityTest;
use Tests\Support\IsolatedAgendaQa;

/** Repeats both entrypoint contracts on the guarded, disposable local MariaDB instance. */
class LegacyRegistrationMariaDbTest extends LegacyRegistrationParityTest
{
    public function createApplication() { return IsolatedAgendaQa::boot(); }
}
