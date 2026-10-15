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

    protected function setUp(): void
    {
        parent::setUp();

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
}
