<?php

namespace SimoneBianco\LaravelKeyRotator\Contracts;

use SimoneBianco\LaravelKeyRotator\Models\RotableKey;

interface RotableDriverInterface
{
    public function rotate(bool $canRepickSelf = true): ?RotableKey;
    public function registerUsage(float $usage = 1): ?RotableKey;
    public function rollbackUsage(float $usage = 1): ?RotableKey;
}
