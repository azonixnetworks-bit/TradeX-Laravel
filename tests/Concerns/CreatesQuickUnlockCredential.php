<?php

namespace Tests\Concerns;

use Illuminate\Support\Str;

trait CreatesQuickUnlockCredential
{
    protected function createQuickUnlockCredential(): array
    {
        $privateKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);
        $this->assertNotFalse($privateKey);

        $details = openssl_pkey_get_details($privateKey);
        $this->assertIsArray($details);

        $publicPem = $details['key'];
        $publicDer = base64_decode(preg_replace(
            '/-----[^-]+-----|\s+/',
            '',
            $publicPem
        ), true);
        $this->assertNotFalse($publicDer);

        return [
            'id' => (string) Str::uuid(),
            'algorithm' => 'ES256',
            'public_key' => base64_encode($publicDer),
            'private_key' => $privateKey,
        ];
    }

    protected function signQuickUnlockMessage(mixed $privateKey, string $message): string
    {
        $signature = '';
        $signed = openssl_sign(
            $message,
            $signature,
            $privateKey,
            OPENSSL_ALGO_SHA256
        );
        $this->assertTrue($signed);

        return base64_encode($signature);
    }
}
