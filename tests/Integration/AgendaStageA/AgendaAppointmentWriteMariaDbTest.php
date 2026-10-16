<?php
namespace Tests\Integration\AgendaStageA;
class AgendaAppointmentWriteMariaDbTest extends \Tests\Feature\Scheduling\AgendaAppointmentWriteTest
{ public function createApplication(){return \Tests\Support\IsolatedAgendaStageAQa::boot();} }
