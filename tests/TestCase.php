<?php

namespace SimoneBianco\LaravelKeyRotator\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use SimoneBianco\LaravelKeyRotator\LaravelKeyRotatorServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    protected function getPackageProviders($app): array
    {
        return [
            \Spatie\LaravelData\LaravelDataServiceProvider::class,
            LaravelKeyRotatorServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
        $app['config']->set('app.cipher', 'AES-256-CBC');

        // Set up the key rotator config
        $app['config']->set('laravel-key-rotator.encrypt_keys', false);
    }

    protected function setUpDatabase(): void
    {
        // Run the package migration
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}

