<?php

namespace App\Services\Payments;

use App\Exceptions\AuthApiException;
use App\Models\PaymentOrder;
use App\Models\User;

class PaymentMethodService
{
    public function __construct(
        private readonly PaymentMoney $money,
        private readonly PaymentProviderRuntimeGate
            $runtimeGate
    ) {
    }

    public function methodsFor(
        User $user
    ): array {
        $country = strtoupper(
            (string) $user->country_code
        );

        $methods = [];

        if ($this->countryAllowed(
            $country,
            (array) config(
                'tradex_payments.paypal.countries',
                []
            )
        )) {
            $methods[] =
                $this->paypalMethod(
                    $country
                );
        }

        if ($this->countryAllowed(
            $country,
            (array) config(
                'tradex_integrations.stripe.countries',
                []
            )
        )) {
            $methods[] =
                $this->stripeMethod(
                    $country
                );
        }

        if ($this->countryAllowed(
            $country,
            (array) config(
                'tradex_integrations.nowpayments.countries',
                []
            )
        )) {
            $methods[] =
                $this->nowPaymentsMethod(
                    $country
                );
        }

        if ($this->countryAllowed(
            $country,
            (array) config(
                'tradex_payments.local_bank.countries',
                []
            )
        )) {
            $methods[] =
                $this->localBankMethod(
                    $country
                );
        }

        if ($this->countryAllowed(
            $country,
            (array) config(
                'tradex_payments.usdt_trc20.countries',
                []
            )
        )) {
            $methods[] =
                $this->usdtMethod(
                    $country
                );
        }

        return [
            'country_code' => $country,
            'product' => [
                'code' =>
                    PaymentOrder::PRODUCT_EDUCATION_PREMIUM,

                'name' =>
                    (string) config(
                        'tradex_payments.product.education_premium.name',
                        'TradeX Education Premium'
                    ),
            ],

            'purchase_allowed' =>
                ! $user->canAccessPremium(),

            'methods' => $methods,
        ];
    }

    public function assertAvailable(
        User $user,
        string $provider
    ): array {
        if ($user->canAccessPremium()) {
            throw new AuthApiException(
                409,
                'PREMIUM_ALREADY_ACTIVE',
                'Premium access is already active for this account.'
            );
        }

        $method = collect(
            $this->methodsFor(
                $user
            )['methods']
        )->firstWhere(
            'code',
            $provider
        );

        if (
            ! is_array($method)
            || ! ($method['available'] ?? false)
        ) {
            throw new AuthApiException(
                422,
                'PAYMENT_METHOD_UNAVAILABLE',
                'This payment method is not currently available for this account.'
            );
        }

        return $method;
    }

    private function paypalMethod(
        string $country
    ): array {
        $amount =
            (int) config(
                'tradex_payments.product.education_premium.paypal.amount_minor',
                7900
            );

        $currency =
            (string) config(
                'tradex_payments.product.education_premium.paypal.currency',
                'USD'
            );

        $enabled =
            (bool) config(
                'tradex_payments.paypal.enabled',
                true
            );

        $configured = $this
            ->paypalConfigured();

        return [
            'code' =>
                PaymentOrder::PROVIDER_PAYPAL,

            'display_name' =>
                'PayPal',

            'mode' =>
                'ONLINE_REDIRECT',

            'country_code' =>
                $country,

            'available' =>
                $enabled
                && $configured
                && $amount > 0,

            'unavailable_reason' =>
                $enabled
                && $configured
                && $amount > 0
                    ? null
                    : 'CONFIGURATION_REQUIRED',

            'amount' =>
                $this->money->present(
                    $amount,
                    $currency
                ),

            'create_endpoint' =>
                'POST /api/v1/payments/paypal/orders',
        ];
    }

    private function stripeMethod(
        string $country
    ): array {
        $amount =
            (int) config(
                'tradex_payments.product.education_premium.stripe.amount_minor',
                7900
            );

        $currency =
            (string) config(
                'tradex_payments.product.education_premium.stripe.currency',
                'USD'
            );

        $available =
            $amount > 0
            && $this->runtimeGate
                ->available(
                    PaymentOrder::PROVIDER_STRIPE,
                    $country
                );

        return [
            'code' =>
                PaymentOrder::PROVIDER_STRIPE,

            'display_name' =>
                'Stripe',

            'mode' =>
                'ONLINE_REDIRECT',

            'country_code' =>
                $country,

            'available' =>
                $available,

            'unavailable_reason' =>
                $available
                    ? null
                    : 'RUNTIME_APPROVAL_OR_CONFIGURATION_REQUIRED',

            'amount' =>
                $this->money->present(
                    $amount,
                    $currency
                ),

            'create_endpoint' =>
                'POST /api/v1/payments/stripe/checkout-sessions',
        ];
    }

    private function nowPaymentsMethod(
        string $country
    ): array {
        $amount =
            (int) config(
                'tradex_payments.product.education_premium.nowpayments.amount_minor',
                7900
            );

        $currency =
            (string) config(
                'tradex_payments.product.education_premium.nowpayments.currency',
                'USD'
            );

        $available =
            $amount > 0
            && $this->runtimeGate
                ->available(
                    PaymentOrder::PROVIDER_NOWPAYMENTS,
                    $country
                );

        return [
            'code' =>
                PaymentOrder::PROVIDER_NOWPAYMENTS,

            'display_name' =>
                'NOWPayments',

            'mode' =>
                'ONLINE_REDIRECT',

            'country_code' =>
                $country,

            'available' =>
                $available,

            'unavailable_reason' =>
                $available
                    ? null
                    : 'RUNTIME_APPROVAL_OR_CONFIGURATION_REQUIRED',

            'amount' =>
                $this->money->present(
                    $amount,
                    $currency
                ),

            'create_endpoint' =>
                'POST /api/v1/payments/nowpayments/invoices',
        ];
    }

    private function paypalConfigured(): bool
    {
        $driver = strtolower(
            trim(
                (string) config(
                    'tradex_payments.paypal.driver',
                    'http'
                )
            )
        );

        if ($driver === 'srmklive') {
            $mode = strtolower(
                trim(
                    (string) config(
                        'paypal.mode',
                        'sandbox'
                    )
                )
            );

            if (! in_array(
                $mode,
                ['sandbox', 'live'],
                true
            )) {
                return false;
            }

            return filled(
                config(
                    'paypal.'.$mode.'.client_id'
                )
            )
                && filled(
                    config(
                        'paypal.'.$mode.'.client_secret'
                    )
                );
        }

        return filled(
            config(
                'tradex_payments.paypal.client_id'
            )
        )
            && filled(
                config(
                    'tradex_payments.paypal.client_secret'
                )
            );
    }

    private function localBankMethod(
        string $country
    ): array {
        $amount =
            (int) config(
                'tradex_payments.product.education_premium.local_bank.amount_minor',
                0
            );

        $currency =
            (string) config(
                'tradex_payments.product.education_premium.local_bank.currency',
                'LKR'
            );

        $enabled =
            (bool) config(
                'tradex_payments.local_bank.enabled',
                true
            );

        $bankName = trim(
            (string) config(
                'tradex_payments.local_bank.bank_name'
            )
        );

        $branch = trim(
            (string) config(
                'tradex_payments.local_bank.branch'
            )
        );

        $holder = trim(
            (string) config(
                'tradex_payments.local_bank.account_holder'
            )
        );

        $account = trim(
            (string) config(
                'tradex_payments.local_bank.account_number'
            )
        );

        $configured =
            $amount > 0
            && $bankName !== ''
            && $branch !== ''
            && $holder !== ''
            && $account !== '';

        return [
            'code' =>
                PaymentOrder::PROVIDER_LOCAL_BANK,

            'display_name' =>
                'Local Bank Deposit',

            'mode' =>
                'MANUAL_REVIEW',

            'country_code' =>
                $country,

            'available' =>
                $enabled
                && $configured,

            'unavailable_reason' =>
                $enabled
                && $configured
                    ? null
                    : 'CONFIGURATION_REQUIRED',

            'amount' =>
                $this->money->present(
                    $amount,
                    $currency
                ),

            'instructions' => [
                'bank_name' => $bankName,
                'branch' => $branch,
                'account_holder' => $holder,
                'account_number' => $account,
            ],

            'submit_endpoint' =>
                'POST /api/v1/payments/bank-deposits',
        ];
    }

    private function usdtMethod(
        string $country
    ): array {
        $amount =
            (int) config(
                'tradex_payments.product.education_premium.usdt_trc20.amount_minor',
                79000000
            );

        $currency =
            (string) config(
                'tradex_payments.product.education_premium.usdt_trc20.currency',
                'USDT'
            );

        $enabled =
            (bool) config(
                'tradex_payments.usdt_trc20.enabled',
                true
            );

        $wallet = trim(
            (string) config(
                'tradex_payments.usdt_trc20.wallet_address'
            )
        );

        $configured =
            $amount > 0
            && $wallet !== '';

        return [
            'code' =>
                PaymentOrder::PROVIDER_USDT_TRC20,

            'display_name' =>
                'USDT TRC20',

            'mode' =>
                'MANUAL_REVIEW',

            'country_code' =>
                $country,

            'available' =>
                $enabled
                && $configured,

            'unavailable_reason' =>
                $enabled
                && $configured
                    ? null
                    : 'CONFIGURATION_REQUIRED',

            'amount' =>
                $this->money->present(
                    $amount,
                    $currency
                ),

            'instructions' => [
                'token' => 'USDT',
                'network' => 'TRC20',
                'wallet_address' => $wallet,
                'exact_amount_required' => true,
            ],

            'submit_endpoint' =>
                'POST /api/v1/payments/usdt-deposits',
        ];
    }

    private function countryAllowed(
        string $country,
        array $countries
    ): bool {
        return in_array(
            $country,
            array_map(
                static fn (
                    mixed $value
                ): string =>
                    strtoupper(
                        trim(
                            (string) $value
                        )
                    ),
                $countries
            ),
            true
        );
    }
}
