<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Route;

class PaymentLiveReadinessService
{
    public function evaluate(bool $production = false): array
    {
        $checks = [];

        $this->stripeChecks($checks, $production);
        $this->nowPaymentsChecks($checks, $production);

        if ($production) {
            $this->add(
                $checks,
                'Application environment',
                app()->environment('production'),
                'critical',
                'Production payment activation requires APP_ENV=production.'
            );

            $this->add(
                $checks,
                'Application debug disabled',
                ! (bool) config('app.debug', false),
                'critical',
                'APP_DEBUG must be false before live payment credentials are enabled.'
            );
        }

        $failedCritical = collect($checks)
            ->filter(fn (array $check): bool =>
                $check['severity'] === 'critical'
                && ! $check['passed'])
            ->count();

        $failedWarnings = collect($checks)
            ->filter(fn (array $check): bool =>
                $check['severity'] === 'warning'
                && ! $check['passed'])
            ->count();

        return [
            'mode' => $production ? 'PRODUCTION' : 'SANDBOX_OR_LIVE',
            'ready' => $failedCritical === 0,
            'failed_critical' => $failedCritical,
            'failed_warnings' => $failedWarnings,
            'checks' => $checks,
        ];
    }

    private function stripeChecks(array &$checks, bool $production): void
    {
        $environment = strtoupper(trim((string) config(
            'tradex_integrations.stripe.environment',
            'SANDBOX'
        )));

        $publishable = trim((string) config('tradex_integrations.stripe.publishable_key'));
        $secret = trim((string) config('tradex_integrations.stripe.secret_key'));
        $webhookSecret = trim((string) config('tradex_integrations.stripe.webhook_secret'));
        $baseUrl = trim((string) config('tradex_integrations.stripe.base_url'));
        $successUrl = trim((string) config('tradex_integrations.stripe.success_url'));
        $cancelUrl = trim((string) config('tradex_integrations.stripe.cancel_url'));
        $countries = (array) config('tradex_integrations.stripe.countries', []);

        $expectedPublicPrefix = $environment === 'LIVE' ? 'pk_live_' : 'pk_test_';
        $expectedSecretPrefix = $environment === 'LIVE' ? 'sk_live_' : 'sk_test_';

        $this->add(
            $checks,
            'Stripe enabled',
            (bool) config('tradex_integrations.stripe.enabled', false),
            'critical',
            'TRADEX_STRIPE_ENABLED must be true for the Stripe runtime.'
        );

        $this->add(
            $checks,
            'Stripe environment',
            in_array($environment, ['SANDBOX', 'LIVE'], true)
                && (! $production || $environment === 'LIVE'),
            'critical',
            $production
                ? 'Production requires TRADEX_STRIPE_ENVIRONMENT=LIVE.'
                : 'Stripe environment must be SANDBOX or LIVE.'
        );

        $this->add(
            $checks,
            'Stripe country scope',
            $this->validCountries($countries),
            'critical',
            'Stripe must have at least one explicit ISO-3166 alpha-2 country code.'
        );

        $this->add(
            $checks,
            'Stripe publishable key',
            str_starts_with($publishable, $expectedPublicPrefix),
            'critical',
            'Stripe publishable key must match the configured environment. The value is never displayed.'
        );

        $this->add(
            $checks,
            'Stripe secret key',
            str_starts_with($secret, $expectedSecretPrefix),
            'critical',
            'Stripe secret key must match the configured environment. The value is never displayed.'
        );

        $this->add(
            $checks,
            'Stripe webhook signing secret',
            str_starts_with($webhookSecret, 'whsec_'),
            'critical',
            'Stripe webhook signing secret must be configured. The value is never displayed.'
        );

        $this->add(
            $checks,
            'Stripe API endpoint',
            $this->httpsUrl($baseUrl)
                && strtolower((string) parse_url($baseUrl, PHP_URL_HOST)) === 'api.stripe.com',
            'critical',
            'Stripe API base URL must use https://api.stripe.com.'
        );

        $this->add(
            $checks,
            'Stripe success URL',
            $this->publicHttpsUrl($successUrl),
            'critical',
            'Stripe success URL must be a public HTTPS URL.'
        );

        $this->add(
            $checks,
            'Stripe cancel URL',
            $this->publicHttpsUrl($cancelUrl),
            'critical',
            'Stripe cancel URL must be a public HTTPS URL.'
        );

        $this->add(
            $checks,
            'Stripe webhook route',
            Route::has('api.v1.payments.stripe.webhook')
                && $this->publicHttpsUrl(route('api.v1.payments.stripe.webhook')),
            'critical',
            'Stripe webhook route must resolve to a public HTTPS URL.'
        );

        $tolerance = (int) config(
            'tradex_integrations.stripe.webhook_tolerance_seconds',
            0
        );

        $this->add(
            $checks,
            'Stripe webhook tolerance',
            $tolerance >= 60 && $tolerance <= 900,
            'critical',
            'Stripe webhook timestamp tolerance must remain between 60 and 900 seconds.'
        );
    }

    private function nowPaymentsChecks(array &$checks, bool $production): void
    {
        $environment = strtoupper(trim((string) config(
            'tradex_integrations.nowpayments.environment',
            'SANDBOX'
        )));

        $apiKey = trim((string) config('tradex_integrations.nowpayments.api_key'));
        $ipnSecret = trim((string) config('tradex_integrations.nowpayments.ipn_secret'));
        $successUrl = trim((string) config('tradex_integrations.nowpayments.success_url'));
        $cancelUrl = trim((string) config('tradex_integrations.nowpayments.cancel_url'));
        $countries = (array) config('tradex_integrations.nowpayments.countries', []);
        $selectedBaseUrl = $environment === 'LIVE'
            ? trim((string) config('tradex_integrations.nowpayments.live_base_url'))
            : trim((string) config('tradex_integrations.nowpayments.sandbox_base_url'));

        $this->add(
            $checks,
            'NOWPayments enabled',
            (bool) config('tradex_integrations.nowpayments.enabled', false),
            'critical',
            'TRADEX_NOWPAYMENTS_ENABLED must be true for the NOWPayments runtime.'
        );

        $this->add(
            $checks,
            'NOWPayments environment',
            in_array($environment, ['SANDBOX', 'LIVE'], true)
                && (! $production || $environment === 'LIVE'),
            'critical',
            $production
                ? 'Production requires TRADEX_NOWPAYMENTS_ENVIRONMENT=LIVE.'
                : 'NOWPayments environment must be SANDBOX or LIVE.'
        );

        $this->add(
            $checks,
            'NOWPayments country scope',
            $this->validCountries($countries),
            'critical',
            'NOWPayments must have at least one explicit ISO-3166 alpha-2 country code.'
        );

        $this->add(
            $checks,
            'NOWPayments API key',
            $apiKey !== '',
            'critical',
            'NOWPayments API key must be configured. The value is never displayed.'
        );

        $this->add(
            $checks,
            'NOWPayments IPN secret',
            $ipnSecret !== '',
            'critical',
            'NOWPayments IPN secret must be configured. The value is never displayed.'
        );

        $baseHost = strtolower((string) parse_url($selectedBaseUrl, PHP_URL_HOST));
        $baseUrlValid = $this->publicHttpsUrl($selectedBaseUrl)
            && ($environment !== 'LIVE' || $baseHost === 'api.nowpayments.io');

        $this->add(
            $checks,
            'NOWPayments API endpoint',
            $baseUrlValid,
            'critical',
            $environment === 'LIVE'
                ? 'Live NOWPayments must use https://api.nowpayments.io.'
                : 'NOWPayments sandbox base URL must be a public HTTPS URL.'
        );

        $this->add(
            $checks,
            'NOWPayments success URL',
            $this->publicHttpsUrl($successUrl),
            'critical',
            'NOWPayments success URL must be a public HTTPS URL.'
        );

        $this->add(
            $checks,
            'NOWPayments cancel URL',
            $this->publicHttpsUrl($cancelUrl),
            'critical',
            'NOWPayments cancel URL must be a public HTTPS URL.'
        );

        $this->add(
            $checks,
            'NOWPayments IPN route',
            Route::has('api.v1.payments.nowpayments.webhook')
                && $this->publicHttpsUrl(route('api.v1.payments.nowpayments.webhook')),
            'critical',
            'NOWPayments IPN callback route must resolve to a public HTTPS URL.'
        );
    }

    private function validCountries(array $countries): bool
    {
        if ($countries === []) {
            return false;
        }

        foreach ($countries as $country) {
            if (preg_match('/^[A-Z]{2}$/', strtoupper(trim((string) $country))) !== 1) {
                return false;
            }
        }

        return true;
    }

    private function httpsUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
    }

    private function publicHttpsUrl(string $url): bool
    {
        if (! $this->httpsUrl($url)) {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) !== false;
        }

        return str_contains($host, '.');
    }

    private function add(
        array &$checks,
        string $name,
        bool $passed,
        string $severity,
        string $detail
    ): void {
        $checks[] = [
            'name' => $name,
            'passed' => $passed,
            'severity' => $severity,
            'detail' => $detail,
        ];
    }
}
