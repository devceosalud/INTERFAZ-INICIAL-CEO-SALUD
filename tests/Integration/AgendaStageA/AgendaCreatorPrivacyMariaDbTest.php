<?php
namespace Tests\Integration\AgendaStageA;
class AgendaCreatorPrivacyMariaDbTest extends \Tests\Feature\Scheduling\AgendaCreatorPrivacyTest
{ public function createApplication(){return \Tests\Support\IsolatedAgendaStageAQa::boot();} }
