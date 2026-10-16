<?php
namespace Tests\Integration\AgendaStageA;
class AppointmentDiscountRegistrationMariaDbTest extends \Tests\Feature\Scheduling\AppointmentDiscountRegistrationTest
{ public function createApplication(){return \Tests\Support\IsolatedAgendaStageAQa::boot();} }
