<?php

namespace Tests\Feature\Affiliates;

use App\Models\AdminUser;
use App\Models\AffiliateCommission;
use App\Models\AffiliateCommissionAdjustment;
use App\Models\AffiliateMilestoneReward;
use App\Models\AffiliateProfile;
use App\Models\AffiliateQualifiedSale;
use App\Models\AffiliateStage;
use App\Services\Affiliates\AffiliateCommercialService;
use Database\Seeders\AffiliateStageSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class AffiliateCommercialFoundationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTradeXMobileSession;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AffiliateStageSeeder::class);
    }

    public function test_stage_seed_matches_approved_commercial_reference(): void
    {
        $this->assertDatabaseCount('affiliate_stages', 7);

        $expected = [
            [AffiliateStage::STARTER, 1, 30, 1500, 30, 7500, 7500],
            [AffiliateStage::GROWTH, 31, 80, 1750, 80, 12500, 20000],
            [AffiliateStage::PRO, 81, 150, 2000, 150, 20000, 40000],
            [AffiliateStage::ELITE, 151, 300, 2000, 300, 40000, 80000],
            [AffiliateStage::DIAMOND, 301, 500, 2000, 500, 60000, 140000],
            [AffiliateStage::AMBASSADOR, 501, 750, 2000, 750, 80000, 220000],
            [AffiliateStage::BUSINESS_PARTNER, 751, null, 2000, 1500, 220000, 440000],
        ];

        foreach ($expected as [
            $code,
            $min,
            $max,
            $rateBps,
            $milestone,
            $giftCents,
            $cumulativeGiftCents,
        ]) {
            $stage = AffiliateStage::query()
                ->where('code', $code)
                ->firstOrFail();

            $this->assertSame($min, $stage->min_qualified_sales);
            $this->assertSame($max, $stage->max_qualified_sales);
            $this->assertSame($rateBps, $stage->standard_commission_bps);
            $this->assertSame($milestone, $stage->milestone_sales);
            $this->assertSame($giftCents, $stage->milestone_gift_cents);
            $this->assertSame($cumulativeGiftCents, $stage->cumulative_gift_cents);
        }

        $this->assertSame(
            AffiliateStage::BUSINESS_PARTNER,
            AffiliateStage::forQualifiedSalesCount(1501)?->code
        );
    }

    public function test_progressive_commission_bands_and_one_time_milestone_gifts(): void
    {
        $affiliate = $this->createApiUser();
        $service = app(AffiliateCommercialService::class);

        $commissionAt30 = null;
        $commissionAt31 = null;
        $commissionAt80 = null;
        $commissionAt81 = null;

        for ($saleNumber = 1; $saleNumber <= 81; $saleNumber++) {
            $referredUser = $this->createApiUser([
                'referred_by_user_id' => $affiliate->getKey(),
            ]);

            $commission = $service->recordClearedPayPalSale(
                $affiliate,
                $referredUser,
                'PAYPAL-'.str_pad((string) $saleNumber, 4, '0', STR_PAD_LEFT),
                10000
            );

            if ($saleNumber === 30) {
                $commissionAt30 = $commission;
            }

            if ($saleNumber === 31) {
                $commissionAt31 = $commission;
            }

            if ($saleNumber === 80) {
                $commissionAt80 = $commission;
            }

            if ($saleNumber === 81) {
                $commissionAt81 = $commission;
            }
        }

        $this->assertSame(1500, $commissionAt30?->rate_bps);
        $this->assertSame(1500, $commissionAt30?->commission_amount_cents);

        $this->assertSame(1750, $commissionAt31?->rate_bps);
        $this->assertSame(1750, $commissionAt31?->commission_amount_cents);

        $this->assertSame(1750, $commissionAt80?->rate_bps);
        $this->assertSame(2000, $commissionAt81?->rate_bps);
        $this->assertSame(2000, $commissionAt81?->commission_amount_cents);

        $profile = AffiliateProfile::query()
            ->where('user_id', $affiliate->getKey())
            ->firstOrFail();

        $this->assertSame(81, $profile->qualified_sales_count);
        $this->assertSame(81, $profile->lifetime_cleared_sales_count);
        $this->assertSame(AffiliateStage::PRO, $profile->currentStage?->code);
        $this->assertSame(20000, $profile->milestone_gifts_earned_cents);

        $this->assertDatabaseHas('affiliate_milestone_rewards', [
            'affiliate_user_id' => $affiliate->getKey(),
            'milestone_sales' => 30,
            'gift_amount_cents' => 7500,
        ]);

        $this->assertDatabaseHas('affiliate_milestone_rewards', [
            'affiliate_user_id' => $affiliate->getKey(),
            'milestone_sales' => 80,
            'gift_amount_cents' => 12500,
        ]);

        $this->assertDatabaseCount('affiliate_milestone_rewards', 2);
    }

    public function test_special_24_percent_exception_receives_no_milestone_gift(): void
    {
        $affiliate = $this->createApiUser();

        $service = app(AffiliateCommercialService::class);
        $admin = $this->createAffiliateApprovalAdmin();

        $service->approveSpecialCommissionRate(
            $affiliate,
            2400,
            $admin,
            'Approved strategic partner exception.'
        );
        $lastCommission = null;

        for ($saleNumber = 1; $saleNumber <= 30; $saleNumber++) {
            $referredUser = $this->createApiUser([
                'referred_by_user_id' => $affiliate->getKey(),
            ]);

            $lastCommission = $service->recordClearedPayPalSale(
                $affiliate,
                $referredUser,
                'SPECIAL-'.str_pad((string) $saleNumber, 4, '0', STR_PAD_LEFT),
                10000
            );
        }

        $this->assertSame(2400, $lastCommission?->rate_bps);
        $this->assertSame(
            AffiliateCommission::RATE_SOURCE_SPECIAL,
            $lastCommission?->rate_source
        );
        $this->assertSame(2400, $lastCommission?->commission_amount_cents);

        $this->assertSame(
            0,
            AffiliateMilestoneReward::query()
                ->where('affiliate_user_id', $affiliate->getKey())
                ->count()
        );
    }

    public function test_special_rate_above_24_percent_is_rejected(): void
    {
        $affiliate = $this->createApiUser();
        $admin = $this->createAffiliateApprovalAdmin();

        $this->expectException(DomainException::class);

        app(AffiliateCommercialService::class)
            ->approveSpecialCommissionRate(
                $affiliate,
                2500,
                $admin,
                'Invalid test rate.'
            );
    }

    public function test_only_directly_attributed_referral_sale_can_be_qualified(): void
    {
        $affiliate = $this->createApiUser();
        $otherAffiliate = $this->createApiUser();
        $referredUser = $this->createApiUser([
            'referred_by_user_id' => $otherAffiliate->getKey(),
        ]);

        $this->expectException(DomainException::class);

        app(AffiliateCommercialService::class)
            ->recordClearedPayPalSale(
                $affiliate,
                $referredUser,
                'WRONG-REFERRER',
                10000
            );
    }

    public function test_paypal_source_reference_is_idempotent_and_cannot_double_count(): void
    {
        $affiliate = $this->createApiUser();
        $referredUser = $this->createApiUser([
            'referred_by_user_id' => $affiliate->getKey(),
        ]);
        $service = app(AffiliateCommercialService::class);

        $first = $service->recordClearedPayPalSale(
            $affiliate,
            $referredUser,
            'IDEMPOTENT-ORDER-001',
            10000
        );

        $second = $service->recordClearedPayPalSale(
            $affiliate,
            $referredUser,
            'IDEMPOTENT-ORDER-001',
            10000
        );

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertDatabaseCount('affiliate_qualified_sales', 1);
        $this->assertDatabaseCount('affiliate_commissions', 1);

        $profile = AffiliateProfile::query()
            ->where('user_id', $affiliate->getKey())
            ->firstOrFail();

        $this->assertSame(1, $profile->qualified_sales_count);
        $this->assertSame(1, $profile->lifetime_cleared_sales_count);
    }

    public function test_forced_payment_reversal_uses_adjustment_ledger_and_does_not_create_a_refund_feature(): void
    {
        $affiliate = $this->createApiUser();
        $referredUser = $this->createApiUser([
            'referred_by_user_id' => $affiliate->getKey(),
        ]);
        $service = app(AffiliateCommercialService::class);

        $commission = $service->recordClearedPayPalSale(
            $affiliate,
            $referredUser,
            'FORCED-REVERSAL-ORDER-001',
            10000
        );

        $sale = AffiliateQualifiedSale::query()
            ->findOrFail($commission->affiliate_qualified_sale_id);

        $adjustment = $service->recordForcedPaymentReversal(
            $sale,
            AffiliateQualifiedSale::REVERSAL_PAYPAL_CHARGEBACK,
            'PayPal chargeback confirmed by the payment processor.'
        );

        $this->assertSame(
            AffiliateCommissionAdjustment::TYPE_PAYMENT_REVERSAL,
            $adjustment->type
        );
        $this->assertSame(-1500, $adjustment->amount_cents);

        $originalCommission = $commission->fresh();

        $this->assertSame(1500, $originalCommission->rate_bps);
        $this->assertSame(1500, $originalCommission->commission_amount_cents);
        $this->assertSame(0, $originalCommission->netAmountCents());

        $profile = AffiliateProfile::query()
            ->where('user_id', $affiliate->getKey())
            ->firstOrFail();

        $this->assertSame(0, $profile->qualified_sales_count);
        $this->assertNull($profile->current_stage_id);
        $this->assertSame(0, $profile->qualified_net_eligible_revenue_cents);
        $this->assertSame(1500, $profile->lifetime_commission_earned_cents);
        $this->assertSame(-1500, $profile->commission_adjustment_cents);
        $this->assertSame(0, $profile->netCommissionCents());

        $secondAdjustment = $service->recordForcedPaymentReversal(
            $sale->fresh(),
            AffiliateQualifiedSale::REVERSAL_PAYPAL_CHARGEBACK,
            'PayPal chargeback confirmed by the payment processor.'
        );

        $this->assertSame($adjustment->getKey(), $secondAdjustment->getKey());
        $this->assertDatabaseCount('affiliate_commission_adjustments', 1);
    }

    public function test_voluntary_refund_is_not_a_supported_affiliate_operation(): void
    {
        $affiliate = $this->createApiUser();
        $referredUser = $this->createApiUser([
            'referred_by_user_id' => $affiliate->getKey(),
        ]);
        $service = app(AffiliateCommercialService::class);

        $commission = $service->recordClearedPayPalSale(
            $affiliate,
            $referredUser,
            'NO-REFUND-ORDER-001',
            10000
        );

        $sale = AffiliateQualifiedSale::query()
            ->findOrFail($commission->affiliate_qualified_sale_id);

        $this->expectException(DomainException::class);

        $service->recordForcedPaymentReversal(
            $sale,
            'VOLUNTARY_REFUND',
            'Voluntary refund requested.'
        );
    }

    private function createAffiliateApprovalAdmin(): AdminUser
    {
        return AdminUser::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Affiliate Approval Admin',
            'email' => 'affiliate-approval-'.Str::lower(Str::random(8)).'@example.test',
            'email_verified_at' => now(),
            'password' => 'TradeX-Affiliate-Admin-Password-123!',
            'is_active' => true,
        ]);
    }
}
