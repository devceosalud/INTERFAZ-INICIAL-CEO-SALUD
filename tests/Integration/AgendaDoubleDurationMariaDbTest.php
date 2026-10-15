<?php
namespace Tests\Integration;
class AgendaDoubleDurationMariaDbTest extends \Tests\Feature\Scheduling\AgendaDoubleDurationTest {
 public function createApplication(){return \Tests\Support\IsolatedAgendaQa::boot();}
}
