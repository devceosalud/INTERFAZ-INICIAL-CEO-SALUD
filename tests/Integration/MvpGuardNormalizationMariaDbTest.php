<?php
namespace Tests\Integration;
use Tests\Feature\Scheduling\MvpGuardNormalizationTest;
use Tests\Support\IsolatedAgendaQa;
class MvpGuardNormalizationMariaDbTest extends MvpGuardNormalizationTest
{
    public function createApplication() { return IsolatedAgendaQa::boot(); }
}
