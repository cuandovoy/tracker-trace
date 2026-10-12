<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        if (! $app->environment('testing') || config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'truck_tracker_test' || config('database.connections.mysql.username') !== 'truck_test') {
            throw new RuntimeException('Pruebas bloqueadas: se requiere la base truck_tracker_test y el usuario exclusivo truck_test. Usá docker compose run --rm tests.');
        }

        return $app;
    }
}
