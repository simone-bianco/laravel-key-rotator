<?php

namespace SimoneBianco\LaravelKeyRotator\Enums;

enum BaseLimitType: string
{
    case FIXED = 'fixed';
    case UNLIMITED = 'unlimited';
    case NONE = 'none';

    public static function values(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }
}
