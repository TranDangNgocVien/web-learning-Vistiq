<?php

namespace Tests\Learner;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    use \Tests\Concerns\AssertsCompactStorage;

    public function createApplication()
    {
        $_SERVER['HTTP_X_VISTIQ_SURFACE'] = 'learner';
        unset($_SERVER['REQUEST_URI']);

        $app = parent::createApplication();
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Tests require an isolated SQLite in-memory database.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    //
}
