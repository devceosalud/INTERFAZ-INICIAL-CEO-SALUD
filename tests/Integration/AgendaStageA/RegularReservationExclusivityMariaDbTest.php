<?php
namespace Tests\Integration\AgendaStageA;
class RegularReservationExclusivityMariaDbTest extends \Tests\Feature\Scheduling\RegularReservationExclusivityTest
{ public function createApplication(){return \Tests\Support\IsolatedAgendaStageAQa::boot();} }
