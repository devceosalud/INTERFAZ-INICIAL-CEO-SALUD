<?php
namespace Tests\Integration;

use Tests\Feature\Scheduling\AgendaStabilizationTest;
use Tests\Support\IsolatedAgendaQa;

/** Re-run the actual endpoint/permission/payment cases against MariaDB/InnoDB. */
class AgendaMariaDbTest extends AgendaStabilizationTest
{
    public function createApplication() { return IsolatedAgendaQa::boot(); }
}
