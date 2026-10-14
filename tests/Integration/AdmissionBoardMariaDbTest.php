<?php
namespace Tests\Integration;
use Tests\Feature\Scheduling\AdmissionBoardTest;
use Tests\Support\IsolatedAgendaQa;
class AdmissionBoardMariaDbTest extends AdmissionBoardTest
{
    public function createApplication() { return IsolatedAgendaQa::boot(); }
}
