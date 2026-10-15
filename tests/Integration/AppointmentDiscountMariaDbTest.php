<?php
namespace Tests\Integration;
use Tests\Feature\Scheduling\AppointmentDiscountRegistrationTest;
use Tests\Support\IsolatedAgendaQa;
class AppointmentDiscountMariaDbTest extends AppointmentDiscountRegistrationTest
{
    public function createApplication() { return IsolatedAgendaQa::boot(); }
}
