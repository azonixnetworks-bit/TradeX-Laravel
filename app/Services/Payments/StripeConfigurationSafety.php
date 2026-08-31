<?php

namespace App\Services\Payments;

use App\Exceptions\AuthApiException;

class StripeConfigurationSafety
{
    public function configured(): bool
    {
        return ! in_array(
            false,
            $this->checks(),
            true
        );
    }

    public function assertConfigured(): void
    {
        if (! $this->configured()) {
            throw new AuthApiException(
                503,
                'STRIPE_CONFIGURATION_UNSAFE',
                'Stripe is not safely configured for the selected runtime environment.'
            );
        }
    }

    public function checks(): array
    {
        $environment = strtoupper(trim((string) config(
            'tradex_integrations.stripe.environment',
            'SANDBOX'
        )));

        $publishableKey = trim((string) config(
            'tradex_integrations.stripe.publishable_key'
        ));

        $secretKey = trim((string) config(
            'tradex_integrations.stripe.secret_key'
        ));

        $webhookSecret = trim((string) config(
            'tradex_integrations.stripe.webhook_secret'
        ));

        $baseUrl = rtrim(trim((string) config(
            'tradex_integrations.stripe.base_url',
            'https://api.stripe.com'
        )), '/');

        $successUrl = trim((string) config(
            'tradex_integrations.stripe.success_url'
        ));

        $cancelUrl = trim((string) config(
            'tradex_integrations.stripe.cancel_url'
        ));

        $isSandbox = $environment === 'SANDBOX';
        $isLive = $environment === 'LIVE';

        $publishablePrefix = $isLive ? 'pk_live_' : 'pk_test_';
        $secretPrefix = $isLive ? 'sk_live_' : 'sk_test_';

        return [
            'environment_supported' => $isSandbox || $isLive,
            'publishable_key_matches_environment' =>
                ($isSandbox || $isLive)
                && str_starts_with($publishableKey, $publishablePrefix),
            'secret_key_matches_environment' =>
                ($isSandbox || $isLive)
                && str_starts_with($secretKey, $secretPrefix),
            'webhook_secret_valid' =>
                str_starts_with($webhookSecret, 'whsec_')
                && strlen($webhookSecret) > 6,

            /*
             * Never allow the Stripe secret key to be sent to an arbitrary
             * deployment-controlled host.
             */
            'official_api_endpoint' => hash_equals(
                'https://api.stripe.com',
                $baseUrl
            ),
            'success_url_valid' => $this->redirectUrlIsAllowed(
                $successUrl,
                $isLive
            ),
            'cancel_url_valid' => $this->redirectUrlIsAllowed(
                $cancelUrl,
                $isLive
            ),
        ];
    }

    private function redirectUrlIsAllowed(string $url, bool $live): bool
    {
        if (
            $url === ''
            || filter_var($url, FILTER_VALIDATE_URL) === false
        ) {
            return false;
        }

        $scheme = strtolower((string) parse_url(
            $url,
            PHP_URL_SCHEME
        ));

        if ($live) {
            return $scheme === 'https';
        }

        return in_array($scheme, ['http', 'https'], true);
    }
}
