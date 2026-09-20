<?php

namespace App\Services\Payments;

use App\Exceptions\AuthApiException;

class NowPaymentsConfigurationSafety
{
    public const LIVE_BASE_URL = 'https://api.nowpayments.io';

    public const SANDBOX_BASE_URL = 'https://api.sandbox.nowpayments.io';

    public function configured(): bool
    {
        return ! in_array(false, $this->checks(), true);
    }

    public function assertConfigured(): void
    {
        if (! $this->configured()) {
            throw new AuthApiException(
                503,
                'NOWPAYMENTS_CONFIGURATION_UNSAFE',
                'NOWPayments is not safely configured for the selected runtime environment.'
            );
        }
    }

    public function checks(): array
    {
        $environment = $this->environment();
        $isSandbox = $environment === 'SANDBOX';
        $isLive = $environment === 'LIVE';
        $expectedBaseUrl = match ($environment) {
            'LIVE' => self::LIVE_BASE_URL,
            'SANDBOX' => self::SANDBOX_BASE_URL,
            default => '',
        };

        return [
            'environment_supported' => $isSandbox || $isLive,
            'api_key_configured' => trim((string) config(
                'tradex_integrations.nowpayments.api_key'
            )) !== '',
            'ipn_secret_configured' => trim((string) config(
                'tradex_integrations.nowpayments.ipn_secret'
            )) !== '',

            /* Never send a merchant API key to a configurable third-party host. */
            'official_api_endpoint' => $expectedBaseUrl !== ''
                && hash_equals($expectedBaseUrl, $this->selectedBaseUrl()),
            'success_url_valid' => $this->publicUrlIsAllowed(
                trim((string) config('tradex_integrations.nowpayments.success_url')),
                $isLive
            ),
            'cancel_url_valid' => $this->publicUrlIsAllowed(
                trim((string) config('tradex_integrations.nowpayments.cancel_url')),
                $isLive
            ),
            'webhook_url_valid' => $this->publicUrlIsAllowed(
                route('api.v1.payments.nowpayments.webhook'),
                $isLive
            ),
        ];
    }

    public function selectedBaseUrl(): string
    {
        $value = match ($this->environment()) {
            'LIVE' => config('tradex_integrations.nowpayments.live_base_url'),
            'SANDBOX' => config('tradex_integrations.nowpayments.sandbox_base_url'),
            default => '',
        };

        return rtrim(trim((string) $value), '/');
    }

    public function invoiceUrlIsAllowed(string $url): bool
    {
        if (! $this->publicUrlIsAllowed($url, true)) {
            return false;
        }

        if ($this->environment() !== 'LIVE') {
            return true;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'nowpayments.io'
            || str_ends_with($host, '.nowpayments.io');
    }

    private function environment(): string
    {
        return strtoupper(trim((string) config(
            'tradex_integrations.nowpayments.environment',
            'SANDBOX'
        )));
    }

    private function publicUrlIsAllowed(string $url, bool $httpsRequired): bool
    {
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === '' || parse_url($url, PHP_URL_USER) !== null) {
            return false;
        }

        if ($httpsRequired && $scheme !== 'https') {
            return false;
        }

        return in_array($scheme, ['http', 'https'], true);
    }
}
