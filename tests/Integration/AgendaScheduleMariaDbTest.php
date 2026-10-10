<?php
namespace Tests\Integration;

/** Module regression on the same dedicated QA instance. */
class AgendaScheduleMariaDbTest extends \Tests\Feature\Scheduling\DoctorScheduleWorkspaceTest
{
    public function createApplication() { return \Tests\Support\IsolatedAgendaQa::boot(); }
}
