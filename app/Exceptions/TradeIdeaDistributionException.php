<?php

namespace App\Exceptions;

use RuntimeException;

class TradeIdeaDistributionException extends RuntimeException
{
    public function __construct(
        public readonly string $provider,
        public readonly string $providerCode,
        string $safeMessage
    ) {
        parent::__construct(
            $safeMessage
        );
    }
}
