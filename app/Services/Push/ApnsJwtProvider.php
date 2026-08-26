<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Cache;
use JsonException;
use RuntimeException;

class ApnsJwtProvider
{
    public function token(bool $forceRefresh = false): string
    {
        $teamId = trim((string) config('tradex_push.apns.team_id', ''));
        $keyId = trim((string) config('tradex_push.apns.key_id', ''));
        $path = trim((string) config(
            'tradex_push.apns.private_key_path',
            ''
        ));

        if (
            $teamId === ''
            || $keyId === ''
            || $path === ''
            || ! is_file($path)
            || ! is_readable($path)
        ) {
            throw new RuntimeException(
                'APNs provider-token credentials are not configured.'
            );
        }

        $cacheKey = 'tradex_push_apns_jwt_'.hash(
            'sha256',
            $teamId.'|'.$keyId
        );

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $privateKey = (string) file_get_contents($path);
        $now = time();

        $header = $this->base64UrlJson([
            'alg' => 'ES256',
            'kid' => $keyId,
        ]);

        $claims = $this->base64UrlJson([
            'iss' => $teamId,
            'iat' => $now,
        ]);

        $signingInput = $header.'.'.$claims;
        $key = openssl_pkey_get_private($privateKey);

        if ($key === false) {
            throw new RuntimeException('Invalid APNs private key.');
        }

        $derSignature = '';
        $signed = openssl_sign(
            $signingInput,
            $derSignature,
            $key,
            OPENSSL_ALGO_SHA256
        );

        if (! $signed) {
            throw new RuntimeException('APNs JWT signing failed.');
        }

        $jwt = $signingInput.'.'.$this->base64Url(
            $this->derSignatureToJose($derSignature)
        );

        Cache::put(
            $cacheKey,
            $jwt,
            now()->addMinutes(45)
        );

        return $jwt;
    }

    private function derSignatureToJose(string $der): string
    {
        $offset = 0;

        if ($this->readByte($der, $offset) !== 0x30) {
            throw new RuntimeException('Invalid ECDSA signature sequence.');
        }

        $this->readLength($der, $offset);

        if ($this->readByte($der, $offset) !== 0x02) {
            throw new RuntimeException('Invalid ECDSA R value.');
        }

        $rLength = $this->readLength($der, $offset);
        $r = substr($der, $offset, $rLength);
        $offset += $rLength;

        if ($this->readByte($der, $offset) !== 0x02) {
            throw new RuntimeException('Invalid ECDSA S value.');
        }

        $sLength = $this->readLength($der, $offset);
        $s = substr($der, $offset, $sLength);

        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");

        if (strlen($r) > 32 || strlen($s) > 32) {
            throw new RuntimeException('Unexpected ECDSA signature size.');
        }

        return str_pad($r, 32, "\x00", STR_PAD_LEFT)
            .str_pad($s, 32, "\x00", STR_PAD_LEFT);
    }

    private function readByte(string $der, int &$offset): int
    {
        if (! isset($der[$offset])) {
            throw new RuntimeException('Truncated ECDSA signature.');
        }

        return ord($der[$offset++]);
    }

    private function readLength(string $der, int &$offset): int
    {
        $first = $this->readByte($der, $offset);

        if (($first & 0x80) === 0) {
            return $first;
        }

        $count = $first & 0x7F;

        if ($count < 1 || $count > 4) {
            throw new RuntimeException('Invalid DER length.');
        }

        $length = 0;

        for ($i = 0; $i < $count; $i++) {
            $length = ($length << 8) | $this->readByte($der, $offset);
        }

        return $length;
    }

    /** @throws JsonException */
    private function base64UrlJson(array $value): string
    {
        return $this->base64Url(json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ));
    }

    private function base64Url(string $value): string
    {
        return rtrim(
            strtr(base64_encode($value), '+/', '-_'),
            '='
        );
    }
}
