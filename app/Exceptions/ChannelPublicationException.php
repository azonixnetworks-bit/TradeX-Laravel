<?php

namespace App\Exceptions;

use RuntimeException;

class ChannelPublicationException extends RuntimeException
{
    public function __construct(
        public readonly string $platform,
        public readonly string $providerCode,
        string $safeMessage
    ) {
        parent::__construct($safeMessage);
    }
}
