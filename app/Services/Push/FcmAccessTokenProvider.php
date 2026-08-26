<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use JsonException;
use RuntimeException;

class FcmAccessTokenProvider
{
    public function token(): string
    {
        $path = trim((string) config(
            'tradex_push.fcm.credentials_path',
            ''
        ));

        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException(
                'FCM service-account credentials are not readable.'
            );
        }

        $credentials = json_decode(
            (string) file_get_contents($path),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $clientEmail = trim((string) ($credentials['client_email'] ?? ''));
        $privateKey = (string) ($credentials['private_key'] ?? '');
        $tokenUri = trim((string) (
            $credentials['token_uri']
            ?? config('tradex_push.fcm.token_uri')
        ));

        if ($clientEmail === '' || $privateKey === '' || $tokenUri === '') {
            throw new RuntimeException(
                'FCM service-account credentials are incomplete.'
            );
        }

        $cacheKey = 'tradex_push_fcm_oauth_'.hash(
            'sha256',
            $clientEmail.'|'.$tokenUri
        );

        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $now = time();
        $scope = (string) config(
            'tradex_push.fcm.scope',
            'https://www.googleapis.com/auth/firebase.messaging'
        );

        $assertion = $this->signedJwt(
            $clientEmail,
            $privateKey,
            $tokenUri,
            $scope,
            $now
        );

        $response = Http::asForm()
            ->acceptJson()
            ->timeout((int) config(
                'tradex_push.fcm.timeout_seconds',
                10
            ))
            ->post($tokenUri, [
                'grant_type' =>
                    'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'FCM OAuth access token request failed.'
            );
        }

        $accessToken = trim((string) $response->json('access_token'));
        $expiresIn = max(120, (int) $response->json('expires_in', 3600));

        if ($accessToken === '') {
            throw new RuntimeException(
                'FCM OAuth response did not contain an access token.'
            );
        }

        Cache::put(
            $cacheKey,
            $accessToken,
            now()->addSeconds(max(60, $expiresIn - 60))
        );

        return $accessToken;
    }

    private function signedJwt(
        string $clientEmail,
        string $privateKey,
        string $audience,
        string $scope,
        int $now
    ): string {
        $header = $this->base64UrlJson([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ]);

        $claims = $this->base64UrlJson([
            'iss' => $clientEmail,
            'scope' => $scope,
            'aud' => $audience,
            'iat' => $now - 5,
            'exp' => $now + 3500,
        ]);

        $signingInput = $header.'.'.$claims;
        $key = openssl_pkey_get_private($privateKey);

        if ($key === false) {
            throw new RuntimeException('Invalid FCM private key.');
        }

        $signature = '';
        $signed = openssl_sign(
            $signingInput,
            $signature,
            $key,
            OPENSSL_ALGO_SHA256
        );

        if (! $signed) {
            throw new RuntimeException('FCM JWT signing failed.');
        }

        return $signingInput.'.'.$this->base64Url($signature);
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
