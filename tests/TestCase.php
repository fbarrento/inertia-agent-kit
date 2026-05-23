<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Application;
use InertiaAgentKit\InertiaAgentKitServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            InertiaAgentKitServiceProvider::class,
        ];
    }
}
