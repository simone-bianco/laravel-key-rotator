<?php

use SimoneBianco\LaravelKeyRotator\KeyRotator;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    KeyRotator::clearCache();
});

test('rules are cached when cache is enabled', function () {
    config(['laravel-key-rotator.cache_enabled' => true]);

    // First call should cache the rules
    $rules1 = KeyRotator::for('user.profile')->toArray();

    // Check if cache exists
    $cacheKey = 'laravel_key_rotator_user.profile';
    expect(Cache::has($cacheKey))->toBeTrue();

    // Second call should use cache
    $rules2 = KeyRotator::for('user.profile')->toArray();

    expect($rules1)->toEqual($rules2);
});

test('rules are not cached when cache is disabled', function () {
    config(['laravel-key-rotator.cache_enabled' => false]);

    // Call should not cache the rules
    KeyRotator::for('user.profile')->toArray();

    // Check if cache does not exist
    $cacheKey = 'laravel_key_rotator_user.profile';
    expect(Cache::has($cacheKey))->toBeFalse();
});

test('cache respects ttl setting', function () {
    config(['laravel-key-rotator.cache_enabled' => true]);
    config(['laravel-key-rotator.cache_ttl' => 60]);

    KeyRotator::for('user.profile')->toArray();

    $cacheKey = 'laravel_key_rotator_user.profile';
    expect(Cache::has($cacheKey))->toBeTrue();
});

test('cache can be cleared', function () {
    config(['laravel-key-rotator.cache_enabled' => true]);

    // Cache some rules
    KeyRotator::for('user.profile')->toArray();
    KeyRotator::for('user.settings')->toArray();

    // Verify cache exists
    expect(Cache::has('laravel_key_rotator_user.profile'))->toBeTrue();
    expect(Cache::has('laravel_key_rotator_user.settings'))->toBeTrue();

    // Clear cache
    KeyRotator::clearCache();

    // Verify cache is cleared
    expect(Cache::has('laravel_key_rotator_user.profile'))->toBeFalse();
    expect(Cache::has('laravel_key_rotator_user.settings'))->toBeFalse();
});

test('cache with ttl zero stores forever', function () {
    config(['laravel-key-rotator.cache_enabled' => true]);
    config(['laravel-key-rotator.cache_ttl' => 0]);

    KeyRotator::for('user.profile')->toArray();

    $cacheKey = 'laravel_key_rotator_user.profile';
    expect(Cache::has($cacheKey))->toBeTrue();
});

