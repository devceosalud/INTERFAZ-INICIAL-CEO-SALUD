<?php
namespace Tests\Integration;
class WorkerAccessMariaDbTest extends \Tests\Feature\Auth\WorkerAccessTest
{
    public function createApplication(){return \Tests\Support\IsolatedWorkerQa::boot();}
    public function refreshDatabase():void
    {
        // Session-table and migration roundtrips must own DDL/transactions, as real requests do.
        $this->artisan('migrate:fresh',['--force'=>true])->assertExitCode(0);
        $this->beforeApplicationDestroyed(function():void{
            $this->artisan('migrate:fresh',['--force'=>true])->assertExitCode(0);
            \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated=false;
        });
    }
}
