<?php
namespace Tests\Database;
/** Run only with phpunit.waiting-room-mariadb.xml; inherits the same official service tests. */
class WaitingRoomMariaDbTest extends \Tests\Feature\Integration\WaitingRoomSnapshotTest
{
    public function createApplication(){return \Tests\Support\WaitingRoomMariaDbQa::boot();}
}
