<?php

namespace SimoneBianco\LaravelKeyRotator;

use SimoneBianco\LaravelKeyRotator\Console\Commands\MakeKeyRotatorCommand;
use SimoneBianco\LaravelKeyRotator\Console\Commands\ResetFreeUsageCommand;
use SimoneBianco\LaravelKeyRotator\Console\Commands\ResetUsageCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\LaravelPackageTools\Commands\InstallCommand;

class LaravelKeyRotatorServiceProvider extends PackageServiceProvider
{
    /**
     * @param Package $package
     * @return void
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-key-rotator')
            ->hasConfigFile('laravel-key-rotator')
            ->hasMigration('create_rotable_api_keys_table')
            ->hasCommands([
                MakeKeyRotatorCommand::class,
                ResetUsageCommand::class,
                ResetFreeUsageCommand::class,
            ])
            ->hasInstallCommand(function(InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('simone-bianco/laravel-key-rotator');
            });
    }

    /**
     * @return void
     */
    public function packageRegistered(): void
    {
    }
}
