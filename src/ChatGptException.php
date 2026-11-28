<?php

declare(strict_types=1);

namespace SimoneBianco\LaravelKeyRotator;

use RuntimeException;

final class ChatGptException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        public readonly ?int $httpStatus = null,
        public readonly ?string $requestId = null,
    ) {
        parent::__construct('ChatGPT OAuth: '.$reason);
    }
}
