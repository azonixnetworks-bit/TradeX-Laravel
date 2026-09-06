<?php

namespace App\Exceptions;

use RuntimeException;

class ScreenerProviderException extends RuntimeException
{
    public function __construct(
        public readonly string $providerCode,
        string $safeMessage
    ) {
        parent::__construct($safeMessage);
    }
}
