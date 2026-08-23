<?php

namespace Tests\Feature\Api\Affiliate;

use App\Models\AffiliateCommission;
use App\Models\AffiliateQualifiedSale;
use App\Models\AffiliateStage;
use App\Models\AuditLog;
use App\Services\Affiliates\AffiliateCommercialService;
use Database\Seeders\AffiliateStageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class AffiliateApiTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_premium_overview_uses_real_affiliate_commercial_ledger(): void
    {
        $this->seed(
            AffiliateStageSeeder::class
        );

        config()->set(
            'tradex_affiliate_api.referral_base_url',
            'https://tradex.example/register'
        );

        $affiliate = $this->createApiUser([
            'account_type' => 'PREMIUM',
            'referral_code' => 'TXAFF001',
        ]);

        $buyer = $this->createApiUser([
            'referred_by_user_id' =>
                $affiliate->getKey(),
        ]);

        $this->createApiUser([
            'referred_by_user_id' =>
                $affiliate->getKey(),
        ]);

        app(
            AffiliateCommercialService::class
        )->recordClearedPayPalSale(
            $affiliate,
            $buyer,
            'PAYPAL-AFF-001',
            10000
        );

        $token = $this->issueBoundToken(
            $affiliate
        );

        $response = $this
            ->withToken($token)
            ->getJson(
                '/api/v1/affiliate/overview'
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.program.model',
                'PROGRESSIVE_DIRECT_SALE'
            )
            ->assertJsonPath(
                'data.program.single_level_only',
                true
            )
            ->assertJsonPath(
                'data.program.multi_level_commission',
                false
            )
            ->assertJsonPath(
                'data.program.voluntary_refund_feature',
                false
            )
            ->assertJsonPath(
                'data.referral.code',
                'TXAFF001'
            )
            ->assertJsonPath(
                'data.referral.url',
                'https://tradex.example/register?ref=TXAFF001'
            )
            ->assertJsonPath(
                'data.referral.direct_referrals_count',
                2
            )
            ->assertJsonPath(
                'data.qualified_sales_count',
                1
            )
            ->assertJsonPath(
                'data.current_stage.code',
                AffiliateStage::STARTER
            )
            ->assertJsonPath(
                'data.commission.next_sale_rate_percent',
                '15.00'
            )
            ->assertJsonPath(
                'data.financials.qualified_net_eligible_revenue.amount_cents',
                10000
            )
            ->assertJsonPath(
                'data.financials.lifetime_commission_earned.amount_cents',
                1500
            )
            ->assertJsonPath(
                'data.financials.net_commission.amount_cents',
                1500
            )
            ->assertJsonPath(
                'data.milestones.next.sales_target',
                30
            );

        $this->assertNoInternalIdKey(
            $response->json('data')
        );
    }

    public function test_stage_endpoint_matches_the_seven_approved_commercial_stages(): void
    {
        $this->seed(
            AffiliateStageSeeder::class
        );

        $affiliate = $this->createApiUser([
            'account_type' => 'PREMIUM',
        ]);

        $token = $this->issueBoundToken(
            $affiliate
        );

        $response = $this
            ->withToken($token)
            ->getJson(
                '/api/v1/affiliate/stages'
            )
            ->assertOk();

        $items = $response->json(
            'data.items'
        );

        $this->assertCount(
            7,
            $items
        );

        $this->assertSame(
            [
                'STARTER',
                'GROWTH',
                'PRO',
                'ELITE',
                'DIAMOND',
                'AMBASSADOR',
                'BUSINESS_PARTNER',
            ],
            array_column(
                $items,
                'code'
            )
        );

        $this->assertSame(
            '15.00',
            $items[0][
                'standard_commission_percent'
            ]
        );

        $this->assertSame(
            '17.50',
            $items[1][
                'standard_commission_percent'
            ]
        );

        $this->assertSame(
            '20.00',
            $items[2][
                'standard_commission_percent'
            ]
        );

        $this->assertSame(
            1500,
            $items[6]['milestone'][
                'sales_target'
            ]
        );

        $this->assertSame(
            440000,
            $items[6]['milestone'][
                'cumulative_gift'
            ]['amount_cents']
        );
    }

    public function test_referrals_endpoint_is_direct_only_and_does_not_expose_contact_pii(): void
    {
        $this->seed(
            AffiliateStageSeeder::class
        );

        $affiliate = $this->createApiUser([
            'account_type' => 'PREMIUM',
        ]);

        $direct = $this->createApiUser([
            'name' => 'Direct Referral',
            'referred_by_user_id' =>
                $affiliate->getKey(),
        ]);

        $downline = $this->createApiUser([
            'name' => 'Must Not Appear',
            'referred_by_user_id' =>
                $direct->getKey(),
        ]);

        app(
            AffiliateCommercialService::class
        )->recordClearedPayPalSale(
            $affiliate,
            $direct,
            'PAYPAL-AFF-DIRECT',
            7900
        );

        $token = $this->issueBoundToken(
            $affiliate
        );

        $response = $this
            ->withToken($token)
            ->getJson(
                '/api/v1/affiliate/referrals'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.scope',
                'DIRECT_REFERRALS_ONLY'
            )
            ->assertJsonPath(
                'data.pagination.total',
                1
            )
            ->assertJsonPath(
                'data.items.0.uuid',
                $direct->uuid
            )
            ->assertJsonPath(
                'data.items.0.qualification.active_qualified_sales_count',
                1
            );

        $encoded = json_encode(
            $response->json('data'),
            JSON_THROW_ON_ERROR
        );

        $this->assertStringNotContainsString(
            $downline->uuid,
            $encoded
        );

        $this->assertStringNotContainsString(
            (string) $direct->email,
            $encoded
        );

        $this->assertStringNotContainsString(
            (string) $direct->whatsapp_number,
            $encoded
        );
    }

    public function test_sales_and_commissions_hide_paypal_reference_and_sensitive_reversal_reason(): void
    {
        $this->seed(
            AffiliateStageSeeder::class
        );

        $affiliate = $this->createApiUser([
            'account_type' => 'PREMIUM',
        ]);

        $buyer = $this->createApiUser([
            'referred_by_user_id' =>
                $affiliate->getKey(),
        ]);

        $commission = app(
            AffiliateCommercialService::class
        )->recordClearedPayPalSale(
            $affiliate,
            $buyer,
            'PAYPAL-SECRET-REFERENCE-123',
            10000
        );

        app(
            AffiliateCommercialService::class
        )->recordForcedPaymentReversal(
            $commission->qualifiedSale,
            AffiliateQualifiedSale::REVERSAL_PAYPAL_CHARGEBACK,
            'Private processor investigation details must not leave the API.'
        );

        $token = $this->issueBoundToken(
            $affiliate
        );

        $sales = $this
            ->withToken($token)
            ->getJson(
                '/api/v1/affiliate/sales'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.items.0.status',
                AffiliateQualifiedSale::STATUS_PAYMENT_REVERSED
            )
            ->assertJsonPath(
                'data.items.0.processor_exception.recorded',
                true
            )
            ->assertJsonPath(
                'data.items.0.processor_exception.code',
                AffiliateQualifiedSale::REVERSAL_PAYPAL_CHARGEBACK
            )
            ->assertJsonPath(
                'data.items.0.commission.gross.amount_cents',
                1500
            )
            ->assertJsonPath(
                'data.items.0.commission.processor_adjustments.amount_cents',
                -1500
            )
            ->assertJsonPath(
                'data.items.0.commission.net.amount_cents',
                0
            );

        $salesJson = json_encode(
            $sales->json('data'),
            JSON_THROW_ON_ERROR
        );

        $this->assertStringNotContainsString(
            'PAYPAL-SECRET-REFERENCE-123',
            $salesJson
        );

        $this->assertStringNotContainsString(
            'Private processor investigation details',
            $salesJson
        );

        $commissions = $this
            ->withToken($token)
            ->getJson(
                '/api/v1/affiliate/commissions'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.items.0.rate_source',
                AffiliateCommission::RATE_SOURCE_STANDARD
            )
            ->assertJsonPath(
                'data.items.0.net_commission.amount_cents',
                0
            );

        $this->assertNoInternalIdKey(
            $commissions->json('data')
        );
    }

    public function test_free_user_is_blocked_from_full_affiliate_api(): void
    {
        $free = $this->createApiUser([
            'account_type' => 'FREE',
        ]);

        $token = $this->issueBoundToken(
            $free
        );

        foreach ([
            '/api/v1/affiliate/overview',
            '/api/v1/affiliate/stages',
            '/api/v1/affiliate/referrals',
            '/api/v1/affiliate/sales',
            '/api/v1/affiliate/commissions',
            '/api/v1/affiliate/milestones',
        ] as $uri) {
            $this
                ->withToken($token)
                ->getJson($uri)
                ->assertForbidden()
                ->assertJsonPath(
                    'error.code',
                    'AFFILIATE_PREMIUM_REQUIRED'
                );
        }

        $this
            ->withToken($token)
            ->postJson(
                '/api/v1/affiliate/share-action',
                [
                    'channel' =>
                        'WHATSAPP',
                ]
            )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'AFFILIATE_PREMIUM_REQUIRED'
            );
    }

    public function test_share_action_returns_external_user_share_only_and_is_audited(): void
    {
        config()->set(
            'tradex_affiliate_api.referral_base_url',
            'https://tradex.example/register'
        );

        $affiliate = $this->createApiUser([
            'account_type' => 'PREMIUM',
            'referral_code' => 'TXSHARE09',
        ]);

        $token = $this->issueBoundToken(
            $affiliate
        );

        $response = $this
            ->withToken($token)
            ->postJson(
                '/api/v1/affiliate/share-action',
                [
                    'channel' =>
                        'whatsapp',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.channel',
                'WHATSAPP'
            )
            ->assertJsonPath(
                'data.referral_code',
                'TXSHARE09'
            )
            ->assertJsonPath(
                'data.referral_url',
                'https://tradex.example/register?ref=TXSHARE09'
            )
            ->assertJsonPath(
                'data.delivery.mode',
                'USER_EXTERNAL_SHARE'
            )
            ->assertJsonPath(
                'data.delivery.server_provider_send_triggered',
                false
            )
            ->assertJsonPath(
                'data.delivery.delivery_verified_by_tradex',
                false
            );

        $this->assertStringStartsWith(
            'https://wa.me/?text=',
            (string) $response->json(
                'data.external_share_url'
            )
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_type' =>
                    'USER',

                'actor_id' =>
                    $affiliate->getKey(),

                'action' =>
                    'AFFILIATE_USER_SHARE_ACTION_PREPARED',

                'module' =>
                    'AFFILIATES',
            ]
        );
    }

    private function assertNoInternalIdKey(
        mixed $value
    ): void {
        if (! is_array($value)) {
            return;
        }

        foreach ($value as $key => $nested) {
            if (is_string($key)) {
                $this->assertNotSame(
                    'id',
                    $key,
                    'Affiliate API must not expose internal numeric id fields.'
                );
            }

            $this->assertNoInternalIdKey(
                $nested
            );
        }
    }
}
