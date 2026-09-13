<?php

namespace SimoneBianco\LaravelKeyRotator;

use SimoneBianco\LaravelKeyRotator\Console\Commands\MakeKeyRotatorCommand;
use SimoneBianco\LaravelKeyRotator\Console\Commands\ResetFreeUsageCommand;
use SimoneBianco\LaravelKeyRotator\Console\Commands\ResetUsageCommand;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelKeyRotatorServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('key-rotator')
            ->hasConfigFile('key-rotator')
            ->hasMigrations([
                'create_rotable_api_keys_table',
                'add_soft_deletes_to_rotable_api_keys_table',
            ])
            ->hasCommands([
                MakeKeyRotatorCommand::class,
                ResetUsageCommand::class,
                ResetFreeUsageCommand::class,
            ])
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('simone-bianco/laravel-key-rotator');
            });
    }

    public function packageRegistered(): void {}
}
