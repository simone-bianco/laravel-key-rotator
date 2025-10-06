<?php

namespace SimoneBianco\LaravelKeyRotator;


use Illuminate\Support\Facades\Cache;
use SimoneBianco\LaravelKeyRotator\Exceptions\NoAvailableKeysException;
use SimoneBianco\LaravelKeyRotator\Models\RotableKey;

class KeyRotator
{
    protected string $group = 'default';

    protected ?RotableKey $rotableKey = null;

    public static function make(string $group = 'default'): static
    {
        $instance = new static();
        $instance->group = $group;
        return $instance;
    }

    public function rotate(bool $canRepickSelf = true): ?RotableKey
    {
        return Cache::lock("laravel-key-rotator-$this->group", config('laravel-key-rotator.timeout'))->get(function() use ($canRepickSelf) {
            $this->rotableKey = RotableKey::where('group', $this->group)
                ->where('enabled', true)
                ->whereColumn('current_usage', '<', 'max_usage')
                ->when($this->rotableKey && !$canRepickSelf, function ($query) {
                    $query->where('id', '!=', $this->rotableKey->id);
                })
                ->inRandomOrder()
                ->first();

            if (!$this->rotableKey) {
                throw new NoAvailableKeysException("No available keys for group {$this->group}");
            }

            config([$this->rotableKey->config_key => $this->rotableKey->config_value]);
        });
    }

    public function execute(callable $callback): mixed
    {
        if (!$this->rotableKey) {
            $this->rotate();
        }

        try {
            $result = $callback();
            $this->rotableKey->incrementUsage();
            return $result;
        } catch (\Exception $e) {
            $this->rotableKey->rollbackUsage();
            throw $e;
        }
    }
}
