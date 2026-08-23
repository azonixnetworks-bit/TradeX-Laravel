<?php

namespace Tests\Feature\Api\Payments;

use App\Models\PaymentOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class PaymentHistoryAndStatusTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_history_and_activation_status_are_scoped_to_authenticated_user(): void
    {
        $user =
            $this->createApiUser();

        $other =
            $this->createApiUser();

        $own =
            $this->createOrder(
                $user->getKey(),
                PaymentOrder::PROVIDER_LOCAL_BANK,
                PaymentOrder::STATUS_PENDING_REVIEW
            );

        $this->createOrder(
            $other->getKey(),
            PaymentOrder::PROVIDER_PAYPAL,
            PaymentOrder::STATUS_CLEARED
        );

        $token =
            $this->issueBoundToken(
                $user
            );

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/payments/history'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.pagination.total',
                1
            )
            ->assertJsonPath(
                'data.items.0.uuid',
                $own->uuid
            );

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/payments/activation-status'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.premium_active',
                false
            )
            ->assertJsonPath(
                'data.latest_payment_order.uuid',
                $own->uuid
            )
            ->assertJsonPath(
                'data.latest_payment_order.status',
                PaymentOrder::STATUS_PENDING_REVIEW
            );
    }

    private function createOrder(
        int $userId,
        string $provider,
        string $status
    ): PaymentOrder {
        return PaymentOrder::query()
            ->create([
                'user_id' =>
                    $userId,

                'product_code' =>
                    PaymentOrder::PRODUCT_EDUCATION_PREMIUM,

                'provider' =>
                    $provider,

                'country_code' =>
                    'LK',

                'currency' =>
                    $provider
                    === PaymentOrder::PROVIDER_PAYPAL
                        ? 'USD'
                        : 'LKR',

                'amount_minor' =>
                    $provider
                    === PaymentOrder::PROVIDER_PAYPAL
                        ? 7900
                        : 2500000,

                'status' =>
                    $status,

                'client_idempotency_key_hash' =>
                    hash(
                        'sha256',
                        (string) Str::uuid()
                    ),

                'cleared_at' =>
                    $status
                    === PaymentOrder::STATUS_CLEARED
                        ? now()
                        : null,
            ]);
    }
}
