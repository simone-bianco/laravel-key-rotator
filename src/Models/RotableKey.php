<?php

namespace SimoneBianco\LaravelKeyRotator\Models;

use Illuminate\Database\Eloquent\Model;

class RotableKey extends Model
{
    protected $fillable = [
        'name',
        'alias',
        'config_key',
        'config_value',
        'enabled',
        'max_usage',
        'current_usage',
        'daily_reset',
        'current_usage',
        'group'
    ];

    protected $casts = [
        'enabled' => 'boolean'
    ];

    protected static function boot()
    {
        parent::boot();

        static::retrieved(function (RotableKey $model) {
            if ($model->daily_reset && $model->updated_at->isBefore(now()->startOfDay())) {
                $model->current_usage = 0;
                $model->save();
            }
        });
    }

    public function incrementUsage(int $by = 1): void
    {
        $this->increment('current_usage', $by);
    }

    public function rollbackUsage(int $by = 1): void
    {
        $this->decrement('current_usage', $by);
    }
}
