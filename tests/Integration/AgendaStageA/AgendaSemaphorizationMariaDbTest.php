<?php
namespace Tests\Integration\AgendaStageA;
class AgendaSemaphorizationMariaDbTest extends \Tests\Feature\Scheduling\AgendaSemaphorizationTest
{public function createApplication(){return \Tests\Support\IsolatedAgendaStageAQa::boot();}}