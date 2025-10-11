<?php

namespace SimoneBianco\LaravelKeyRotator\Facades;

use Illuminate\Support\Facades\Facade;

class KeyRotator extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-key-rotator.factory';
    }
}
