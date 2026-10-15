<?php
namespace Tests\Integration;
class MagdalenaSiteSetupMariaDbTest extends \Tests\Feature\Scheduling\MagdalenaSiteSetupTest
{
    public function createApplication(){return \Tests\Support\IsolatedAgendaQa::boot();}
}
