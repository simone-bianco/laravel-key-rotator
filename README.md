# Laravel Key Rotator

[![Latest Version](https://img.shields.io/packagist/v/simone-bianco/laravel-key-rotator.svg?style=flat-square)](https://packagist.org/packages/simone-bianco/laravel-key-rotator)
[![Total Downloads](https://img.shields.io/packagist/dt/simone-bianco/laravel-key-rotator.svg?style=flat-square)](https://packagist.org/packages/simone-bianco/laravel-key-rotator)
[![License](https://img.shields.io/packagist/l/simone-bianco/laravel-key-rotator.svg?style=flat-square)](https://packagist.org/packages/simone-bianco/laravel-key-rotator)

A powerful Laravel package for managing and rotating API keys with intelligent usage tracking. Perfect for applications that need to manage multiple API keys for services like OpenAI, Anthropic, Google Cloud, or any other API provider.

## Features

- 🔄 **Automatic Key Rotation**: Intelligently selects the best available API key based on remaining usage
- 📊 **Usage Tracking**: Track both free and paid usage pools for each key
- ⏰ **Automatic Reset Scheduling**: Support for daily and monthly free tier resets
- 🔐 **Encrypted Storage**: Optional encryption for API keys in the database
- 🎯 **Smart Depletion Detection**: Automatically detect and mark depleted keys
- 🔧 **Highly Customizable**: Override any method to implement custom logic
- 🚀 **Easy Integration**: Simple, fluent API that integrates seamlessly with Laravel
- 📦 **Multiple Config Keys**: Support for injecting the same key into multiple configuration locations
- 🎨 **Artisan Commands**: Generate rotators and manage keys via CLI

## Installation

Install the package via Composer:

```bash
composer require simone-bianco/laravel-key-rotator
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=laravel-key-rotator-config
```

Run the migrations:

```bash
php artisan migrate
```

## Quick Start

### 1. Create a Key Rotator

Generate a new rotator class for your service:

```bash
php artisan make:key-rotator OpenAIKeyRotator
```

This creates a new class in `app/KeyRotators/OpenAIKeyRotator.php`:

```php
<?php

namespace App\KeyRotators;

use SimoneBianco\LaravelKeyRotator\KeyRotator;

class OpenAIKeyRotator extends KeyRotator
{
    protected static string $serviceName = 'openai';
    protected static string $configKey = 'services.openai.api_key';
}
```

### 2. Register API Keys

Register your API keys in the database:

```php
use App\KeyRotators\OpenAIKeyRotator;
use SimoneBianco\LaravelKeyRotator\Data\RotableKeyData;

$rotator = new OpenAIKeyRotator();

// Register a key with unlimited usage
$rotator->registerKey(new RotableKeyData(
    key: 'sk-proj-...',
    base_limit_type: 'unlimited'
));

// Register a key with fixed usage limit
$rotator->registerKey(new RotableKeyData(
    key: 'sk-proj-...',
    base_limit_type: 'fixed',
    max_base_usage: 1000000 // 1M tokens
));

// Register a key with free tier that resets monthly
$rotator->registerKey(new RotableKeyData(
    key: 'sk-proj-...',
    free_limit_type: 'monthly',
    max_free_usage: 100000, // 100K free tokens
    base_limit_type: 'fixed',
    max_base_usage: 5000000 // 5M paid tokens
));
```

### 3. Use the Rotator

```php
use App\KeyRotators\OpenAIKeyRotator;

// Pick and inject the best available key
OpenAIKeyRotator::make()
    ->pickKey()
    ->injectKey();

// Now use your API client normally
$response = OpenAI::chat()->create([
    'model' => 'gpt-4',
    'messages' => [
        ['role' => 'user', 'content' => 'Hello!']
    ]
]);

// Register the usage
OpenAIKeyRotator::registerUsageForLastUsedKey(
    $response->usage->totalTokens
);
```

## Advanced Usage

### Multiple Configuration Keys

Some services require the same API key in multiple configuration locations. You can specify an array of config keys:

```php
class OpenAIKeyRotator extends KeyRotator
{
    protected static string $serviceName = 'openai';

    // Inject the same key into multiple config locations
    protected static array $configKey = [
        'services.openai.api_key',
        'openai.api_key',
        'ai.providers.openai.key'
    ];
}
```

### Using Extra Data for Additional Credentials

Many APIs require more than just an API key. Use the `extra_data` field to store additional credentials:

```php
// Register a key with organization and project IDs
$rotator->registerKey(new RotableKeyData(
    key: 'sk-proj-...',
    base_limit_type: 'unlimited',
    extra_data: [
        'organization_id' => 'org-...',
        'project_id' => 'proj-...'
    ]
));
```


Then override the `injectKey()` method to inject these additional values:

```php
class OpenAIKeyRotator extends KeyRotator
{
    protected static string $serviceName = 'openai';
    protected static string $configKey = 'services.openai.api_key';

    public function injectKey(): static
    {
        parent::injectKey();

        // Inject additional credentials from extra_data
        if ($this->currentKey->extra_data) {
            if (isset($this->currentKey->extra_data['organization_id'])) {
                Config::set('services.openai.organization',
                    $this->currentKey->extra_data['organization_id']);
            }

            if (isset($this->currentKey->extra_data['project_id'])) {
                Config::set('services.openai.project',
                    $this->currentKey->extra_data['project_id']);
            }
        }

        return $this;
    }
}
```

### Custom Key Selection Logic

Override the `pickKey()` method to implement custom selection logic:

```php
class OpenAIKeyRotator extends KeyRotator
{
    public function pickKey(): static
    {
        // Prioritize keys from a specific region
        $nextKey = RotableApiKey::where('service', static::$serviceName)
            ->where('is_active', true)
            ->where('is_depleted', false)
            ->whereJsonContains('extra_data->region', 'us-east-1')
            ->orderBy('current_base_usage', 'asc')
            ->first();

        if (!$nextKey) {
            // Fallback to default logic
            return parent::pickKey();
        }

        $this->currentKey = $nextKey;
        self::cacheRotableKeyId($nextKey);

        return $this;
    }
}
```

### Custom Depletion Detection

Override `isDepletedException()` for service-specific error detection:

```php
use OpenAI\Exceptions\ErrorException;

class OpenAIKeyRotator extends KeyRotator
{
    public function isDepletedException(Exception $exception): bool
    {
        // Check for OpenAI-specific rate limit errors
        if ($exception instanceof ErrorException) {
            $error = $exception->getErrorResponse();

            if ($error['type'] === 'insufficient_quota') {
                return true;
            }

            if ($error['code'] === 'rate_limit_exceeded') {
                return true;
            }
        }

        // Fallback to default keyword detection
        return parent::isDepletedException($exception);
    }
}
```

### Handling Depletion with Automatic Retry

```php
use App\KeyRotators\OpenAIKeyRotator;

$maxRetries = 3;
$attempt = 0;

while ($attempt < $maxRetries) {
    try {
        $rotator = OpenAIKeyRotator::make()->pickKey()->injectKey();

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4',
            'messages' => [['role' => 'user', 'content' => 'Hello!']]
        ]);

        $rotator->registerUsage($response->usage->totalTokens);
        break; // Success!

    } catch (Exception $e) {
        if ($rotator->handleDepletedException($e)) {
            $attempt++;
            continue; // Try again with a different key
        }

        throw $e; // Not a depletion error, re-throw
    }
}
```

## Artisan Commands

### Generate a Key Rotator

```bash
php artisan make:key-rotator {name}
```

Creates a new KeyRotator class in `app/KeyRotators/`.

### Reset All Usage

```bash
php artisan key-rotator:reset-usage {service?}
```

Resets usage counters for all keys or a specific service.

### Reset Free Usage

```bash
php artisan key-rotator:reset-free-usage
```

Resets free usage for keys that are due for a reset based on their schedule.

## Configuration

The configuration file `config/laravel-key-rotator.php` allows you to customize:

```php
return [
    // The model to use for rotable API keys
    'model' => \SimoneBianco\LaravelKeyRotator\Models\RotableApiKey::class,

    // Whether to encrypt keys in the database
    'encrypt_keys' => true,

    // Database connection to use
    'db_connection' => env('DB_CONNECTION', 'mysql'),
];
```

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Credits

- [Simone Bianco](https://github.com/simone-bianco)

## Support

If you discover any issues, please open an issue on GitHub.
