<?php

namespace App\Services\Payments;

use App\Exceptions\AuthApiException;

class PayPalConfigurationSafety
{
    public function configured(): bool
    {
        return ! in_array(
            false,
            $this->checks(),
            true
        );
    }

    public function apiConfigured(): bool
    {
        return ! in_array(
            false,
            $this->apiChecks(),
            true
        );
    }

    public function webhookConfigured(): bool
    {
        return $this->apiConfigured()
            && $this->webhookIdConfigured();
    }

    public function assertConfigured(): void
    {
        if (! $this->configured()) {
            $this->unsafe();
        }
    }

    public function assertApiConfigured(): void
    {
        if (! $this->apiConfigured()) {
            $this->unsafe();
        }
    }

    public function assertWebhookConfigured(): void
    {
        if (! $this->webhookConfigured()) {
            $this->unsafe();
        }
    }

    public function checks(): array
    {
        return [
            ...$this->apiChecks(),

            'webhook_id_configured' =>
                $this->webhookIdConfigured(),

            'return_url_valid' =>
                $this->redirectUrlIsAllowed(
                    trim((string) config(
                        'tradex_payments.paypal.return_url'
                    )),
                    $this->isLive()
                ),

            'cancel_url_valid' =>
                $this->redirectUrlIsAllowed(
                    trim((string) config(
                        'tradex_payments.paypal.cancel_url'
                    )),
                    $this->isLive()
                ),
        ];
    }

    private function apiChecks(): array
    {
        $driver = $this->driver();
        $environment = $this->environment();
        $isSandbox = $environment === 'sandbox';
        $isLive = $environment === 'live';

        [$clientId, $clientSecret] =
            $this->credentials(
                $driver,
                $environment
            );

        $baseUrl = $this->selectedBaseUrl(
            $environment
        );

        $expectedBaseUrl = match ($environment) {
            'sandbox' =>
                'https://api-m.sandbox.paypal.com',
            'live' =>
                'https://api-m.paypal.com',
            default =>
                '',
        };

        $pluginMode = strtolower(trim(
            (string) config(
                'paypal.mode',
                'sandbox'
            )
        ));

        return [
            'driver_supported' =>
                in_array(
                    $driver,
                    ['http', 'srmklive'],
                    true
                ),

            'environment_supported' =>
                $isSandbox || $isLive,

            'plugin_environment_matches' =>
                $driver !== 'srmklive'
                || (
                    ($isSandbox || $isLive)
                    && $pluginMode === $environment
                ),

            'client_id_configured' =>
                $clientId !== '',

            'client_secret_configured' =>
                $clientSecret !== '',

            /*
             * Credentials must never be sent to a deployment-controlled host.
             * The selected runtime endpoint has to be PayPal's official host.
             */
            'official_api_endpoint' =>
                $expectedBaseUrl !== ''
                && hash_equals(
                    $expectedBaseUrl,
                    $baseUrl
                ),
        ];
    }

    private function credentials(
        string $driver,
        string $environment
    ): array {
        if (
            $driver === 'srmklive'
            && in_array(
                $environment,
                ['sandbox', 'live'],
                true
            )
        ) {
            return [
                trim((string) config(
                    'paypal.'
                    .$environment
                    .'.client_id'
                )),
                trim((string) config(
                    'paypal.'
                    .$environment
                    .'.client_secret'
                )),
            ];
        }

        return [
            trim((string) config(
                'tradex_payments.paypal.client_id'
            )),
            trim((string) config(
                'tradex_payments.paypal.client_secret'
            )),
        ];
    }

    private function selectedBaseUrl(
        string $environment
    ): string {
        $value = match ($environment) {
            'live' =>
                config(
                    'tradex_payments.paypal.live_base_url'
                ),
            'sandbox' =>
                config(
                    'tradex_payments.paypal.sandbox_base_url'
                ),
            default =>
                '',
        };

        return rtrim(
            trim((string) $value),
            '/'
        );
    }

    private function webhookIdConfigured(): bool
    {
        return trim((string) config(
            'tradex_payments.paypal.webhook_id'
        )) !== '';
    }

    private function driver(): string
    {
        return strtolower(trim(
            (string) config(
                'tradex_payments.paypal.driver',
                'http'
            )
        ));
    }

    private function environment(): string
    {
        return strtolower(trim(
            (string) config(
                'tradex_payments.paypal.environment',
                'sandbox'
            )
        ));
    }

    private function isLive(): bool
    {
        return $this->environment() === 'live';
    }

    private function redirectUrlIsAllowed(
        string $url,
        bool $live
    ): bool {
        if (
            $url === ''
            || filter_var(
                $url,
                FILTER_VALIDATE_URL
            ) === false
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

        return in_array(
            $scheme,
            ['http', 'https'],
            true
        );
    }

    private function unsafe(): never
    {
        throw new AuthApiException(
            503,
            'PAYPAL_CONFIGURATION_UNSAFE',
            'PayPal is not safely configured for the selected runtime environment.'
        );
    }
}