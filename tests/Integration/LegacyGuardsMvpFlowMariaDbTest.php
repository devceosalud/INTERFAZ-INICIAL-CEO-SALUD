<?php
namespace Tests\Integration;
use Tests\Feature\Scheduling\LegacyGuardsMvpFlowTest;
use Tests\Support\IsolatedAgendaQa;
class LegacyGuardsMvpFlowMariaDbTest extends LegacyGuardsMvpFlowTest
{
    public function createApplication() { return IsolatedAgendaQa::boot(); }
}
