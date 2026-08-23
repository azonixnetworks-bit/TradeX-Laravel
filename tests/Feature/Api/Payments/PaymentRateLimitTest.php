<?php

namespace Tests\Feature\Api\Payments;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class PaymentRateLimitTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_payment_create_actions_have_dedicated_user_rate_limit(): void
    {
        config()->set(
            'tradex_payments.rate_limits.create_per_minute',
            1
        );

        config()->set(
            'tradex_payments.usdt_trc20.enabled',
            true
        );

        config()->set(
            'tradex_payments.usdt_trc20.countries',
            ['IN']
        );

        config()->set(
            'tradex_payments.usdt_trc20.wallet_address',
            'TTradeXMerchantWallet123456789'
        );

        config()->set(
            'tradex_payments.product.education_premium.usdt_trc20.amount_minor',
            79000000
        );

        $user = $this->createApiUser([
            'country_code' =>
                'IN',
        ]);

        $token = $this->issueBoundToken(
            $user
        );

        $this
            ->withToken($token)
            ->withHeader(
                'Idempotency-Key',
                'api12-rate-idempotency-0001'
            )
            ->postJson(
                '/api/v1/payments/usdt-deposits',
                [
                    'tx_hash' =>
                        str_repeat(
                            'c',
                            64
                        ),

                    'amount' =>
                        '79.000000',
                ]
            )
            ->assertCreated();

        $this
            ->withToken($token)
            ->withHeader(
                'Idempotency-Key',
                'api12-rate-idempotency-0002'
            )
            ->postJson(
                '/api/v1/payments/usdt-deposits',
                [
                    'tx_hash' =>
                        str_repeat(
                            'd',
                            64
                        ),

                    'amount' =>
                        '79.000000',
                ]
            )
            ->assertStatus(
                429
            );
    }
}
