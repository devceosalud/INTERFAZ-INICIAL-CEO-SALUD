<?php
namespace Tests\Integration;
class ReevaluationIndicationMariaDbTest extends \Tests\Feature\Scheduling\ReevaluationIndicationTest {
 public function createApplication() { return \Tests\Support\IsolatedAgendaQa::boot(); }
}
