<?php

namespace App\Services\Security\QuickUnlock;

use App\Exceptions\AuthApiException;

class DeviceCredentialVerifier
{
    public const ALGORITHM_ES256 = 'ES256';

    public function validatePublicKey(
        string $algorithm,
        string $encodedPublicKey
    ): void {
        if ($algorithm !== self::ALGORITHM_ES256) {
            throw new AuthApiException(
                422,
                'QUICK_UNLOCK_ALGORITHM_UNSUPPORTED',
                'The device credential algorithm is not supported.'
            );
        }

        $key = openssl_pkey_get_public($this->toPem($encodedPublicKey));
        $details = $key === false ? false : openssl_pkey_get_details($key);

        if (
            ! is_array($details)
            || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_EC
            || ! in_array(
                $details['ec']['curve_name'] ?? null,
                ['prime256v1', 'secp256r1'],
                true
            )
        ) {
            throw new AuthApiException(
                422,
                'QUICK_UNLOCK_PUBLIC_KEY_INVALID',
                'The device credential public key is invalid.'
            );
        }
    }

    public function verify(
        string $encodedPublicKey,
        string $message,
        string $encodedSignature
    ): bool {
        $signature = base64_decode($encodedSignature, true);
        if ($signature === false || $signature === '') {
            return false;
        }

        $key = openssl_pkey_get_public($this->toPem($encodedPublicKey));
        if ($key === false) {
            return false;
        }

        return openssl_verify(
            $message,
            $signature,
            $key,
            OPENSSL_ALGO_SHA256
        ) === 1;
    }

    public function canonicalMessage(
        string $challengeId,
        string $credentialId,
        string $installationId,
        string $nonce
    ): string {
        return implode("\n", [
            'tradex-quick-login-v1',
            strtolower($challengeId),
            strtolower($credentialId),
            strtolower($installationId),
            $nonce,
        ]);
    }

    private function toPem(string $encodedPublicKey): string
    {
        $der = base64_decode($encodedPublicKey, true);
        if ($der === false || $der === '') {
            return '';
        }

        return "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode($der), 64, "\n")
            ."-----END PUBLIC KEY-----\n";
    }
}
