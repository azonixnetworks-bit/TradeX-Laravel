<?php

namespace App\Services\Auth\Social;

class SocialIdentity
{
    public function __construct(
        public readonly string $provider,
        public readonly string $subject,
        public readonly ?string $email,
        public readonly bool $emailVerified,
        public readonly ?string $name,
        public readonly ?string $nonce = null,
    ) {
    }
}
