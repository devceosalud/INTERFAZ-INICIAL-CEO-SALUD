<?php
namespace Tests\Integration;
class DoctorScheduleSafetyMariaDbTest extends \Tests\Feature\Scheduling\DoctorScheduleSafetyTest
{
    public function createApplication(){return \Tests\Support\IsolatedAgendaQa::boot();}
}
