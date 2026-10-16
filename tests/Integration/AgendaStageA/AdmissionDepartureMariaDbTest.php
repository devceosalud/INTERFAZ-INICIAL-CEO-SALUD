<?php
namespace Tests\Integration\AgendaStageA;
class AdmissionDepartureMariaDbTest extends \Tests\Feature\Scheduling\AdmissionDepartureTest
{ public function createApplication(){return \Tests\Support\IsolatedAgendaStageAQa::boot();} }
