<?php
namespace Tests\Integration\AgendaStageA;
class AdmissionBoardMariaDbTest extends \Tests\Feature\Scheduling\AdmissionBoardTest
{ public function createApplication(){return \Tests\Support\IsolatedAgendaStageAQa::boot();} }
