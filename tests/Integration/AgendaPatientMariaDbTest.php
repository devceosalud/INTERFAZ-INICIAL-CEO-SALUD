<?php
namespace Tests\Integration;

/** Module regression on the same dedicated QA instance. */
class AgendaPatientMariaDbTest extends \Tests\Feature\Patients\OperationalPatientModuleTest
{
    public function createApplication() { return \Tests\Support\IsolatedAgendaQa::boot(); }
}
