<?php

namespace SimoneBianco\LaravelKeyRotator\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use SimoneBianco\LaravelKeyRotator\LaravelKeyRotatorServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelKeyRotatorServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Setup default configuration
        $app['config']->set('laravel-key-rotator.rules_path', 'tests/fixtures/rules');
        $app['config']->set('laravel-key-rotator.cache_enabled', true);
        $app['config']->set('laravel-key-rotator.cache_ttl', 3600);
    }
}

