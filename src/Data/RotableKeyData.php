<?php

namespace SimoneBianco\LaravelKeyRotator\Data;

use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class RotableKeyData extends Data
{
    public function __construct(
        public ?string $service = null,
        public ?string $key = null,
        public string  $base_limit_type = 'none',
        public ?float  $max_base_usage = null,
        public float   $current_base_usage = 0,
        public string  $free_limit_type = 'none',
        public ?float  $max_free_usage = null,
        public float   $current_free_usage = 0,
        public ?Carbon $free_usage_resets_at = null,
        public string  $reset_timezone = 'UTC',
        public ?array  $extra_data = [],
        public bool    $is_active = true,
        public bool    $is_depleted = false,
        public ?string $depleted_at = null,
        public ?string $created_at = null,
        public ?string $updated_at = null,
        public ?string $last_used_at = null,
    ) {}
}
