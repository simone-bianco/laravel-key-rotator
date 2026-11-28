<?php

declare(strict_types=1);

namespace SimoneBianco\LaravelKeyRotator;

use Illuminate\Http\Client\Factory;

/**
 * An isolated HTTP factory for requests carrying OAuth secrets.
 */
final class ChatGptHttp extends Factory
{
    public function __construct()
    {
        parent::__construct();
    }
}
