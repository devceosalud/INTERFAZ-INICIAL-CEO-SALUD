<?php
namespace Tests\Integration\AgendaStageA;
class AgendaBoardMariaDbTest extends \Tests\Feature\Scheduling\AgendaBoardTest
{ public function createApplication(){return \Tests\Support\IsolatedAgendaStageAQa::boot();} }
