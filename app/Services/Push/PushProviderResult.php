<?php

namespace App\Services\Push;

class PushProviderResult
{
    public const KIND_SENT = 'SENT';
    public const KIND_INVALID_TOKEN = 'INVALID_TOKEN';
    public const KIND_TRANSIENT = 'TRANSIENT';
    public const KIND_PERMANENT = 'PERMANENT';

    public function __construct(
        public readonly string $kind,
        public readonly ?int $httpStatus = null,
        public readonly ?string $code = null,
        public readonly ?string $providerMessageId = null
    ) {
    }

    public static function sent(
        ?int $httpStatus,
        ?string $providerMessageId = null
    ): self {
        return new self(
            self::KIND_SENT,
            $httpStatus,
            null,
            $providerMessageId
        );
    }

    public static function invalidToken(
        ?int $httpStatus,
        string $code
    ): self {
        return new self(
            self::KIND_INVALID_TOKEN,
            $httpStatus,
            $code
        );
    }

    public static function transient(
        ?int $httpStatus,
        string $code
    ): self {
        return new self(
            self::KIND_TRANSIENT,
            $httpStatus,
            $code
        );
    }

    public static function permanent(
        ?int $httpStatus,
        string $code
    ): self {
        return new self(
            self::KIND_PERMANENT,
            $httpStatus,
            $code
        );
    }
}
