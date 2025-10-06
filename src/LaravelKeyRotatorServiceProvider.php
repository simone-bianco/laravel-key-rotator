<?php

namespace SimoneBianco\LaravelKeyRotator;

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
            ->hasCommands([])
            ->hasInstallCommand(function(InstallCommand $command) {
                $command
                    ->publishConfigFile()
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
