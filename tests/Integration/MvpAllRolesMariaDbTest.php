<?php
namespace Tests\Integration;
use Tests\Feature\Scheduling\MvpAllRolesFlowTest;
use Tests\Support\IsolatedAgendaQa;
class MvpAllRolesMariaDbTest extends MvpAllRolesFlowTest
{
    public function createApplication() { return IsolatedAgendaQa::boot(); }
}
