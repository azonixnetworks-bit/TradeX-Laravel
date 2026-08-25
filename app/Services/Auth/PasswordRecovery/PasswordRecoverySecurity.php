<?php

namespace App\Services\Auth\PasswordRecovery;

use App\Exceptions\AuthApiException;

class PasswordRecoverySecurity
{
    public function hmac(string $purpose, string $value): string
    {
        $configuredKey = (string) config(
            'tradex_password_recovery.hash_key',
            ''
        );

        if (blank($configuredKey)) {
            throw new AuthApiException(
                500,
                'PASSWORD_RECOVERY_HASH_KEY_MISSING',
                'Password recovery protection is not configured.'
            );
        }

        $key = hash_hmac(
            'sha256',
            'tradex:password-recovery:'.$purpose.':v1',
            $configuredKey,
            true
        );

        return hash_hmac('sha256', $value, $key);
    }

    public function accessTokenHash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function randomToken(int $bytes = 32): string
    {
        return rtrim(
            strtr(
                base64_encode(random_bytes($bytes)),
                '+/',
                '-_'
            ),
            '='
        );
    }
}
