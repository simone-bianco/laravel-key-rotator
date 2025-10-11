<?php

use SimoneBianco\LaravelKeyRotator\Models\RotableApiKey;
use SimoneBianco\LaravelKeyRotator\Data\RotableKeyData;
use SimoneBianco\LaravelKeyRotator\Exceptions\NoAvailableKeysException;
use Illuminate\Support\Facades\Config;

// Test KeyRotator implementation
class TestKeyRotator extends \SimoneBianco\LaravelKeyRotator\KeyRotator
{
    protected static string $serviceName = 'test-service';
    protected static string|array $configKey = 'services.test.api_key';
}

// Test KeyRotator with multiple config keys
class TestMultiConfigKeyRotator extends \SimoneBianco\LaravelKeyRotator\KeyRotator
{
    protected static string $serviceName = 'test-multi';
    protected static string|array $configKey = [
        'services.test.api_key',
        'test.api_key',
        'api.test.key',
    ];
}

beforeEach(function () {
    // Clear any existing keys
    RotableApiKey::query()->delete();
});

test('can register a new API key', function () {
    $rotator = new TestKeyRotator();

    $key = $rotator->registerKey(new RotableKeyData(
        key: 'test-key-123',
        base_limit_type: 'unlimited'
    ));

    expect($key)->toBeInstanceOf(RotableApiKey::class)
        ->and($key->service)->toBe('test-service')
        ->and($key->key)->toBe('test-key-123')
        ->and($key->base_limit_type)->toBe('unlimited')
        ->and($key->is_active)->toBeTrue()
        ->and($key->is_depleted)->toBeFalse();
});

test('can pick the best available key', function () {
    $rotator = new TestKeyRotator();

    // Register multiple keys with different usage
    $key1 = $rotator->registerKey(new RotableKeyData(
        key: 'key-1',
        base_limit_type: 'fixed',
        max_base_usage: 1000,
        current_base_usage: 500
    ));

    $key2 = $rotator->registerKey(new RotableKeyData(
        key: 'key-2',
        base_limit_type: 'fixed',
        max_base_usage: 1000,
        current_base_usage: 200
    ));

    $rotator->pickKey();

    // Should pick key-2 as it has more remaining usage
    expect($rotator->getCurrentKey()->id)->toBe($key2->id);
});

test('throws exception when no keys are available', function () {
    $rotator = new TestKeyRotator();

    $rotator->pickKey();
})->throws(NoAvailableKeysException::class);

test('can inject key into config', function () {
    $rotator = new TestKeyRotator();

    $rotator->registerKey(new RotableKeyData(
        key: 'test-key-123',
        base_limit_type: 'unlimited'
    ));

    $rotator->pickKey()->injectKey();

    expect(Config::get('services.test.api_key'))->toBe('test-key-123');
});

test('can inject key into multiple config locations', function () {
    $rotator = new TestMultiConfigKeyRotator();

    $rotator->registerKey(new RotableKeyData(
        key: 'multi-key-123',
        base_limit_type: 'unlimited'
    ));

    $rotator->pickKey()->injectKey();

    expect(Config::get('services.test.api_key'))->toBe('multi-key-123')
        ->and(Config::get('test.api_key'))->toBe('multi-key-123')
        ->and(Config::get('api.test.key'))->toBe('multi-key-123');
});

test('can register usage for a key', function () {
    $rotator = new TestKeyRotator();

    $key = $rotator->registerKey(new RotableKeyData(
        key: 'test-key',
        base_limit_type: 'fixed',
        max_base_usage: 1000
    ));

    $rotator->setKey($key);
    $rotator->registerUsage(100);

    $key->refresh();

    expect((float) $key->current_base_usage)->toBe(100.0)
        ->and($key->last_used_at)->not->toBeNull();
});

test('consumes free pool before base pool', function () {
    $rotator = new TestKeyRotator();

    $key = $rotator->registerKey(new RotableKeyData(
        key: 'test-key',
        free_limit_type: 'monthly',
        max_free_usage: 100,
        base_limit_type: 'fixed',
        max_base_usage: 1000
    ));

    $rotator->setKey($key);
    $rotator->registerUsage(150);

    $key->refresh();

    // Should consume 100 from free pool and 50 from base pool
    expect((float) $key->current_free_usage)->toBe(100.0)
        ->and((float) $key->current_base_usage)->toBe(50.0);
});

test('marks key as depleted when both pools are exhausted', function () {
    $rotator = new TestKeyRotator();

    $key = $rotator->registerKey(new RotableKeyData(
        key: 'test-key',
        free_limit_type: 'monthly',
        max_free_usage: 100,
        base_limit_type: 'fixed',
        max_base_usage: 200
    ));

    $rotator->setKey($key);
    $rotator->registerUsage(300);

    $key->refresh();

    expect($key->is_depleted)->toBeTrue()
        ->and($key->depleted_at)->not->toBeNull();
});

test('can register usage for last used key', function () {
    $rotator = new TestKeyRotator();

    $rotator->registerKey(new RotableKeyData(
        key: 'test-key',
        base_limit_type: 'fixed',
        max_base_usage: 1000
    ));

    $rotator->pickKey()->injectKey();

    TestKeyRotator::registerUsageForLastUsedKey(50);

    $key = RotableApiKey::where('service', 'test-service')->first();

    expect((float) $key->current_base_usage)->toBe(50.0);
});

test('detects depletion exceptions', function () {
    $rotator = new TestKeyRotator();

    $exception = new Exception('Rate limit exceeded for this API key');

    expect($rotator->isDepletedException($exception))->toBeTrue();
});

test('handles depletion exception and marks key as depleted', function () {
    $rotator = new TestKeyRotator();

    $key = $rotator->registerKey(new RotableKeyData(
        key: 'test-key',
        base_limit_type: 'unlimited'
    ));

    $rotator->setKey($key);

    $exception = new Exception('Quota exceeded');
    $handled = $rotator->handleDepletedException($exception);

    $key->refresh();

    expect($handled)->toBeTrue()
        ->and($key->is_depleted)->toBeTrue()
        ->and($key->depleted_at)->not->toBeNull();
});

test('does not pick depleted keys', function () {
    $rotator = new TestKeyRotator();

    // Register a depleted key
    $depletedKey = $rotator->registerKey(new RotableKeyData(
        key: 'depleted-key',
        base_limit_type: 'fixed',
        max_base_usage: 100
    ));
    $depletedKey->update(['is_depleted' => true]);

    // Register an active key
    $activeKey = $rotator->registerKey(new RotableKeyData(
        key: 'active-key',
        base_limit_type: 'unlimited'
    ));

    $rotator->pickKey();

    expect($rotator->getCurrentKey()->id)->toBe($activeKey->id);
});

test('does not pick inactive keys', function () {
    $rotator = new TestKeyRotator();

    // Register an inactive key
    $inactiveKey = $rotator->registerKey(new RotableKeyData(
        key: 'inactive-key',
        base_limit_type: 'unlimited',
        is_active: false
    ));

    // Register an active key
    $activeKey = $rotator->registerKey(new RotableKeyData(
        key: 'active-key',
        base_limit_type: 'unlimited'
    ));

    $rotator->pickKey();

    expect($rotator->getCurrentKey()->id)->toBe($activeKey->id);
});

test('can use make factory method', function () {
    $rotator = TestKeyRotator::make();

    expect($rotator)->toBeInstanceOf(TestKeyRotator::class);
});

test('throws exception when injecting without picking a key', function () {
    $rotator = new TestKeyRotator();

    $rotator->injectKey();
})->throws(Exception::class, 'No key selected');

