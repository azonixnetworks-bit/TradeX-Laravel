<?php

namespace App\Exceptions;

use RuntimeException;

class TradingRoutineWhatsAppException extends RuntimeException
{
    public function __construct(
        public readonly string $failureCode,
        string $message
    ) {
        parent::__construct($message);
    }
}
