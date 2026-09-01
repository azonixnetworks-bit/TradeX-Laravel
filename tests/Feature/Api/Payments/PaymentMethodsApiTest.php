<?php

namespace Tests\Feature\Api\Payments;

use App\Models\IntegrationProviderState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class PaymentMethodsApiTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_sri_lanka_user_receives_configured_paypal_and_local_bank_methods(): void
    {
        $this->configurePayPal();
        $this->configureLocalBank();

        config()->set(
            'tradex_payments.paypal.countries',
            ['LK', 'IN']
        );

        config()->set(
            'tradex_payments.local_bank.countries',
            ['LK']
        );

        config()->set(
            'tradex_payments.usdt_trc20.countries',
            ['IN']
        );

        $user = $this->createApiUser([
            'country_code' => 'LK',
        ]);

        $token = $this->issueBoundToken(
            $user
        );

        $response = $this
            ->withToken($token)
            ->getJson(
                '/api/v1/payments/methods'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.country_code',
                'LK'
            )
            ->assertJsonPath(
                'data.purchase_allowed',
                true
            );

        $codes = collect(
            $response->json(
                'data.methods'
            )
        )->pluck('code')->all();

        $this->assertSame(
            [
                'PAYPAL',
                'LOCAL_BANK',
            ],
            $codes
        );

        $payload = json_encode(
            $response->json('data'),
            JSON_THROW_ON_ERROR
        );

        $this->assertStringNotContainsString(
            'super-secret-paypal-client-secret',
            $payload
        );
    }

    public function test_india_user_receives_configured_paypal_and_usdt_methods(): void
    {
        $this->configurePayPal();

        config()->set(
            'tradex_payments.usdt_trc20.countries',
            ['IN']
        );

        config()->set(
            'tradex_payments.usdt_trc20.wallet_address',
            'TTradeXMerchantWallet123456789'
        );

        $user = $this->createApiUser([
            'country_code' => 'IN',
        ]);

        $token = $this->issueBoundToken(
            $user
        );

        $response = $this
            ->withToken($token)
            ->getJson(
                '/api/v1/payments/methods'
            )
            ->assertOk();

        $codes = collect(
            $response->json(
                'data.methods'
            )
        )->pluck('code')->all();

        $this->assertSame(
            [
                'PAYPAL',
                'USDT_TRC20',
            ],
            $codes
        );
    }

    private function configurePayPal(): void
    {
        config()->set(
            'tradex_payments.paypal.driver',
            'http'
        );

        config()->set(
            'tradex_payments.paypal.enabled',
            true
        );

        config()->set(
            'tradex_payments.paypal.countries',
            ['LK', 'IN']
        );

        config()->set(
            'tradex_payments.paypal.environment',
            'sandbox'
        );

        config()->set(
            'tradex_payments.paypal.client_id',
            'paypal-client'
        );

        config()->set(
            'tradex_payments.paypal.client_secret',
            'super-secret-paypal-client-secret'
        );

        config()->set(
            'tradex_payments.paypal.webhook_id',
            'paypal-webhook-id'
        );

        config()->set(
            'tradex_payments.paypal.sandbox_base_url',
            'https://api-m.sandbox.paypal.com'
        );

        config()->set(
            'tradex_payments.paypal.live_base_url',
            'https://api-m.paypal.com'
        );

        config()->set(
            'tradex_payments.paypal.return_url',
            'https://app.tradex.test/payments/paypal/return'
        );

        config()->set(
            'tradex_payments.paypal.cancel_url',
            'https://app.tradex.test/payments/paypal/cancel'
        );

        IntegrationProviderState::query()
            ->create([
                'provider_code' =>
                    'PAYPAL',

                'is_enabled' =>
                    true,

                'environment' =>
                    'SANDBOX',

                'countries' =>
                    ['LK', 'IN'],

                'eligibility_confirmed' =>
                    true,

                'version' =>
                    1,
            ]);
    }

    private function configureLocalBank(): void
    {
        config()->set(
            'tradex_payments.product.education_premium.local_bank.amount_minor',
            2500000
        );

        config()->set(
            'tradex_payments.local_bank.enabled',
            true
        );

        config()->set(
            'tradex_payments.local_bank.bank_name',
            'TradeX Test Bank'
        );

        config()->set(
            'tradex_payments.local_bank.branch',
            'Colombo'
        );

        config()->set(
            'tradex_payments.local_bank.account_holder',
            'TradeX'
        );

        config()->set(
            'tradex_payments.local_bank.account_number',
            '00123456789'
        );
    }
}
