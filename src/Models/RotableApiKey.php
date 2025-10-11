<?php

namespace SimoneBianco\LaravelKeyRotator\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class RotableApiKey extends Model
{
    protected $fillable = [
        'service',
        'key',
        'base_limit_type',
        'max_base_usage',
        'current_base_usage',
        'free_limit_type',
        'max_free_usage',
        'current_free_usage',
        'free_usage_resets_at',
        'last_free_usage_reset_at',
        'reset_timezone',
        'extra_data',
        'is_active',
        'is_depleted',
        'depleted_at',
        'last_used_at',
    ];

    protected $casts = [
        'extra_data' => 'array',
        'is_active' => 'boolean',
        'is_depleted' => 'boolean',
        'depleted_at' => 'datetime',
        'free_usage_resets_at' => 'datetime',
        'last_free_usage_reset_at' => 'datetime',
        'last_used_at' => 'datetime',
        'max_base_usage' => 'decimal:8',
        'current_base_usage' => 'decimal:8',
        'max_free_usage' => 'decimal:8',
        'current_free_usage' => 'decimal:8',
    ];

    public function getConnection()
    {
        return config('laravel-key-rotator.database_connection', parent::getConnection());
    }

    public function setKeyAttribute(string $value): void
    {
        if (config('laravel-key-rotator.encrypt_keys', true)) {
            $value = Crypt::encryptString($value);
        }

        $this->attributes['key'] = $value;
    }

    public function getKeyAttribute(string $value): string
    {
        if (!config('laravel-key-rotator.encrypt_keys', true)) {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (Exception $e) {
            return "Error decrypting key: $value";
        }
    }
}
