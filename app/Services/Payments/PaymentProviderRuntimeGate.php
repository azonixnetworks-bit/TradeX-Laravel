<?php

namespace App\Services\Payments;

use App\Exceptions\AuthApiException;
use App\Models\IntegrationProviderState;

class PaymentProviderRuntimeGate
{
    public function __construct(
        private readonly StripeConfigurationSafety $stripeConfiguration
    ) {
    }

    public function available(string $provider, string $country): bool
    {
        $provider = strtoupper(trim($provider));
        $country = strtoupper(trim($country));

        $key = match ($provider) {
            'STRIPE' => 'stripe',
            'NOWPAYMENTS' => 'nowpayments',
            default => null,
        };

        if ($key === null || $country === '') {
            return false;
        }

        if (! (bool) config('tradex_integrations.'.$key.'.enabled', false)) {
            return false;
        }

        $sourceCountries = $this->countries(
            (array) config('tradex_integrations.'.$key.'.countries', [])
        );

        if (! in_array($country, $sourceCountries, true)) {
            return false;
        }

        if (! $this->configured($provider)) {
            return false;
        }

        $state = IntegrationProviderState::query()
            ->where('provider_code', $provider)
            ->first();

        if (
            ! $state instanceof IntegrationProviderState
            || ! $state->is_enabled
            || ! $state->eligibility_confirmed
        ) {
            return false;
        }

        $approvedCountries = $this->countries($state->countries ?? []);

        if (! in_array($country, $approvedCountries, true)) {
            return false;
        }

        $sourceEnvironment = strtoupper((string) config(
            'tradex_integrations.'.$key.'.environment',
            'SANDBOX'
        ));

        return strtoupper((string) $state->environment) === $sourceEnvironment;
    }

    public function assertAvailable(string $provider, string $country): void
    {
        if (! $this->available($provider, $country)) {
            throw new AuthApiException(
                422,
                'PAYMENT_PROVIDER_NOT_RUNTIME_APPROVED',
                'This payment provider is not currently approved and ready for this account.'
            );
        }
    }

    private function configured(string $provider): bool
    {
        return match ($provider) {
            'STRIPE' => $this->stripeConfiguration->configured(),
            'NOWPAYMENTS' => filled(config('tradex_integrations.nowpayments.api_key'))
                && filled(config('tradex_integrations.nowpayments.ipn_secret'))
                && filled(config('tradex_integrations.nowpayments.success_url'))
                && filled(config('tradex_integrations.nowpayments.cancel_url')),
            default => false,
        };
    }

    private function countries(array $values): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => strtoupper(trim((string) $value)),
            $values
        ), static fn (string $value): bool => preg_match('/^[A-Z]{2}$/', $value) === 1)));
    }
}
