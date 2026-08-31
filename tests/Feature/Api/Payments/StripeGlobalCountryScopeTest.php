<?php

namespace Tests\Feature\Api\Payments;

use App\Models\IntegrationProviderState;
use App\Models\PaymentOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class StripeGlobalCountryScopeTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_stripe_wildcard_scope_allows_customer_outside_sri_lanka(): void
    {
        config()->set('tradex_integrations.stripe.enabled', true);
        config()->set('tradex_integrations.stripe.countries', ['*']);
        config()->set('tradex_integrations.stripe.environment', 'SANDBOX');
        config()->set('tradex_integrations.stripe.publishable_key', 'pk_test_global_scope');
        config()->set('tradex_integrations.stripe.secret_key', 'sk_test_global_scope');
        config()->set('tradex_integrations.stripe.webhook_secret', 'whsec_global_scope');
        config()->set('tradex_integrations.stripe.base_url', 'https://api.stripe.com');
        config()->set(
            'tradex_integrations.stripe.success_url',
            'https://app.tradex.test/payments/success'
        );
        config()->set(
            'tradex_integrations.stripe.cancel_url',
            'https://app.tradex.test/payments/cancel'
        );

        IntegrationProviderState::query()->create([
            'provider_code' => PaymentOrder::PROVIDER_STRIPE,
            'is_enabled' => true,
            'environment' => 'SANDBOX',
            'countries' => ['*'],
            'eligibility_confirmed' => true,
            'version' => 1,
        ]);

        $user = $this->createApiUser([
            'country_code' => 'IN',
        ]);

        $token = $this->issueBoundToken($user);

        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' =>
                Http::response([
                    'id' => 'cs_test_global_scope_1',
                    'url' => 'https://checkout.stripe.com/c/pay/cs_test_global_scope_1',
                ], 200),
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/payments/methods')
            ->assertOk()
            ->assertJsonFragment([
                'code' => PaymentOrder::PROVIDER_STRIPE,
                'country_code' => 'IN',
                'available' => true,
            ]);

        $this->withToken($token)
            ->withHeader(
                'Idempotency-Key',
                'stripe-global-country-scope-0001'
            )
            ->postJson('/api/v1/payments/stripe/checkout-sessions')
            ->assertCreated()
            ->assertJsonPath(
                'data.provider',
                PaymentOrder::PROVIDER_STRIPE
            )
            ->assertJsonPath(
                'data.status',
                PaymentOrder::STATUS_AWAITING_USER_ACTION
            );
    }
}