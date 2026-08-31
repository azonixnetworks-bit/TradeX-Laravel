<?php

namespace App\Services\Payments;

use App\Exceptions\AuthApiException;
use App\Models\PaymentOrder;
use App\Models\PaymentReceivingAccount;
use App\Models\Product;
use App\Models\User;
use App\Services\Products\ProductCatalogService;

class PaymentMethodService
{
    public function __construct(
        private readonly PaymentMoney $money,
        private readonly PaymentProviderRuntimeGate
            $runtimeGate,
        private readonly ProductCatalogService
            $products
    ) {
    }

    public function methodsFor(
        User $user
    ): array {
        $country = strtoupper(
            (string) $user->country_code
        );

        $product =
            $this->products
                ->educationPremium();

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
                    $country,
                    $product
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
                    $country,
                    $product
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
                    $country,
                    $product
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
                    $country,
                    $product
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
                    $country,
                    $product
                );
        }

        if (
            $this->hasGenericCryptoAccount(
                $country
            )
        ) {
            $methods[] =
                $this->genericCryptoMethod(
                    $country,
                    $product
                );
        }
        return [
            'country_code' => $country,
            'product' => [
                'uuid' =>
                    $product->uuid,
                'code' =>
                    $product->code,
                'name' =>
                    $product->name,
                'billing_type' =>
                    $product
                        ->billing_type,
                'is_active' =>
                    (bool) $product
                        ->is_active,
                'base_price' =>
                    $this->money
                        ->present(
                            (int) $product
                                ->amount_minor,
                            (string) $product
                                ->currency
                        ),
            ],

            'purchase_allowed' =>
                (bool) $product
                    ->is_active
                && ! $user
                    ->canAccessPremium(),

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

        $catalog =
            $this->methodsFor($user);

        if (
            ! (bool) data_get(
                $catalog,
                'product.is_active',
                false
            )
        ) {
            throw new AuthApiException(
                422,
                'PRODUCT_INACTIVE',
                'This TradeX product is not currently available for new purchases.'
            );
        }

        $method = collect(
            $catalog['methods']
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
        string $country,
        Product $product
    ): array {
        $currency =
            strtoupper(
                (string) config(
                    'tradex_payments.product.education_premium.paypal.currency',
                    'USD'
                )
            );

        $amount =
            $currency
                === strtoupper(
                    (string) $product
                        ->currency
                )
                ? (int) $product
                    ->amount_minor
                : 0;

        $enabled =
            (bool) config(
                'tradex_payments.paypal.enabled',
                true
            );

        $configured = $this
            ->paypalConfigured();

        $available =
            (bool) $product->is_active
            && $enabled
            && $configured
            && $amount > 0;

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
                $available,

            'unavailable_reason' =>
                $available
                    ? null
                    : (
                        ! $product->is_active
                            ? 'PRODUCT_INACTIVE'
                            : (
                                $amount <= 0
                                    ? 'PRODUCT_CURRENCY_MISMATCH'
                                    : 'CONFIGURATION_REQUIRED'
                            )
                    ),

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
        string $country,
        Product $product
    ): array {
        $currency =
            strtoupper(
                (string) config(
                    'tradex_payments.product.education_premium.stripe.currency',
                    'USD'
                )
            );

        $amount =
            $currency
                === strtoupper(
                    (string) $product
                        ->currency
                )
                ? (int) $product
                    ->amount_minor
                : 0;

        $runtime =
            $amount > 0
            && $this->runtimeGate
                ->available(
                    PaymentOrder::PROVIDER_STRIPE,
                    $country
                );

        $available =
            (bool) $product->is_active
            && $runtime;

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
                    : (
                        ! $product->is_active
                            ? 'PRODUCT_INACTIVE'
                            : (
                                $amount <= 0
                                    ? 'PRODUCT_CURRENCY_MISMATCH'
                                    : 'RUNTIME_APPROVAL_OR_CONFIGURATION_REQUIRED'
                            )
                    ),

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
        string $country,
        Product $product
    ): array {
        $currency =
            strtoupper(
                (string) config(
                    'tradex_payments.product.education_premium.nowpayments.currency',
                    'USD'
                )
            );

        $amount =
            $currency
                === strtoupper(
                    (string) $product
                        ->currency
                )
                ? (int) $product
                    ->amount_minor
                : 0;

        $runtime =
            $amount > 0
            && $this->runtimeGate
                ->available(
                    PaymentOrder::PROVIDER_NOWPAYMENTS,
                    $country
                );

        $available =
            (bool) $product->is_active
            && $runtime;

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
                    : (
                        ! $product->is_active
                            ? 'PRODUCT_INACTIVE'
                            : (
                                $amount <= 0
                                    ? 'PRODUCT_CURRENCY_MISMATCH'
                                    : 'RUNTIME_APPROVAL_OR_CONFIGURATION_REQUIRED'
                            )
                    ),

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
        string $country,
        Product $product
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

        $available =
            (bool) $product->is_active
            && $enabled
            && $configured;

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
                $available,

            'unavailable_reason' =>
                $available
                    ? null
                    : (
                        ! $product->is_active
                            ? 'PRODUCT_INACTIVE'
                            : 'CONFIGURATION_REQUIRED'
                    ),

            /*
             * ADMIN-25 deliberately keeps the explicitly configured
             * local-currency tender amount. No FX conversion is invented.
             */
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
        string $country,
        Product $product
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

        $available =
            (bool) $product->is_active
            && $enabled
            && $configured;

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
                $available,

            'unavailable_reason' =>
                $available
                    ? null
                    : (
                        ! $product->is_active
                            ? 'PRODUCT_INACTIVE'
                            : 'CONFIGURATION_REQUIRED'
                    ),

            /*
             * ADMIN-25 deliberately keeps the explicitly configured
             * USDT tender amount. No FX conversion is invented.
             */
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

    private function genericCryptoMethod(
        string $country,
        Product $product
    ): array {
        $enabled =
            (bool) config(
                'tradex_payments.crypto_manual.enabled',
                true
            );

        $configured =
            $this->hasGenericCryptoAccount(
                $country
            );

        $available =
            (bool) $product->is_active
            && $enabled
            && $configured;

        return [
            'code' =>
                PaymentOrder::PROVIDER_CRYPTO_MANUAL,

            'display_name' =>
                'Other Crypto',

            'mode' =>
                'MANUAL_REVIEW',

            'country_code' =>
                $country,

            'available' =>
                $available,

            'unavailable_reason' =>
                $available
                    ? null
                    : (
                        ! $product->is_active
                            ? 'PRODUCT_INACTIVE'
                            : (
                                ! $enabled
                                    ? 'PAYMENT_METHOD_DISABLED'
                                    : 'CONFIGURATION_REQUIRED'
                            )
                    ),

            /*
             * This is the authoritative TradeX commercial product value.
             * The exact coin/network tender is account-specific and is
             * returned by /manual-accounts. No crypto FX is invented here.
             */
            'amount' =>
                $this->money->present(
                    (int) $product
                        ->amount_minor,
                    (string) $product
                        ->currency
                ),

            'account_catalog_endpoint' =>
                'GET /api/v1/payments/manual-accounts',

            'create_endpoint' =>
                'POST /api/v1/payments/manual-orders',
        ];
    }

    private function hasGenericCryptoAccount(
        string $country
    ): bool {
        return PaymentReceivingAccount::query()
            ->where(
                'type',
                PaymentReceivingAccount::TYPE_CRYPTO
            )
            ->where(
                'country_code',
                strtoupper(
                    $country
                )
            )
            ->where(
                'is_active',
                true
            )
            ->whereNotNull(
                'tender_amount_decimal'
            )
            ->where(
                'tender_amount_decimal',
                '!=',
                ''
            )
            ->where(
                function (
                    $query
                ): void {
                    /*
                     * USDT/TRC20 retains its dedicated compatibility provider.
                     * Every other configured coin/network belongs to the
                     * generic manual-crypto flow.
                     */
                    $query
                        ->where(
                            'coin_symbol',
                            '!=',
                            'USDT'
                        )
                        ->orWhere(
                            'network',
                            '!=',
                            'TRC20'
                        );
                }
            )
            ->exists();
    }
    private function countryAllowed(
        string $country,
        array $countries
    ): bool {
        $country = strtoupper(
            trim($country)
        );

        $normalized = array_values(
            array_unique(
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
                )
            )
        );

        return in_array(
            '*',
            $normalized,
            true
        )
            || in_array(
                $country,
                $normalized,
                true
            );
    }
}
