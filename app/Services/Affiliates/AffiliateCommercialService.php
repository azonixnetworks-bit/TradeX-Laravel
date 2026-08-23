<?php

namespace App\Services\Affiliates;

use App\Models\AffiliateCommission;
use App\Models\AffiliateCommissionAdjustment;
use App\Models\AffiliateMilestoneReward;
use App\Models\AffiliateProfile;
use App\Models\AffiliateQualifiedSale;
use App\Models\AffiliateStage;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

class AffiliateCommercialService
{
    public const STANDARD_MAX_COMMISSION_BPS = 2000;
    public const SPECIAL_MAX_COMMISSION_BPS = 2400;
    public const CURRENCY_USD = 'USD';

    public function approveSpecialCommissionRate(
        User $affiliate,
        int $rateBps,
        AdminUser $approvedBy,
        string $reason
    ): AffiliateProfile {
        $reason = trim($reason);

        if (
            $rateBps <= self::STANDARD_MAX_COMMISSION_BPS
            || $rateBps > self::SPECIAL_MAX_COMMISSION_BPS
        ) {
            throw new DomainException(
                'Special affiliate commission must be above 20% and no more than 24%.'
            );
        }

        if ($reason === '' || mb_strlen($reason) > 500) {
            throw new DomainException(
                'A management approval reason is required for a special affiliate commission.'
            );
        }

        return DB::transaction(function () use (
            $affiliate,
            $rateBps,
            $approvedBy,
            $reason
        ): AffiliateProfile {
            $profile = AffiliateProfile::query()->firstOrCreate(
                ['user_id' => $affiliate->getKey()],
                [
                    'qualified_sales_count' => 0,
                    'lifetime_cleared_sales_count' => 0,
                    'qualified_net_eligible_revenue_cents' => 0,
                    'lifetime_commission_earned_cents' => 0,
                    'commission_adjustment_cents' => 0,
                    'milestone_gifts_earned_cents' => 0,
                ]
            );

            $profile = AffiliateProfile::query()
                ->whereKey($profile->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $before = [
                'special_commission_bps' => $profile->special_commission_bps,
                'special_commission_approved_at' =>
                    $profile->special_commission_approved_at?->toISOString(),
                'special_commission_approved_by_admin_id' =>
                    $profile->special_commission_approved_by_admin_id,
                'special_commission_reason' => $profile->special_commission_reason,
            ];

            $profile->forceFill([
                'special_commission_bps' => $rateBps,
                'special_commission_approved_at' => now(),
                'special_commission_approved_by_admin_id' => $approvedBy->getKey(),
                'special_commission_reason' => $reason,
            ])->save();

            AuditLog::record(
                actorType: 'ADMIN',
                actorId: $approvedBy->getKey(),
                action: 'AFFILIATE_SPECIAL_COMMISSION_APPROVED',
                module: 'AFFILIATES',
                targetType: 'AFFILIATE_PROFILE',
                targetId: $profile->getKey(),
                oldValues: $before,
                newValues: [
                    'special_commission_bps' => $rateBps,
                    'special_commission_approved_at' =>
                        $profile->special_commission_approved_at?->toISOString(),
                    'special_commission_approved_by_admin_id' => $approvedBy->getKey(),
                    'special_commission_reason' => $reason,
                ]
            );

            return $profile->fresh();
        }, 3);
    }

    public function recordClearedPayPalSale(
        User $affiliate,
        User $referredUser,
        string $sourceReference,
        int $netEligibleRevenueCents,
        ?CarbonInterface $qualifiedAt = null
    ): AffiliateCommission {
        $sourceReference = trim($sourceReference);

        if ($sourceReference === '' || mb_strlen($sourceReference) > 191) {
            throw new DomainException(
                'A valid PayPal source reference is required.'
            );
        }

        if ($netEligibleRevenueCents <= 0) {
            throw new DomainException(
                'PayPal Net Eligible Revenue must be greater than zero.'
            );
        }

        if ($affiliate->getKey() === $referredUser->getKey()) {
            throw new DomainException(
                'An affiliate cannot qualify their own sale.'
            );
        }

        if (
            (int) $referredUser->referred_by_user_id
            !== (int) $affiliate->getKey()
        ) {
            throw new DomainException(
                'The sale is not attributed to this direct referring affiliate.'
            );
        }

        return DB::transaction(function () use (
            $affiliate,
            $referredUser,
            $sourceReference,
            $netEligibleRevenueCents,
            $qualifiedAt
        ): AffiliateCommission {
            $existingSale = AffiliateQualifiedSale::query()
                ->where(
                    'source_provider',
                    AffiliateQualifiedSale::PROVIDER_PAYPAL
                )
                ->where('source_reference', $sourceReference)
                ->lockForUpdate()
                ->first();

            if ($existingSale !== null) {
                $this->assertIdempotentSaleMatches(
                    $existingSale,
                    $affiliate,
                    $referredUser,
                    $netEligibleRevenueCents
                );

                $existingCommission = $existingSale->commission()->first();

                if (! $existingCommission instanceof AffiliateCommission) {
                    throw new DomainException(
                        'The existing qualified sale is missing its commission ledger entry.'
                    );
                }

                return $existingCommission;
            }

            $profile = AffiliateProfile::query()->firstOrCreate(
                ['user_id' => $affiliate->getKey()],
                [
                    'qualified_sales_count' => 0,
                    'lifetime_cleared_sales_count' => 0,
                    'qualified_net_eligible_revenue_cents' => 0,
                    'lifetime_commission_earned_cents' => 0,
                    'commission_adjustment_cents' => 0,
                    'milestone_gifts_earned_cents' => 0,
                ]
            );

            $profile = AffiliateProfile::query()
                ->whereKey($profile->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $activeQualifiedCount = AffiliateQualifiedSale::query()
                ->where('affiliate_user_id', $affiliate->getKey())
                ->where('status', AffiliateQualifiedSale::STATUS_CLEARED)
                ->count();

            $qualifiedOrdinal = $activeQualifiedCount + 1;
            $ledgerSequence =
                (int) $profile->lifetime_cleared_sales_count + 1;

            $stage = AffiliateStage::forQualifiedSalesCount(
                $qualifiedOrdinal
            );

            if (! $stage instanceof AffiliateStage) {
                throw new DomainException(
                    'Affiliate commercial stages are not configured for this sales count.'
                );
            }

            [$rateBps, $rateSource, $specialRateApplied] =
                $this->commissionRateForProfile(
                    $profile,
                    $stage
                );

            $commissionAmountCents =
                $this->calculateCommissionCents(
                    $netEligibleRevenueCents,
                    $rateBps
                );

            $earnedAt = $qualifiedAt ?? now();

            $sale = AffiliateQualifiedSale::query()->create([
                'affiliate_user_id' => $affiliate->getKey(),
                'referred_user_id' => $referredUser->getKey(),
                'source_provider' => AffiliateQualifiedSale::PROVIDER_PAYPAL,
                'source_reference' => $sourceReference,
                'currency' => self::CURRENCY_USD,
                'ledger_sequence' => $ledgerSequence,
                'qualified_ordinal' => $qualifiedOrdinal,
                'net_eligible_revenue_cents' => $netEligibleRevenueCents,
                'status' => AffiliateQualifiedSale::STATUS_CLEARED,
                'qualified_at' => $earnedAt,
            ]);

            $commission = AffiliateCommission::query()->create([
                'affiliate_qualified_sale_id' => $sale->getKey(),
                'affiliate_user_id' => $affiliate->getKey(),
                'qualified_ordinal' => $qualifiedOrdinal,
                'rate_bps' => $rateBps,
                'rate_source' => $rateSource,
                'calculation_base_cents' => $netEligibleRevenueCents,
                'commission_amount_cents' => $commissionAmountCents,
                'currency' => self::CURRENCY_USD,
                'status' => AffiliateCommission::STATUS_EARNED,
                'earned_at' => $earnedAt,
            ]);

            $profile->forceFill([
                'current_stage_id' => $stage->getKey(),
                'qualified_sales_count' => $qualifiedOrdinal,
                'lifetime_cleared_sales_count' => $ledgerSequence,
                'qualified_net_eligible_revenue_cents' =>
                    (int) $profile->qualified_net_eligible_revenue_cents
                    + $netEligibleRevenueCents,
                'lifetime_commission_earned_cents' =>
                    (int) $profile->lifetime_commission_earned_cents
                    + $commissionAmountCents,
            ])->save();

            AuditLog::record(
                actorType: 'SYSTEM',
                actorId: null,
                action: 'AFFILIATE_QUALIFIED_SALE_CLEARED',
                module: 'AFFILIATES',
                targetType: 'AFFILIATE_QUALIFIED_SALE',
                targetId: $sale->getKey(),
                newValues: [
                    'affiliate_user_id' => $affiliate->getKey(),
                    'referred_user_id' => $referredUser->getKey(),
                    'source_provider' => AffiliateQualifiedSale::PROVIDER_PAYPAL,
                    'source_reference' => $sourceReference,
                    'qualified_ordinal' => $qualifiedOrdinal,
                    'net_eligible_revenue_cents' => $netEligibleRevenueCents,
                    'currency' => self::CURRENCY_USD,
                ]
            );

            AuditLog::record(
                actorType: 'SYSTEM',
                actorId: null,
                action: 'AFFILIATE_COMMISSION_EARNED',
                module: 'AFFILIATES',
                targetType: 'AFFILIATE_COMMISSION',
                targetId: $commission->getKey(),
                newValues: [
                    'qualified_sale_id' => $sale->getKey(),
                    'rate_bps' => $rateBps,
                    'rate_source' => $rateSource,
                    'calculation_base_cents' => $netEligibleRevenueCents,
                    'commission_amount_cents' => $commissionAmountCents,
                    'currency' => self::CURRENCY_USD,
                ]
            );

            if (! $specialRateApplied) {
                $this->awardMilestoneIfReached(
                    $profile,
                    $stage,
                    $qualifiedOrdinal,
                    $earnedAt
                );
            }

            return $commission->fresh([
                'qualifiedSale',
                'adjustments',
            ]);
        }, 3);
    }

    /**
     * Records only an externally forced payment exception.
     *
     * This is NOT a TradeX refund feature. TradeX does not expose a voluntary
     * refund action. This method exists only so accounting remains correct when
     * PayPal/the payment system, applicable law, or a confirmed billing/duplicate/
     * unauthorized-payment exception invalidates an already-cleared payment.
     */
    public function recordForcedPaymentReversal(
        AffiliateQualifiedSale $qualifiedSale,
        string $reversalCode,
        string $reason,
        ?int $adminId = null
    ): AffiliateCommissionAdjustment {
        $reversalCode = trim($reversalCode);
        $reason = trim($reason);

        if (! in_array(
            $reversalCode,
            AffiliateQualifiedSale::PAYMENT_REVERSAL_CODES,
            true
        )) {
            throw new DomainException(
                'The payment reversal code is not an approved no-refund exception.'
            );
        }

        if ($reason === '' || mb_strlen($reason) > 500) {
            throw new DomainException(
                'A valid payment reversal reason is required.'
            );
        }

        return DB::transaction(function () use (
            $qualifiedSale,
            $reversalCode,
            $reason,
            $adminId
        ): AffiliateCommissionAdjustment {
            $sale = AffiliateQualifiedSale::query()
                ->whereKey($qualifiedSale->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $existingAdjustment = AffiliateCommissionAdjustment::query()
                ->where('affiliate_qualified_sale_id', $sale->getKey())
                ->where('type', AffiliateCommissionAdjustment::TYPE_PAYMENT_REVERSAL)
                ->first();

            if ($sale->isPaymentReversed()) {
                if ($existingAdjustment instanceof AffiliateCommissionAdjustment) {
                    return $existingAdjustment;
                }

                throw new DomainException(
                    'The payment is reversed but its commission adjustment is missing.'
                );
            }

            $commission = $sale->commission()
                ->lockForUpdate()
                ->first();

            if (! $commission instanceof AffiliateCommission) {
                throw new DomainException(
                    'The qualified sale is missing its commission ledger entry.'
                );
            }

            $profile = AffiliateProfile::query()
                ->where('user_id', $sale->affiliate_user_id)
                ->lockForUpdate()
                ->first();

            if (! $profile instanceof AffiliateProfile) {
                throw new DomainException(
                    'The affiliate profile is missing.'
                );
            }

            $adjustmentAmountCents =
                -1 * (int) $commission->commission_amount_cents;

            $adjustment = AffiliateCommissionAdjustment::query()->create([
                'affiliate_commission_id' => $commission->getKey(),
                'affiliate_qualified_sale_id' => $sale->getKey(),
                'affiliate_user_id' => $sale->affiliate_user_id,
                'type' => AffiliateCommissionAdjustment::TYPE_PAYMENT_REVERSAL,
                'amount_cents' => $adjustmentAmountCents,
                'currency' => $commission->currency,
                'reason' => $reason,
                'created_by_admin_id' => $adminId,
            ]);

            $sale->forceFill([
                'status' => AffiliateQualifiedSale::STATUS_PAYMENT_REVERSED,
                'payment_reversed_at' => now(),
                'payment_reversal_code' => $reversalCode,
                'payment_reversal_reason' => $reason,
            ])->save();

            $activeQualifiedCount = AffiliateQualifiedSale::query()
                ->where('affiliate_user_id', $sale->affiliate_user_id)
                ->where('status', AffiliateQualifiedSale::STATUS_CLEARED)
                ->count();

            $currentStage = AffiliateStage::forQualifiedSalesCount(
                $activeQualifiedCount
            );

            $profile->forceFill([
                'current_stage_id' => $currentStage?->getKey(),
                'qualified_sales_count' => $activeQualifiedCount,
                'qualified_net_eligible_revenue_cents' => max(
                    0,
                    (int) $profile->qualified_net_eligible_revenue_cents
                    - (int) $sale->net_eligible_revenue_cents
                ),
                'commission_adjustment_cents' =>
                    (int) $profile->commission_adjustment_cents
                    + $adjustmentAmountCents,
            ])->save();

            AuditLog::record(
                actorType: $adminId === null ? 'SYSTEM' : 'ADMIN',
                actorId: $adminId,
                action: 'AFFILIATE_FORCED_PAYMENT_REVERSAL_RECORDED',
                module: 'AFFILIATES',
                targetType: 'AFFILIATE_QUALIFIED_SALE',
                targetId: $sale->getKey(),
                oldValues: [
                    'status' => AffiliateQualifiedSale::STATUS_CLEARED,
                ],
                newValues: [
                    'status' => AffiliateQualifiedSale::STATUS_PAYMENT_REVERSED,
                    'payment_reversal_code' => $reversalCode,
                    'payment_reversal_reason' => $reason,
                    'commission_adjustment_cents' => $adjustmentAmountCents,
                ]
            );

            return $adjustment->fresh();
        }, 3);
    }

    private function commissionRateForProfile(
        AffiliateProfile $profile,
        AffiliateStage $stage
    ): array {
        if (! $profile->hasApprovedSpecialCommission()) {
            return [
                (int) $stage->standard_commission_bps,
                AffiliateCommission::RATE_SOURCE_STANDARD,
                false,
            ];
        }

        $specialRateBps = (int) $profile->special_commission_bps;

        if (
            $specialRateBps <= self::STANDARD_MAX_COMMISSION_BPS
            || $specialRateBps > self::SPECIAL_MAX_COMMISSION_BPS
            || $profile->special_commission_approved_by_admin_id === null
            || blank($profile->special_commission_reason)
        ) {
            throw new DomainException(
                'Approved special affiliate commission must have management approval, a reason, and a rate above 20% up to 24%.'
            );
        }

        return [
            $specialRateBps,
            AffiliateCommission::RATE_SOURCE_SPECIAL,
            true,
        ];
    }

    private function calculateCommissionCents(
        int $calculationBaseCents,
        int $rateBps
    ): int {
        return intdiv(
            ($calculationBaseCents * $rateBps) + 5000,
            10000
        );
    }

    private function awardMilestoneIfReached(
        AffiliateProfile $profile,
        AffiliateStage $stage,
        int $qualifiedOrdinal,
        CarbonInterface $earnedAt
    ): void {
        if (
            $stage->milestone_sales === null
            || (int) $stage->milestone_sales !== $qualifiedOrdinal
            || (int) $stage->milestone_gift_cents <= 0
        ) {
            return;
        }

        $reward = AffiliateMilestoneReward::query()->firstOrCreate(
            [
                'affiliate_user_id' => $profile->user_id,
                'milestone_sales' => $qualifiedOrdinal,
            ],
            [
                'affiliate_stage_id' => $stage->getKey(),
                'gift_amount_cents' => (int) $stage->milestone_gift_cents,
                'currency' => self::CURRENCY_USD,
                'status' => AffiliateMilestoneReward::STATUS_EARNED,
                'earned_at' => $earnedAt,
            ]
        );

        if (! $reward->wasRecentlyCreated) {
            return;
        }

        $profile->increment(
            'milestone_gifts_earned_cents',
            (int) $stage->milestone_gift_cents
        );

        AuditLog::record(
            actorType: 'SYSTEM',
            actorId: null,
            action: 'AFFILIATE_MILESTONE_REWARD_EARNED',
            module: 'AFFILIATES',
            targetType: 'AFFILIATE_MILESTONE_REWARD',
            targetId: $reward->getKey(),
            newValues: [
                'affiliate_user_id' => $profile->user_id,
                'stage_code' => $stage->code,
                'milestone_sales' => $qualifiedOrdinal,
                'gift_amount_cents' => (int) $stage->milestone_gift_cents,
                'currency' => self::CURRENCY_USD,
            ]
        );
    }

    private function assertIdempotentSaleMatches(
        AffiliateQualifiedSale $existingSale,
        User $affiliate,
        User $referredUser,
        int $netEligibleRevenueCents
    ): void {
        $matches =
            (int) $existingSale->affiliate_user_id
                === (int) $affiliate->getKey()
            && (int) $existingSale->referred_user_id
                === (int) $referredUser->getKey()
            && (int) $existingSale->net_eligible_revenue_cents
                === $netEligibleRevenueCents
            && $existingSale->currency === self::CURRENCY_USD;

        if (! $matches) {
            throw new DomainException(
                'The PayPal source reference already exists with different affiliate sale data.'
            );
        }
    }
}
