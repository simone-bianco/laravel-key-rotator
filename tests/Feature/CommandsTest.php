<?php

use SimoneBianco\LaravelKeyRotator\Models\RotableApiKey;
use SimoneBianco\LaravelKeyRotator\Data\RotableKeyData;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Clear any existing keys
    RotableApiKey::query()->delete();
});

test('make:key-rotator command creates a new rotator class', function () {
    $className = 'TestServiceKeyRotator';
    $filePath = app_path("KeyRotators/{$className}.php");

    // Ensure the file doesn't exist
    if (File::exists($filePath)) {
        File::delete($filePath);
    }

    $this->artisan('make:key-rotator', ['name' => 'TestService'])
        ->expectsQuestion('What is the service name? (e.g., openai, anthropic)', 'test_service')
        ->expectsQuestion('What is the config key? (e.g., services.openai.api_key)', 'services.test_service.api_key')
        ->expectsQuestion('Do you need to inject the key into multiple config locations?', false)
        ->assertExitCode(0);

    expect(File::exists($filePath))->toBeTrue();

    $content = File::get($filePath);
    expect($content)->toContain('class TestServiceKeyRotator')
        ->and($content)->toContain("protected static string \$serviceName = 'test_service'")
        ->and($content)->toContain("protected static string \$configKey = 'services.test_service.api_key'");

    // Cleanup
    File::delete($filePath);
});

test('make:key-rotator command creates rotator with multiple config keys', function () {
    $className = 'MultiConfigKeyRotator';
    $filePath = app_path("KeyRotators/{$className}.php");

    // Ensure the file doesn't exist
    if (File::exists($filePath)) {
        File::delete($filePath);
    }

    $this->artisan('make:key-rotator', ['name' => 'MultiConfig'])
        ->expectsQuestion('What is the service name? (e.g., openai, anthropic)', 'multi_config')
        ->expectsQuestion('What is the config key? (e.g., services.openai.api_key)', 'services.multi_config.api_key')
        ->expectsQuestion('Do you need to inject the key into multiple config locations?', true)
        ->assertExitCode(0);

    expect(File::exists($filePath))->toBeTrue();

    $content = File::get($filePath);
    expect($content)->toContain('protected static array $configKey');

    // Cleanup
    File::delete($filePath);
});

test('make:key-rotator command fails if class already exists', function () {
    $className = 'ExistingKeyRotator';
    $filePath = app_path("KeyRotators/{$className}.php");

    // Create the directory if it doesn't exist
    File::ensureDirectoryExists(app_path('KeyRotators'));

    // Create a dummy file
    File::put($filePath, '<?php // Existing file');

    $this->artisan('make:key-rotator', ['name' => 'Existing'])
        ->expectsOutput("   ❌ KeyRotator class '{$className}' already exists!")
        ->assertExitCode(1);

    // Cleanup
    File::delete($filePath);
});

test('key-rotator:reset-usage command resets all keys', function () {
    // Create some keys with usage
    RotableApiKey::create([
        'service' => 'service1',
        'key' => 'key1',
        'base_limit_type' => 'fixed',
        'max_base_usage' => 1000,
        'current_base_usage' => 500,
        'current_free_usage' => 50,
        'is_depleted' => true,
        'depleted_at' => now(),
    ]);

    RotableApiKey::create([
        'service' => 'service2',
        'key' => 'key2',
        'base_limit_type' => 'fixed',
        'max_base_usage' => 2000,
        'current_base_usage' => 1000,
        'current_free_usage' => 100,
    ]);

    $this->artisan('key-rotator:reset-usage', ['--force' => true])
        ->assertExitCode(0);

    $keys = RotableApiKey::all();

    foreach ($keys as $key) {
        expect((float) $key->current_base_usage)->toBe(0.0)
            ->and((float) $key->current_free_usage)->toBe(0.0)
            ->and($key->is_depleted)->toBeFalse()
            ->and($key->depleted_at)->toBeNull();
    }
});

test('key-rotator:reset-usage command resets only specific service', function () {
    // Create keys for different services
    RotableApiKey::create([
        'service' => 'service1',
        'key' => 'key1',
        'base_limit_type' => 'fixed',
        'max_base_usage' => 1000,
        'current_base_usage' => 500,
    ]);

    RotableApiKey::create([
        'service' => 'service2',
        'key' => 'key2',
        'base_limit_type' => 'fixed',
        'max_base_usage' => 2000,
        'current_base_usage' => 1000,
    ]);

    $this->artisan('key-rotator:reset-usage', [
        'service' => 'service1',
        '--force' => true
    ])->assertExitCode(0);

    $key1 = RotableApiKey::where('service', 'service1')->first();
    $key2 = RotableApiKey::where('service', 'service2')->first();

    expect((float) $key1->current_base_usage)->toBe(0.0)
        ->and((float) $key2->current_base_usage)->toBe(1000.0);
});

test('key-rotator:reset-free-usage command resets keys due for reset', function () {
    // Create a key with daily reset that's due
    $key1 = RotableApiKey::create([
        'service' => 'service1',
        'key' => 'key1',
        'free_limit_type' => 'daily',
        'max_free_usage' => 100,
        'current_free_usage' => 80,
        'base_limit_type' => 'unlimited',
        'free_usage_resets_at' => Carbon::now()->subHour(),
        'last_free_usage_reset_at' => Carbon::now()->subDay(),
    ]);

    // Create a key that's not due for reset
    $key2 = RotableApiKey::create([
        'service' => 'service2',
        'key' => 'key2',
        'free_limit_type' => 'monthly',
        'max_free_usage' => 1000,
        'current_free_usage' => 500,
        'base_limit_type' => 'unlimited',
        'free_usage_resets_at' => Carbon::now()->addWeek(),
        'last_free_usage_reset_at' => Carbon::now()->subWeek(),
    ]);

    $this->artisan('key-rotator:reset-free-usage', ['--force' => true])
        ->assertExitCode(0);

    $key1->refresh();
    $key2->refresh();

    expect((float) $key1->current_free_usage)->toBe(0.0)
        ->and($key1->last_free_usage_reset_at)->not->toBeNull()
        ->and((float) $key2->current_free_usage)->toBe(500.0);
});

test('key-rotator:reset-free-usage command with dry-run does not reset', function () {
    $key = RotableApiKey::create([
        'service' => 'service1',
        'key' => 'key1',
        'free_limit_type' => 'daily',
        'max_free_usage' => 100,
        'current_free_usage' => 80,
        'base_limit_type' => 'unlimited',
        'free_usage_resets_at' => Carbon::now()->subHour(),
    ]);

    $this->artisan('key-rotator:reset-free-usage', ['--dry-run' => true])
        ->assertExitCode(0);

    $key->refresh();

    expect((float) $key->current_free_usage)->toBe(80.0);
});

test('key-rotator:reset-free-usage command calculates next reset correctly for daily', function () {
    $key = RotableApiKey::create([
        'service' => 'service1',
        'key' => 'key1',
        'free_limit_type' => 'daily',
        'max_free_usage' => 100,
        'current_free_usage' => 80,
        'base_limit_type' => 'unlimited',
        'reset_timezone' => 'UTC',
        'free_usage_resets_at' => Carbon::now()->subHour(),
    ]);

    $this->artisan('key-rotator:reset-free-usage', ['--force' => true])
        ->assertExitCode(0);

    $key->refresh();

    $expectedNextReset = Carbon::now('UTC')->addDay()->startOfDay();

    expect($key->free_usage_resets_at->format('Y-m-d H:i'))
        ->toBe($expectedNextReset->format('Y-m-d H:i'));
});

test('key-rotator:reset-free-usage command calculates next reset correctly for monthly', function () {
    $key = RotableApiKey::create([
        'service' => 'service1',
        'key' => 'key1',
        'free_limit_type' => 'monthly',
        'max_free_usage' => 1000,
        'current_free_usage' => 800,
        'base_limit_type' => 'unlimited',
        'reset_timezone' => 'UTC',
        'free_usage_resets_at' => Carbon::now()->subHour(),
    ]);

    $this->artisan('key-rotator:reset-free-usage', ['--force' => true])
        ->assertExitCode(0);

    $key->refresh();

    $expectedNextReset = Carbon::now('UTC')->addMonth()->startOfMonth();

    expect($key->free_usage_resets_at->format('Y-m-d H:i'))
        ->toBe($expectedNextReset->format('Y-m-d H:i'));
});

