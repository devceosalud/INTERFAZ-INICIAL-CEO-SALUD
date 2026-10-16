<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    private bool $catalogMariaDdlSeen=false;

    protected function setUp(): void
    {
        parent::setUp();
        if(($_ENV['CATALOG_QA_RUNTIME']??getenv('CATALOG_QA_RUNTIME'))==='catalog-11.8.9') {
            $this->catalogMariaDdlSeen=\Illuminate\Support\Facades\DB::transactionLevel()===0;
            \Illuminate\Support\Facades\DB::listen(function($query){
                if(preg_match('/^\s*(?:create|alter|drop|truncate|rename)\b/i',$query->sql))$this->catalogMariaDdlSeen=true;
            });
        }

        // Separate fake-file roots when independent SQLite/MariaDB suites share a checkout.
        // This does not enable Laravel parallel database naming or change any DB connection.
        \Illuminate\Support\Facades\ParallelTesting::resolveTokenUsing(
            static fn () => $_SERVER['TEST_TOKEN'] ?? 'ceosalud_'.getmypid()
        );

        Http::preventStrayRequests();
        Mail::fake();
        Queue::fake();
        Notification::fake();
    }

    protected function tearDown():void
    {
        // MariaDB DDL commits implicitly: never carry those fixtures into the next test.
        if($this->catalogMariaDdlSeen)\Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated=false;
        parent::tearDown();
    }
}
