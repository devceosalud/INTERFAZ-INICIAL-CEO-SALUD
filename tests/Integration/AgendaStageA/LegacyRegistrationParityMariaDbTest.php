<?php
namespace Tests\Integration\AgendaStageA;
class LegacyRegistrationParityMariaDbTest extends \Tests\Feature\Scheduling\LegacyRegistrationParityTest
{ public function createApplication(){return \Tests\Support\IsolatedAgendaStageAQa::boot();} }
