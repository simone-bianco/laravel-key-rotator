<?php

namespace SimoneBianco\LaravelKeyRotator;

use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use SimoneBianco\LaravelKeyRotator\Data\RotableKeyData;
use SimoneBianco\LaravelKeyRotator\Enums\BaseLimitType;
use SimoneBianco\LaravelKeyRotator\Enums\FreeLimitType;
use SimoneBianco\LaravelKeyRotator\Exceptions\NoAvailableKeysException;
use SimoneBianco\LaravelKeyRotator\Models\RotableApiKey;

/**
 * Abstract class for managing API key rotation.
 * Provides the base logic for selecting, using, and marking
 * depleted API keys, managing both free and paid usage pools.
 */
abstract class KeyRotator
{
    /**
     * The service name as stored in the database (e.g., 'openai').
     * @var string
     */
    protected static string $serviceName;

    /**
     * The Laravel configuration key(s) to override (e.g., 'services.openai.api_key').
     * Can be a single string or an array of keys to override with the same value.
     * @var string|array
     */
    protected static string|array $configKey;

    protected static string $baseLimitType = BaseLimitType::UNLIMITED->value; // 'fixed', 'unlimited', 'none'
    protected static string $freeLimitType = FreeLimitType::NONE->value;
    protected static float $maxBaseUsage = 0;
    protected static float $maxFreeUsage = 0;
    protected static ?Carbon $freeUsageResetsAt = null;
    protected static string $resetTimezone = 'UTC';

    protected ?RotableApiKey $currentKey = null;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        if (!static::$configKey) {
            throw new Exception("KeyRotator: Property 'configKey' must be defined in " . static::class);
        }

        if (!static::$serviceName) {
            throw new Exception("KeyRotator: Property 'serviceName' must be defined in " . static::class);
        }
    }

    /**
     * Get the currently selected API key.
     *
     * @return RotableApiKey|null The current key or null if no key is selected.
     */
    public function getCurrentKey(): ?RotableApiKey
    {
        return $this->currentKey;
    }

    /**
     * Register a new API key in the database for this service.
     *
     * This method creates a new rotable API key with the provided data or default values.
     * You can override this method to add custom validation or processing logic.
     *
     * @param RotableKeyData|null $data The key data to register. If null, uses default values.
     * @return RotableApiKey The created API key model instance.
     *
     * @example
     * ```php
     * // Basic usage with default values
     * $rotator = new OpenAIKeyRotator();
     * $key = $rotator->registerKey(new RotableKeyData(
     *     key: 'sk-...',
     *     max_base_usage: 100.0,
     *     base_limit_type: 'fixed'
     * ));
     *
     * // Override example with custom logic
     * public function registerKey(?RotableKeyData $data = null): RotableApiKey
     * {
     *     // Your custom validation logic here
     *     if ($data && !$this->validateApiKey($data->key)) {
     *         throw new InvalidKeyException('Invalid API key format');
     *     }
     *
     *     return parent::registerKey($data);
     * }
     * ```
     */
    public function registerKey(?RotableKeyData $data = null): RotableApiKey
    {
        $data ??= new RotableKeyData();

        $data->service = static::$serviceName;
        $data->base_limit_type ??= static::$baseLimitType;
        $data->free_limit_type ??= static::$freeLimitType;
        $data->max_base_usage ??= static::$maxBaseUsage;
        $data->max_free_usage ??= static::$maxFreeUsage;
        $data->free_usage_resets_at ??= static::$freeUsageResetsAt;
        $data->reset_timezone ??= static::$resetTimezone;

        return RotableApiKey::create($data->toArray());
    }

    /**
     * Search the database for a key that matches the one currently
     * present in the Laravel configuration file.
     *
     * @return RotableApiKey|null
     */
    protected static function getActiveKeyFromConfig(): ?RotableApiKey
    {
        // If $configKey is an array, use the first key for comparison
        $configKeyToCheck = is_array(static::$configKey) ? static::$configKey[0] : static::$configKey;
        $configKeyValue = Config::get($configKeyToCheck);

        if (!$configKeyValue) {
            return null;
        }

        $query = RotableApiKey::where('service', static::$serviceName)
            ->where('is_active', true)
            ->where('is_depleted', false);
        if (config('laravel-key-rotator.encrypt_keys', true) === false) {
            return $query->where('key', $configKeyValue)->first();
        }

        return $query->get()->first(fn ($key) => $key->getOriginal('key') === Crypt::encryptString($configKeyValue));
    }

    /**
     * Create a new instance of the key rotator.
     *
     * Factory method to instantiate the rotator. Useful for method chaining.
     *
     * @return static A new instance of the key rotator.
     *
     * @example
     * ```php
     * // Basic usage
     * $key = OpenAIKeyRotator::make()
     *     ->pickKey()
     *     ->injectKey();
     *
     * // Override example with custom initialization
     * public static function make(): static
     * {
     *     $instance = parent::make();
     *     // Your custom initialization logic here
     *     $instance->initializeCustomSettings();
     *     return $instance;
     * }
     * ```
     */
    public static function make(): static
    {
        return new static();
    }

    /**
     * Set the current key to be used by the rotator.
     *
     * This method manually sets the active key. Typically used when you want to
     * work with a specific key instead of letting the rotator pick one automatically.
     *
     * @param RotableApiKey $key The API key to set as current.
     * @return void
     *
     * @example
     * ```php
     * // Basic usage
     * $rotator = new OpenAIKeyRotator();
     * $specificKey = RotableApiKey::find(1);
     * $rotator->setKey($specificKey);
     * $rotator->injectKey();
     *
     * // Override example with custom logic
     * public function setKey(RotableApiKey $key): void
     * {
     *     // Your validation logic here
     *     if (!$key->is_active) {
     *         throw new InactiveKeyException('Cannot use inactive key');
     *     }
     *
     *     parent::setKey($key);
     * }
     * ```
     */
    public function setKey(RotableApiKey $key): void
    {
        $this->currentKey = $key;
    }

    protected static function cacheRotableKeyId(RotableApiKey $key): void
    {
        $service = static::$serviceName;
        Context::addHidden("rotable_api_key_id_$service", $key->id);
    }

    protected static function getCachedRotableKeyId(): ?int
    {
        $service = static::$serviceName;
        return Context::getHidden("rotable_api_key_id_$service");
    }

    protected function getCachedRotableKey(): ?RotableApiKey
    {
        $keyId = self::getCachedRotableKeyId();
        if ($keyId) {
            return RotableApiKey::find($keyId);
        }
        return null;
    }

    /**
     * Register usage for the last key that was used in the current request context.
     *
     * This static method retrieves the cached key ID from the request context and
     * registers usage for it. Useful when you need to track usage after an API call
     * without maintaining a reference to the rotator instance.
     *
     * @param float $usage The amount of usage to register (e.g., tokens consumed, API calls made).
     * @return void
     * @throws Exception If no cached key ID is found or the key doesn't exist.
     *
     * @example
     * ```php
     * // Basic usage - after making an API call
     * OpenAIKeyRotator::make()->pickKey()->injectKey();
     * $response = OpenAI::chat()->create([...]);
     *
     * // Later, register the usage
     * $tokensUsed = $response->usage->totalTokens;
     * OpenAIKeyRotator::registerUsageForLastUsedKey($tokensUsed);
     *
     * // Override example with custom logic
     * public static function registerUsageForLastUsedKey(float $usage): void
     * {
     *     // Your custom logic here (e.g., logging, notifications)
     *     Log::info("Registering usage: $usage");
     *
     *     parent::registerUsageForLastUsedKey($usage);
     * }
     * ```
     */
    public static function registerUsageForLastUsedKey(float $usage): void
    {
        $service = static::$serviceName;
        $keyId = self::getCachedRotableKeyId();
        if (!$keyId) {
            throw new Exception("KeyRotator: No cached RotableApiKey ID found for service '$service' when trying to register usage.");
        }

        $key = RotableApiKey::find($keyId);
        if (!$key) {
            throw new Exception("KeyRotator: No RotableApiKey found with ID $keyId for service '$service' when trying to register usage.");
        }

        $rotator = new static();
        $rotator->setKey($key);
        $rotator->registerUsage($usage);
    }

    /**
     * Pick the best available API key from the pool.
     *
     * Selects an active, non-depleted key with the most remaining usage capacity.
     * The selection prioritizes keys with the highest combined free and base usage remaining.
     * You can override this method to implement custom key selection logic.
     *
     * @return $this The rotator instance for method chaining.
     * @throws NoAvailableKeysException If no available keys are found.
     *
     * @example
     * ```php
     * // Basic usage
     * $rotator = OpenAIKeyRotator::make()
     *     ->pickKey()
     *     ->injectKey();
     *
     * // Override example with custom selection logic
     * public function pickKey(): static
     * {
     *     // Your custom logic here (e.g., prioritize keys by region)
     *     $nextKey = RotableApiKey::where('service', static::$serviceName)
     *         ->where('is_active', true)
     *         ->where('is_depleted', false)
     *         ->where('region', 'us-east-1') // Custom filter
     *         ->orderBy('current_base_usage', 'asc')
     *         ->first();
     *
     *     if (!$nextKey) {
     *         // Fallback to default logic
     *         return parent::pickKey();
     *     }
     *
     *     $this->currentKey = $nextKey;
     *     self::cacheRotableKeyId($nextKey);
     *     return $this;
     * }
     * ```
     */
    public function pickKey(): static
    {
        $nextKey = RotableApiKey::where('service', static::$serviceName)
            ->where('is_active', true)
            ->where('is_depleted', false)
            ->orderByRaw(
                '(COALESCE(max_free_usage, 0) - current_free_usage) + (COALESCE(max_base_usage, 0) - current_base_usage) DESC'
            )
            ->first();

        $serviceName = static::$serviceName;
        if (!$nextKey) {
            throw new NoAvailableKeysException("No available API keys for service '$serviceName'.");
        }

        $this->currentKey = $nextKey;
        self::cacheRotableKeyId($nextKey);

        return $this;
    }

    /**
     * Inject the selected API key into Laravel's configuration.
     *
     * Sets the current key in the Laravel config, making it available to your application.
     * Supports both single config key and multiple config keys (useful for services that
     * require the same key in multiple configuration locations).
     * You can override this method to inject additional configuration values.
     *
     * @return $this The rotator instance for method chaining.
     * @throws Exception If no key has been selected via pickKey() or setKey().
     *
     * @example
     * ```php
     * // Basic usage
     * OpenAIKeyRotator::make()
     *     ->pickKey()
     *     ->injectKey();
     *
     * // Now the key is available in config
     * $apiKey = config('services.openai.api_key');
     *
     * // Override example with extra_data injection
     * public function injectKey(): static
     * {
     *     parent::injectKey();
     *
     *     // Your custom logic here - inject additional config from extra_data
     *     if ($this->currentKey->extra_data) {
     *         if (isset($this->currentKey->extra_data['organization_id'])) {
     *             Config::set('services.openai.organization',
     *                 $this->currentKey->extra_data['organization_id']);
     *         }
     *         if (isset($this->currentKey->extra_data['project_id'])) {
     *             Config::set('services.openai.project',
     *                 $this->currentKey->extra_data['project_id']);
     *         }
     *     }
     *
     *     return $this;
     * }
     * ```
     */
    public function injectKey(): static
    {
        if (!$this->currentKey) {
            throw new Exception("KeyRotator: No key selected. Call pickKey() before injectKey().");
        }

        // Support both string and array of config keys
        $configKeys = is_array(static::$configKey) ? static::$configKey : [static::$configKey];

        foreach ($configKeys as $configKey) {
            Config::set($configKey, $this->currentKey->key);
        }

        return $this;
    }

    /**
     * Determine if an exception indicates that the API key's quota is exhausted.
     *
     * This method analyzes exception messages to detect quota/limit-related errors.
     * The default implementation checks for common keywords, but you should override
     * this method to provide service-specific detection logic for better accuracy.
     *
     * @param Exception $exception The exception thrown by the API client.
     * @return bool True if the exception is due to exhausted quota, otherwise false.
     *
     * @example
     * ```php
     * // Basic usage - the default implementation
     * try {
     *     $response = OpenAI::chat()->create([...]);
     * } catch (Exception $e) {
     *     $rotator = OpenAIKeyRotator::make();
     *     if ($rotator->isDepletedException($e)) {
     *         // Handle depletion - maybe rotate to another key
     *         $rotator->handleDepletedException($e);
     *     }
     * }
     *
     * // Override example with service-specific logic
     * public function isDepletedException(Exception $exception): bool
     * {
     *     // Your custom logic here for OpenAI-specific errors
     *     if ($exception instanceof OpenAI\Exceptions\RateLimitException) {
     *         return true;
     *     }
     *
     *     if ($exception->getCode() === 429) {
     *         return true;
     *     }
     *
     *     // Fallback to default keyword detection
     *     return parent::isDepletedException($exception);
     * }
     * ```
     */
    public function isDepletedException(Exception $exception): bool
    {
        $depletionKeywords = [
            'exceeded', 'quota', 'limit', 'rate',
            'subscription', 'billed', 'payment', 'card', 'insufficient'
        ];

        $message = strtolower($exception->getMessage());

        $foundKeywords = array_filter(
            $depletionKeywords,
            fn($keyword) => str_contains($message, $keyword)
        );

        return count($foundKeywords) >= 1;
    }

    /**
     * Register usage for the current API key.
     *
     * Tracks the usage of the current key, consuming from the free pool first,
     * then from the base pool. Automatically marks the key as depleted if both
     * pools are exhausted. You can override this to add custom tracking logic.
     *
     * @param float $quantity The amount of usage to register (e.g., tokens, API calls, credits).
     * @return void
     *
     * @example
     * ```php
     * // Basic usage
     * $rotator = OpenAIKeyRotator::make()->pickKey()->injectKey();
     * $response = OpenAI::chat()->create([...]);
     * $rotator->registerUsage($response->usage->totalTokens);
     *
     * // Or use the static method
     * OpenAIKeyRotator::registerUsageForLastUsedKey($response->usage->totalTokens);
     *
     * // Override example with custom tracking
     * public function registerUsage(float $quantity): void
     * {
     *     // Your custom logic here (e.g., send metrics to monitoring service)
     *     Metrics::increment('api.usage', $quantity, [
     *         'service' => static::$serviceName,
     *         'key_id' => $this->currentKey->id,
     *     ]);
     *
     *     parent::registerUsage($quantity);
     * }
     * ```
     */
    public function registerUsage(float $quantity): void
    {
        if (!$this->currentKey) {
            return;
        }

        $key = $this->currentKey;

        // 1. First consume the FREE pool, if available and not exhausted.
        $remainingFree = ($key->max_free_usage ?? 0) - $key->current_free_usage;
        if ($remainingFree > 0) {
            $usageForFreePool = min($quantity, $remainingFree);
            $key->current_free_usage += $usageForFreePool;
            $quantity -= $usageForFreePool; // Reduce the remaining quantity to register
        }

        // 2. If there's still usage to register, consume the BASE pool.
        if ($quantity > 0 && $key->base_limit_type !== 'none') {
            $key->current_base_usage += $quantity;
        }

        $key->last_used_at = now();
        $key->save();

        // 3. Check if the usage just registered has caused the key to be depleted.
        $this->checkAndMarkAsDepleted($key);
    }

    /**
     * Handle an exception that indicates key depletion.
     *
     * If the exception indicates quota exhaustion, marks the current key as depleted.
     * This is useful when the API returns an error before you can track usage normally.
     * You can override this to add custom depletion handling logic.
     *
     * @param Exception $exception The exception to analyze and handle.
     * @return bool True if the key was marked as depleted, false otherwise.
     *
     * @example
     * ```php
     * // Basic usage in a try-catch block
     * try {
     *     $rotator = OpenAIKeyRotator::make()->pickKey()->injectKey();
     *     $response = OpenAI::chat()->create([...]);
     * } catch (Exception $e) {
     *     if ($rotator->handleDepletedException($e)) {
     *         // Key was depleted, try with another key
     *         $rotator->pickKey()->injectKey();
     *         $response = OpenAI::chat()->create([...]);
     *     } else {
     *         throw $e; // Not a depletion error, re-throw
     *     }
     * }
     *
     * // Override example with custom notification
     * public function handleDepletedException(Exception $exception): bool
     * {
     *     $wasHandled = parent::handleDepletedException($exception);
     *
     *     if ($wasHandled) {
     *         // Your custom logic here (e.g., send notification)
     *         Notification::send($admins, new KeyDepletedNotification(
     *             $this->currentKey,
     *             static::$serviceName
     *         ));
     *     }
     *
     *     return $wasHandled;
     * }
     * ```
     */
    public function handleDepletedException(Exception $exception): bool
    {
        if ($this->currentKey && !$this->currentKey->is_depleted && $this->isDepletedException($exception)) {
            $this->currentKey->update([
                'is_depleted' => true,
                'depleted_at' => now(),
            ]);
            Log::info("KeyRotator: Key ID {$this->currentKey->id} for service '" . static::$serviceName . "' marked as depleted due to an exception.");
            return true;
        }
        return false;
    }

    /**
     * Check if a key has exhausted both of its usage pools
     * and, if so, mark it as depleted.
     *
     * @param RotableApiKey $key
     */
    protected function checkAndMarkAsDepleted(RotableApiKey $key): void
    {
        $freeDepleted = match ($key->free_limit_type) {
            'none' => true,
            default => ($key->current_free_usage >= $key->max_free_usage),
        };

        $baseDepleted = match ($key->base_limit_type) {
            'none' => true,
            'unlimited' => false,
            'fixed' => ($key->current_base_usage >= $key->max_base_usage),
        };

        if ($freeDepleted && $baseDepleted) {
            $key->update(['is_depleted' => true, 'depleted_at' => now()]);
        }
    }
}
