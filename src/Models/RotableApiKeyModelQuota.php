<?php

declare(strict_types=1);

namespace SimoneBianco\LaravelKeyRotator\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RotableApiKeyModelQuota extends Model
{
    protected $table = 'rotable_api_key_model_quotas';

    protected $fillable = [
        'rotable_api_key_id',
        'model',
        'free_limit_type',
        'max_free_usage',
        'current_free_usage',
        'free_usage_resets_at',
        'last_free_usage_reset_at',
        'reset_timezone',
    ];

    protected $casts = [
        'max_free_usage' => 'float',
        'current_free_usage' => 'float',
        'free_usage_resets_at' => 'datetime',
        'last_free_usage_reset_at' => 'datetime',
    ];

    public function getConnection()
    {
        return config('laravel-key-rotator.database_connection') ?? parent::getConnection();
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(RotableApiKey::class, 'rotable_api_key_id');
    }

    public function remainingFreeUsage(): float
    {
        if ($this->free_limit_type === 'none' || $this->max_free_usage === null) {
            return 0.0;
        }

        return max(0.0, (float) $this->max_free_usage - (float) $this->current_free_usage);
    }

    public function canServe(float $threshold = 0.0): bool
    {
        if ($this->free_limit_type === 'none' || $this->max_free_usage === null) {
            return false;
        }

        return $this->remainingFreeUsage() >= max(0.0, $threshold);
    }
}
