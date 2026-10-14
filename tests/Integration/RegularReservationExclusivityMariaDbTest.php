<?php
namespace Tests\Integration;
class RegularReservationExclusivityMariaDbTest extends \Tests\Feature\Scheduling\RegularReservationExclusivityTest {
    public function createApplication(){return \Tests\Support\IsolatedAgendaQa::boot();}
}
