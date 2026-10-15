<?php
namespace Tests\Integration;
class OperationalDocumentsMariaDbTest extends \Tests\Feature\Scheduling\OperationalDocumentsTest
{
    public function createApplication(){return \Tests\Support\IsolatedAgendaQa::boot();}
}
