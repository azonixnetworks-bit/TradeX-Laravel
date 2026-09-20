<?php

namespace App\Services\Payments;

use App\Exceptions\AuthApiException;
use App\Models\IntegrationProviderState;

class PaymentProviderRuntimeGate
{
    public function __construct(
        private readonly StripeConfigurationSafety $stripeConfiguration,
        private readonly PayPalConfigurationSafety $paypalConfiguration,
        private readonly NowPaymentsConfigurationSafety $nowPaymentsConfiguration
    ) {
    }

    public function available(
        string $provider,
        string $country
    ): bool {
        $provider = strtoupper(trim($provider));
        $country = strtoupper(trim($country));

        if ($country === '') {
            return false;
        }

        $source = $this->sourceRuntime(
            $provider
        );

        if ($source === null) {
            return false;
        }

        if (! $source['enabled']) {
            return false;
        }

        if (! $this->countryAllowed(
            $country,
            $source['countries']
        )) {
            return false;
        }

        if (! $this->configured($provider)) {
            return false;
        }

        $state = IntegrationProviderState::query()
            ->where(
                'provider_code',
                $provider
            )
            ->first();

        if (
            ! $state instanceof IntegrationProviderState
            || ! $state->is_enabled
            || ! $state->eligibility_confirmed
        ) {
            return false;
        }

        $approvedCountries = $this->countries(
            $state->countries ?? []
        );

        if (! $this->countryAllowed(
            $country,
            $approvedCountries
        )) {
            return false;
        }

        return strtoupper(
            (string) $state->environment
        ) === $source['environment'];
    }

    public function assertAvailable(
        string $provider,
        string $country
    ): void {
        if (! $this->available(
            $provider,
            $country
        )) {
            throw new AuthApiException(
                422,
                'PAYMENT_PROVIDER_NOT_RUNTIME_APPROVED',
                'This payment provider is not currently approved and ready for this account.'
            );
        }
    }

    private function sourceRuntime(
        string $provider
    ): ?array {
        return match ($provider) {
            'PAYPAL' => [
                'enabled' =>
                    (bool) config(
                        'tradex_payments.paypal.enabled',
                        false
                    ),

                'countries' =>
                    $this->countries(
                        (array) config(
                            'tradex_payments.paypal.countries',
                            []
                        )
                    ),

                'environment' =>
                    strtoupper(trim(
                        (string) config(
                            'tradex_payments.paypal.environment',
                            'SANDBOX'
                        )
                    )),
            ],

            'STRIPE' => [
                'enabled' =>
                    (bool) config(
                        'tradex_integrations.stripe.enabled',
                        false
                    ),

                'countries' =>
                    $this->countries(
                        (array) config(
                            'tradex_integrations.stripe.countries',
                            []
                        )
                    ),

                'environment' =>
                    strtoupper(trim(
                        (string) config(
                            'tradex_integrations.stripe.environment',
                            'SANDBOX'
                        )
                    )),
            ],

            'NOWPAYMENTS' => [
                'enabled' =>
                    (bool) config(
                        'tradex_integrations.nowpayments.enabled',
                        false
                    ),

                'countries' =>
                    $this->countries(
                        (array) config(
                            'tradex_integrations.nowpayments.countries',
                            []
                        )
                    ),

                'environment' =>
                    strtoupper(trim(
                        (string) config(
                            'tradex_integrations.nowpayments.environment',
                            'SANDBOX'
                        )
                    )),
            ],

            default => null,
        };
    }

    private function configured(
        string $provider
    ): bool {
        return match ($provider) {
            'PAYPAL' =>
                $this->paypalConfiguration
                    ->configured(),

            'STRIPE' =>
                $this->stripeConfiguration
                    ->configured(),

            'NOWPAYMENTS' =>
                $this->nowPaymentsConfiguration
                    ->configured(),

            default => false,
        };
    }

    private function countryAllowed(
        string $country,
        array $values
    ): bool {
        $countries = $this->countries(
            $values
        );

        return in_array(
            '*',
            $countries,
            true
        )
            || in_array(
                $country,
                $countries,
                true
            );
    }

    private function countries(
        array $values
    ): array {
        return array_values(
            array_unique(
                array_filter(
                    array_map(
                        static fn (
                            mixed $value
                        ): string =>
                            strtoupper(trim(
                                (string) $value
                            )),
                        $values
                    ),
                    static fn (
                        string $value
                    ): bool =>
                        $value === '*'
                        || preg_match(
                            '/^[A-Z]{2}$/',
                            $value
                        ) === 1
                )
            )
        );
    }
}
