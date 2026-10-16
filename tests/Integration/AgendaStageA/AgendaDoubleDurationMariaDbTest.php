<?php
namespace Tests\Integration\AgendaStageA;
class AgendaDoubleDurationMariaDbTest extends \Tests\Feature\Scheduling\AgendaDoubleDurationTest
{ public function createApplication(){return \Tests\Support\IsolatedAgendaStageAQa::boot();} }
