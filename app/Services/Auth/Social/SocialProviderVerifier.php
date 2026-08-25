<?php

namespace App\Services\Auth\Social;

use App\Exceptions\AuthApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class SocialProviderVerifier
{
    public function verify(string $provider, string $credential): SocialIdentity
    {
        $provider = strtolower($provider);
        $config = (array) config("tradex_social_auth.providers.{$provider}", []);

        if (! in_array($provider, ['google', 'facebook', 'apple'], true)) {
            throw new AuthApiException(404, 'SOCIAL_PROVIDER_NOT_SUPPORTED', 'The social provider is not supported.');
        }

        if (! ($config['enabled'] ?? false)) {
            throw new AuthApiException(503, 'SOCIAL_PROVIDER_NOT_CONFIGURED', 'The social provider is not available.');
        }

        return $provider === 'facebook'
            ? $this->verifyFacebook($credential, $config)
            : $this->verifyOidc($provider, $credential, $config);
    }

    private function verifyOidc(string $provider, string $jwt, array $config): SocialIdentity
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            $this->invalidCredential();
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $header = $this->decodeJsonSegment($encodedHeader);
        $claims = $this->decodeJsonSegment($encodedPayload);

        if (($header['alg'] ?? null) !== 'RS256' || blank($header['kid'] ?? null)) {
            $this->invalidCredential();
        }

        $audiences = array_values(array_filter((array) ($config['client_ids'] ?? [])));
        if ($audiences === []) {
            throw new AuthApiException(503, 'SOCIAL_PROVIDER_NOT_CONFIGURED', 'The social provider is not available.');
        }

        $key = $this->resolveJwk((string) $config['jwks_url'], (string) $header['kid']);
        if (($key['kty'] ?? null) !== 'RSA' || (($key['alg'] ?? 'RS256') !== 'RS256')) {
            $this->invalidCredential();
        }

        $signature = $this->base64UrlDecode($encodedSignature);
        $verified = openssl_verify(
            $encodedHeader.'.'.$encodedPayload,
            $signature,
            $this->jwkToPem($key),
            OPENSSL_ALGO_SHA256
        );

        if ($verified !== 1) {
            $this->invalidCredential();
        }

        $now = time();
        $skew = max(0, (int) config('tradex_social_auth.clock_skew_seconds', 60));
        $issuers = (array) ($config['issuer'] ?? []);
        $audClaim = $claims['aud'] ?? null;
        $tokenAudiences = is_array($audClaim) ? $audClaim : [$audClaim];

        if (
            ! in_array((string) ($claims['iss'] ?? ''), $issuers, true)
            || ! array_intersect($audiences, array_filter($tokenAudiences, 'is_string'))
            || ! is_numeric($claims['exp'] ?? null)
            || (int) $claims['exp'] < ($now - $skew)
            || (isset($claims['nbf']) && (int) $claims['nbf'] > ($now + $skew))
            || (isset($claims['iat']) && (int) $claims['iat'] > ($now + $skew))
            || blank($claims['sub'] ?? null)
        ) {
            $this->invalidCredential();
        }

        if (is_array($audClaim) && count($audClaim) > 1) {
            $azp = (string) ($claims['azp'] ?? '');
            if (! in_array($azp, $audiences, true)) {
                $this->invalidCredential();
            }
        }

        $email = isset($claims['email'])
            ? mb_strtolower(trim((string) $claims['email']))
            : null;

        return new SocialIdentity(
            provider: $provider,
            subject: (string) $claims['sub'],
            email: $email ?: null,
            emailVerified: $this->toBoolean($claims['email_verified'] ?? false),
            name: isset($claims['name']) ? trim((string) $claims['name']) ?: null : null,
            nonce: isset($claims['nonce']) ? (string) $claims['nonce'] : null,
        );
    }

    private function verifyFacebook(string $credential, array $config): SocialIdentity
    {
        $appId = trim((string) ($config['app_id'] ?? ''));
        $appSecret = trim((string) ($config['app_secret'] ?? ''));
        $base = rtrim((string) ($config['graph_base_url'] ?? ''), '/');

        if ($appId === '' || $appSecret === '' || ! str_starts_with($base, 'https://')) {
            throw new AuthApiException(503, 'SOCIAL_PROVIDER_NOT_CONFIGURED', 'The social provider is not available.');
        }

        try {
            $debug = Http::acceptJson()
                ->timeout((int) config('tradex_social_auth.http_timeout_seconds', 5))
                ->get($base.'/debug_token', [
                    'input_token' => $credential,
                    'access_token' => $appId.'|'.$appSecret,
                ]);

            if (! $debug->successful()) {
                $this->invalidCredential();
            }

            $data = (array) $debug->json('data', []);
            if (
                ! ($data['is_valid'] ?? false)
                || ! hash_equals($appId, (string) ($data['app_id'] ?? ''))
                || blank($data['user_id'] ?? null)
                || (isset($data['expires_at']) && (int) $data['expires_at'] > 0 && (int) $data['expires_at'] <= time())
                || (isset($data['data_access_expires_at']) && (int) $data['data_access_expires_at'] > 0 && (int) $data['data_access_expires_at'] <= time())
            ) {
                $this->invalidCredential();
            }

            $profile = Http::acceptJson()
                ->timeout((int) config('tradex_social_auth.http_timeout_seconds', 5))
                ->get($base.'/me', [
                    'fields' => 'id,name,email',
                    'access_token' => $credential,
                ]);

            if (! $profile->successful()) {
                $this->invalidCredential();
            }

            $subject = (string) $profile->json('id', '');
            if ($subject === '' || ! hash_equals((string) $data['user_id'], $subject)) {
                $this->invalidCredential();
            }

            $email = mb_strtolower(trim((string) $profile->json('email', ''))) ?: null;

            return new SocialIdentity(
                provider: 'facebook',
                subject: $subject,
                email: $email,
                emailVerified: false,
                name: trim((string) $profile->json('name', '')) ?: null,
            );
        } catch (ConnectionException) {
            throw new AuthApiException(503, 'SOCIAL_PROVIDER_UNAVAILABLE', 'The social provider could not be reached.');
        }
    }

    private function resolveJwk(string $url, string $kid): array
    {
        if (! str_starts_with($url, 'https://')) {
            throw new AuthApiException(503, 'SOCIAL_PROVIDER_NOT_CONFIGURED', 'The social provider is not available.');
        }

        $cacheKey = 'tradex-social-jwks:'.hash('sha256', $url);
        $keys = $this->fetchJwks($url, $cacheKey, false);
        $key = $this->findKey($keys, $kid);

        if ($key !== null) {
            return $key;
        }

        Cache::forget($cacheKey);
        $keys = $this->fetchJwks($url, $cacheKey, true);
        $key = $this->findKey($keys, $kid);

        if ($key === null) {
            $this->invalidCredential();
        }

        return $key;
    }

    private function fetchJwks(string $url, string $cacheKey, bool $fresh): array
    {
        try {
            $callback = function () use ($url): array {
                $response = Http::acceptJson()
                    ->timeout((int) config('tradex_social_auth.http_timeout_seconds', 5))
                    ->get($url);

                if (! $response->successful() || ! is_array($response->json('keys'))) {
                    throw new AuthApiException(503, 'SOCIAL_PROVIDER_UNAVAILABLE', 'The social provider verification keys are unavailable.');
                }

                return (array) $response->json('keys');
            };

            if ($fresh) {
                $keys = $callback();
                Cache::put($cacheKey, $keys, (int) config('tradex_social_auth.jwks_cache_seconds', 3600));
                return $keys;
            }

            return Cache::remember(
                $cacheKey,
                (int) config('tradex_social_auth.jwks_cache_seconds', 3600),
                $callback
            );
        } catch (ConnectionException) {
            throw new AuthApiException(503, 'SOCIAL_PROVIDER_UNAVAILABLE', 'The social provider verification keys are unavailable.');
        }
    }

    private function findKey(array $keys, string $kid): ?array
    {
        foreach ($keys as $key) {
            if (is_array($key) && isset($key['kid']) && hash_equals((string) $key['kid'], $kid)) {
                return $key;
            }
        }
        return null;
    }

    private function decodeJsonSegment(string $segment): array
    {
        $decoded = $this->base64UrlDecode($segment);
        try {
            $value = json_decode($decoded, true, 16, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            $this->invalidCredential();
        }
        if (! is_array($value)) {
            $this->invalidCredential();
        }
        return $value;
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = (4 - (strlen($value) % 4)) % 4;
        $decoded = base64_decode(strtr($value, '-_', '+/').str_repeat('=', $padding), true);
        if ($decoded === false) {
            $this->invalidCredential();
        }
        return $decoded;
    }

    private function jwkToPem(array $jwk): string
    {
        if (blank($jwk['n'] ?? null) || blank($jwk['e'] ?? null)) {
            $this->invalidCredential();
        }
        $modulus = $this->base64UrlDecode((string) $jwk['n']);
        $exponent = $this->base64UrlDecode((string) $jwk['e']);
        $rsa = $this->asn1Sequence($this->asn1Integer($modulus).$this->asn1Integer($exponent));
        $algorithm = hex2bin('300d06092a864886f70d0101010500');
        $spki = $this->asn1Sequence($algorithm."\x03".$this->asn1Length(strlen($rsa) + 1)."\x00".$rsa);
        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($spki), 64, "\n")."-----END PUBLIC KEY-----\n";
    }

    private function asn1Sequence(string $value): string
    {
        return "\x30".$this->asn1Length(strlen($value)).$value;
    }

    private function asn1Integer(string $value): string
    {
        $value = ltrim($value, "\x00");
        if ($value === '') { $value = "\x00"; }
        if ((ord($value[0]) & 0x80) !== 0) { $value = "\x00".$value; }
        return "\x02".$this->asn1Length(strlen($value)).$value;
    }

    private function asn1Length(int $length): string
    {
        if ($length < 128) { return chr($length); }
        $encoded = '';
        while ($length > 0) {
            $encoded = chr($length & 0xff).$encoded;
            $length >>= 8;
        }
        return chr(0x80 | strlen($encoded)).$encoded;
    }

    private function toBoolean(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }

    private function invalidCredential(): never
    {
        throw new AuthApiException(401, 'INVALID_SOCIAL_CREDENTIAL', 'The social provider credential is invalid or expired.');
    }
}
