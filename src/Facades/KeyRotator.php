<?php

namespace SimoneBianco\LaravelKeyRotator\Facades;

use Illuminate\Support\Facades\Facade;
use SimoneBianco\LaravelKeyRotator\KeyRotator as RulesInstance;

class KeyRotator extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-key-rotator.factory';
    }
}
