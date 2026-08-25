<?php

namespace Tests\Feature\Api\Payouts;

use App\Models\PayoutAllocation;
use App\Models\PayoutRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesPayoutLedger;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class PayoutCreationAndIdempotencyTest extends TestCase
{
    use CreatesPayoutLedger;
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_payout_is_created_allocated_and_destination_encrypted(): void
    {
        $user = $this->createApiUser([
            'account_type' => 'PREMIUM',
        ]);

        $this->configurePayoutProfile($user);
        $this->seedPayoutCommission($user, 2500);

        $token = $this->issueBoundToken($user);

        $response = $this
            ->withToken($token)
            ->withHeader(
                'Idempotency-Key',
                'payout-create-001'
            )
            ->postJson(
                '/api/v1/payouts',
                [
                    'method' => 'PAYPAL',
                    'amount' => '15.00',
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.payout.status',
                PayoutRequest::STATUS_PENDING_REVIEW
            )
            ->assertJsonPath(
                'data.payout.amount.amount_cents',
                1500
            );

        $payout = PayoutRequest::query()
            ->firstOrFail();

        $this->assertSame(
            1500,
            (int) PayoutAllocation::query()
                ->where(
                    'payout_request_id',
                    $payout->getKey()
                )
                ->sum('amount_cents')
        );

        $rawDestination = (string) DB::table(
            'payout_requests'
        )
            ->where(
                'id',
                $payout->getKey()
            )
            ->value(
                'destination_snapshot'
            );

        $this->assertStringNotContainsString(
            'payout@example.test',
            $rawDestination
        );

        $this->assertStringNotContainsString(
            'payout@example.test',
            json_encode(
                $response->json(),
                JSON_THROW_ON_ERROR
            )
        );
    }

    public function test_idempotency_replay_does_not_double_allocate(): void
    {
        $user = $this->createApiUser([
            'account_type' => 'PREMIUM',
        ]);

        $this->configurePayoutProfile($user);
        $this->seedPayoutCommission($user, 4000);

        $token = $this->issueBoundToken($user);

        $this
            ->withToken($token)
            ->withHeader(
                'Idempotency-Key',
                'payout-idem-001'
            )
            ->postJson(
                '/api/v1/payouts',
                [
                    'method' => 'PAYPAL',
                    'amount' => '15.00',
                ]
            )
            ->assertCreated();

        $this
            ->withToken($token)
            ->withHeader(
                'Idempotency-Key',
                'payout-idem-001'
            )
            ->postJson(
                '/api/v1/payouts',
                [
                    'method' => 'PAYPAL',
                    'amount' => '15.00',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.idempotency_replayed',
                true
            );

        $this->assertDatabaseCount(
            'payout_requests',
            1
        );

        $this->assertSame(
            1500,
            (int) PayoutAllocation::query()
                ->sum('amount_cents')
        );
    }

    public function test_same_key_with_different_request_conflicts(): void
    {
        $user = $this->createApiUser([
            'account_type' => 'PREMIUM',
        ]);

        $this->configurePayoutProfile($user);
        $this->seedPayoutCommission($user, 5000);

        $token = $this->issueBoundToken($user);

        $this
            ->withToken($token)
            ->withHeader(
                'Idempotency-Key',
                'payout-idem-002'
            )
            ->postJson(
                '/api/v1/payouts',
                [
                    'method' => 'PAYPAL',
                    'amount' => '15.00',
                ]
            )
            ->assertCreated();

        $this
            ->withToken($token)
            ->withHeader(
                'Idempotency-Key',
                'payout-idem-002'
            )
            ->postJson(
                '/api/v1/payouts',
                [
                    'method' => 'PAYPAL',
                    'amount' => '20.00',
                ]
            )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'PAYOUT_IDEMPOTENCY_CONFLICT'
            );
    }

    public function test_minimum_balance_and_idempotency_validation(): void
    {
        $user = $this->createApiUser([
            'account_type' => 'PREMIUM',
        ]);

        $this->configurePayoutProfile($user);
        $this->seedPayoutCommission($user, 2000);

        $token = $this->issueBoundToken($user);

        $this
            ->withToken($token)
            ->withHeader(
                'Idempotency-Key',
                'payout-min-001'
            )
            ->postJson(
                '/api/v1/payouts',
                [
                    'method' => 'PAYPAL',
                    'amount' => '14.99',
                ]
            )
            ->assertStatus(422)
            ->assertJsonPath(
                'error.code',
                'PAYOUT_BELOW_MINIMUM'
            );

        $this
            ->withToken($token)
            ->withHeader(
                'Idempotency-Key',
                'payout-over-001'
            )
            ->postJson(
                '/api/v1/payouts',
                [
                    'method' => 'PAYPAL',
                    'amount' => '25.00',
                ]
            )
            ->assertStatus(422)
            ->assertJsonPath(
                'error.code',
                'PAYOUT_INSUFFICIENT_BALANCE'
            );

        /*
         * Laravel's HTTP test helper keeps default headers between requests
         * inside the same test method. Explicitly clear Idempotency-Key so
         * this request genuinely tests the missing-header validation path.
         */
        $this
            ->withoutHeader(
                'Idempotency-Key'
            )
            ->withToken($token)
            ->postJson(
                '/api/v1/payouts',
                [
                    'method' => 'PAYPAL',
                    'amount' => '15.00',
                ]
            )
            ->assertStatus(422)
            ->assertJsonPath(
                'error.code',
                'PAYOUT_IDEMPOTENCY_KEY_INVALID'
            );
    }
}
