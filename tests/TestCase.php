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

        Http::fake([
            '*' => Http::response(['message' => 'External HTTP disabled in tests'], 503),
        ]);
        Mail::fake();
        Queue::fake();
        Notification::fake();
    }
}
