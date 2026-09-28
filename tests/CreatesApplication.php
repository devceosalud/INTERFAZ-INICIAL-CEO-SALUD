<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $requiredEnvironment = [
            'APP_ENV' => 'testing',
            'APP_CONFIG_CACHE' => 'bootstrap/cache/testing-config.php',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
        ];

        foreach ($requiredEnvironment as $name => $expected) {
            $actual = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);

            if ($actual !== $expected) {
                throw new \RuntimeException(
                    "Test safety guard refused to bootstrap: {$name} must be {$expected}."
                );
            }
        }

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        if (!$app->environment('testing')
            || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException(
                'Test safety guard refused the configured environment: only in-memory SQLite is allowed.'
            );
        }

        return $app;
    }
}
