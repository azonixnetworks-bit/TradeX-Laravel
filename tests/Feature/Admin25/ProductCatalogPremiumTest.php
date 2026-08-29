<?php

namespace Tests\Feature\Admin25;

use App\Models\Product;
use App\Models\UserEntitlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class ProductCatalogPremiumTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_customer_product_api_exposes_lifetime_education_product_and_default_usd_price(): void
    {
        $user = $this->createApiUser([
            'account_type' => 'FREE',
            'country_code' => 'LK',
        ]);

        $token =
            $this->issueBoundToken(
                $user
            );

        $this->withToken($token)
            ->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath(
                'data.items.0.code',
                Product::CODE_EDUCATION_PREMIUM
            )
            ->assertJsonPath(
                'data.items.0.billing_type',
                Product::BILLING_ONE_TIME_LIFETIME
            )
            ->assertJsonPath(
                'data.items.0.price.currency',
                'USD'
            )
            ->assertJsonPath(
                'data.items.0.price.amount_minor',
                7900
            )
            ->assertJsonPath(
                'data.items.0.purchase_allowed',
                true
            );
    }

    public function test_active_entitlement_is_authoritative_and_blocks_duplicate_purchase_even_if_product_is_inactive(): void
    {
        $user = $this->createApiUser([
            'account_type' => 'FREE',
        ]);

        UserEntitlement::query()
            ->create([
                'user_id' =>
                    $user->getKey(),
                'product_code' =>
                    Product::CODE_EDUCATION_PREMIUM,
                'status' =>
                    UserEntitlement::STATUS_ACTIVE,
                'activated_at' =>
                    now(),
                'payment_id' =>
                    null,
            ]);

        Product::query()
            ->where(
                'code',
                Product::CODE_EDUCATION_PREMIUM
            )
            ->update([
                'is_active' => false,
            ]);

        $user->refresh();

        $this->assertTrue(
            $user->canAccessPremium()
        );

        $token =
            $this->issueBoundToken(
                $user
            );

        $this->withToken($token)
            ->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath(
                'data.items.0.is_active',
                false
            )
            ->assertJsonPath(
                'data.items.0.entitlement.active',
                true
            )
            ->assertJsonPath(
                'data.items.0.purchase_allowed',
                false
            );
    }

    public function test_future_online_payment_method_uses_current_product_catalog_price(): void
    {
        config()->set(
            'tradex_payments.paypal.enabled',
            true
        );
        config()->set(
            'tradex_payments.paypal.client_id',
            'test-client'
        );
        config()->set(
            'tradex_payments.paypal.client_secret',
            'test-secret'
        );
        config()->set(
            'tradex_payments.paypal.countries',
            ['LK']
        );

        $product = Product::query()
            ->where(
                'code',
                Product::CODE_EDUCATION_PREMIUM
            )
            ->firstOrFail();

        $product->forceFill([
            'amount_minor' => 8300,
        ])->save();

        $user = $this->createApiUser([
            'account_type' => 'FREE',
            'country_code' => 'LK',
        ]);

        $token =
            $this->issueBoundToken(
                $user
            );

        $this->withToken($token)
            ->getJson(
                '/api/v1/payments/methods'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.product.base_price.amount_minor',
                8300
            )
            ->assertJsonPath(
                'data.methods.0.code',
                'PAYPAL'
            )
            ->assertJsonPath(
                'data.methods.0.amount.amount_minor',
                8300
            );
    }
}
