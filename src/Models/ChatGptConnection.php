<?php

declare(strict_types=1);

namespace SimoneBianco\LaravelKeyRotator\Models;

use Illuminate\Database\Eloquent\Model;

final class ChatGptConnection extends Model
{
    protected $table = 'rotable_chatgpt_connections';

    protected $hidden = ['tokens'];

    public function getConnectionName()
    {
        return config('laravel-key-rotator.database_connection') ?? parent::getConnectionName();
    }

    protected function casts(): array
    {
        return [
            'tokens' => 'encrypted:array',
            'expires_at' => 'immutable_datetime',
            'retry_after' => 'immutable_datetime',
            'refresh_retry_after' => 'immutable_datetime',
            'scopes' => 'array',
        ];
    }
}
