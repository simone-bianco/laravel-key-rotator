<?php

namespace SimoneBianco\LaravelKeyRotator\Enums;

enum FreeLimitType: string
{
    case DAILY = 'daily';
    case MONTHLY = 'monthly';
    case NONE = 'none';

    public static function values(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }
}
