<?php

namespace App\Services\CopyTrading;

use App\Models\CopyTradeAccountBenefit;
use App\Models\CopyTradeBrokerAccount;
use App\Models\CopyTradeEntitlementPeriod;
use App\Models\UserEntitlement;
use Illuminate\Support\Carbon;

class CopyTradeBenefitService
{
    public function __construct(
        private readonly CopyTradeCohortService $cohorts,
        private readonly CopyTradeAccountSlotService $slots
    ) {
    }

    public function preview(
        CopyTradeBrokerAccount $account
    ): array {
        $account->loadMissing([
            'user',
            'brokerMarket',
            'accountBenefit',
        ]);

        if ($account->accountBenefit instanceof CopyTradeAccountBenefit) {
            $benefit = $account->accountBenefit;

            return [
                'benefit_resolved' => true,
                'benefit_reason_code' => 'BENEFIT_ALREADY_ASSIGNED',
                'cohort_position' => (int) $benefit->cohort_position,
                'account_slot' => (int) $benefit->account_slot,
                'benefit_type' => $benefit->benefit_type,
                'previous_free_used' => in_array(
                    $benefit->benefit_type,
                    [
                        CopyTradeAccountBenefit::BENEFIT_LIFETIME_FREE_ACCOUNT_1,
                        CopyTradeAccountBenefit::BENEFIT_THIRTY_DAY_FREE_ACCOUNT_1,
                    ],
                    true
                ),
                'education_entitlement' => $benefit->educationEntitlement,
                ...$this->entitlementState(
                    $account,
                    (int) $benefit->account_slot,
                    $benefit->benefit_type
                ),
            ];
        }

        $qualification = $this->cohorts
            ->qualification($account->user);

        if (! $qualification['qualified']) {
            return [
                'benefit_resolved' => false,
                'benefit_reason_code' =>
                    $qualification['reason_code'],
                'cohort_position' => null,
                'account_slot' => null,
                'benefit_type' => null,
                'previous_free_used' => false,
                'education_entitlement' =>
                    $qualification['education_entitlement'],
                'entitlement_passed' => false,
                'entitlement_reason_code' =>
                    'BENEFIT_NOT_RESOLVED',
                'paid_entitlement' => null,
                'product_code' => null,
            ];
        }

        try {
            $slot = $this->slots->resolve($account);
        } catch (\DomainException) {
            return [
                'benefit_resolved' => false,
                'benefit_reason_code' =>
                    'ACCOUNT_SLOT_UNAVAILABLE',
                'cohort_position' =>
                    $qualification['cohort_position'],
                'account_slot' => null,
                'benefit_type' => null,
                'previous_free_used' => false,
                'education_entitlement' =>
                    $qualification['education_entitlement'],
                'entitlement_passed' => false,
                'entitlement_reason_code' =>
                    'BENEFIT_NOT_RESOLVED',
                'paid_entitlement' => null,
                'product_code' => null,
            ];
        }

        $market = strtoupper(
            (string) $account->brokerMarket?->market_category
        );

        $previousFreeUsed = CopyTradeAccountBenefit::query()
            ->where(
                'user_id',
                $account->user_id
            )
            ->where(
                'market_category',
                $market
            )
            ->where(
                'account_slot',
                $slot
            )
            ->whereIn(
                'benefit_type',
                [
                    CopyTradeAccountBenefit::BENEFIT_LIFETIME_FREE_ACCOUNT_1,
                    CopyTradeAccountBenefit::BENEFIT_THIRTY_DAY_FREE_ACCOUNT_1,
                ]
            )
            ->exists();

        $benefitType =
            CopyTradeAccountBenefit::BENEFIT_PAID_REQUIRED;

        if ($slot === 1 && ! $previousFreeUsed) {
            $lifetimeLimit = (int) config(
                'tradex_copy_trading.activation.first_eligible_users_lifetime_free_count',
                1000
            );

            $benefitType =
                (int) $qualification['cohort_position']
                    <= $lifetimeLimit
                    ? CopyTradeAccountBenefit::BENEFIT_LIFETIME_FREE_ACCOUNT_1
                    : CopyTradeAccountBenefit::BENEFIT_THIRTY_DAY_FREE_ACCOUNT_1;
        }

        return [
            'benefit_resolved' => true,
            'benefit_reason_code' =>
                $previousFreeUsed
                    ? 'FREE_BENEFIT_ALREADY_USED'
                    : 'BENEFIT_RESOLVED',
            'cohort_position' =>
                (int) $qualification['cohort_position'],
            'account_slot' => $slot,
            'benefit_type' => $benefitType,
            'previous_free_used' => $previousFreeUsed,
            'education_entitlement' =>
                $qualification['education_entitlement'],
            ...$this->entitlementState(
                $account,
                $slot,
                $benefitType
            ),
        ];
    }

    public function persist(
        CopyTradeBrokerAccount $account,
        array $preview,
        Carbon $startsAt
    ): array {
        $existing = $account
            ->accountBenefit()
            ->first();

        if ($existing instanceof CopyTradeAccountBenefit) {
            return [
                'benefit' => $existing,
                'period' => $account
                    ->entitlementPeriods()
                    ->latest('starts_at')
                    ->first(),
            ];
        }

        $educationEntitlement =
            $preview['education_entitlement'];

        $benefit = CopyTradeAccountBenefit::query()
            ->create([
                'user_id' => $account->user_id,
                'broker_account_id' => $account->getKey(),
                'education_entitlement_id' =>
                    $educationEntitlement
                    instanceof UserEntitlement
                        ? $educationEntitlement->getKey()
                        : null,
                'market_category' => strtoupper(
                    (string) $account
                        ->brokerMarket
                        ?->market_category
                ),
                'account_slot' =>
                    (int) $preview['account_slot'],
                'cohort_position' =>
                    (int) $preview['cohort_position'],
                'benefit_type' =>
                    (string) $preview['benefit_type'],
                'assigned_at' => $startsAt,
            ]);

        $period = $this->createPeriod(
            $account,
            $benefit,
            $preview,
            $startsAt
        );

        return [
            'benefit' => $benefit,
            'period' => $period,
        ];
    }

    private function entitlementState(
        CopyTradeBrokerAccount $account,
        int $slot,
        string $benefitType
    ): array {
        $market = strtoupper(
            (string) $account->brokerMarket?->market_category
        );

        if (
            $benefitType
            === CopyTradeAccountBenefit::BENEFIT_LIFETIME_FREE_ACCOUNT_1
        ) {
            return [
                'entitlement_passed' => true,
                'entitlement_reason_code' =>
                    'LIFETIME_FREE_ENTITLEMENT',
                'paid_entitlement' => null,
                'product_code' =>
                    CopyTradeProductCode::lifetimeFree(
                        $market,
                        $slot
                    ),
            ];
        }

        if (
            $benefitType
            === CopyTradeAccountBenefit::BENEFIT_THIRTY_DAY_FREE_ACCOUNT_1
        ) {
            return [
                'entitlement_passed' => true,
                'entitlement_reason_code' =>
                    'THIRTY_DAY_FREE_ENTITLEMENT',
                'paid_entitlement' => null,
                'product_code' =>
                    CopyTradeProductCode::thirtyDayFree(
                        $market,
                        $slot
                    ),
            ];
        }

        $productCode = CopyTradeProductCode::monthly(
            $market,
            $slot
        );

        $paid = UserEntitlement::query()
            ->with('payment')
            ->where(
                'user_id',
                $account->user_id
            )
            ->where(
                'product_code',
                $productCode
            )
            ->where(
                'status',
                UserEntitlement::STATUS_ACTIVE
            )
            ->whereNotNull('payment_id')
            ->first();

        $cleared = $paid instanceof UserEntitlement
            && $paid->payment?->isCleared();

        return [
            'entitlement_passed' => $cleared,
            'entitlement_reason_code' =>
                $cleared
                    ? 'PAID_ENTITLEMENT_ACTIVE'
                    : 'PAID_ENTITLEMENT_REQUIRED',
            'paid_entitlement' =>
                $cleared
                    ? $paid
                    : null,
            'product_code' => $productCode,
        ];
    }

    private function createPeriod(
        CopyTradeBrokerAccount $account,
        CopyTradeAccountBenefit $benefit,
        array $preview,
        Carbon $startsAt
    ): CopyTradeEntitlementPeriod {
        $type = match ($benefit->benefit_type) {
            CopyTradeAccountBenefit::BENEFIT_LIFETIME_FREE_ACCOUNT_1 =>
                CopyTradeEntitlementPeriod::TYPE_LIFETIME_FREE,

            CopyTradeAccountBenefit::BENEFIT_THIRTY_DAY_FREE_ACCOUNT_1 =>
                CopyTradeEntitlementPeriod::TYPE_THIRTY_DAY_FREE,

            default =>
                CopyTradeEntitlementPeriod::TYPE_PAID,
        };

        $endsAt = null;

        if (
            $type
            === CopyTradeEntitlementPeriod::TYPE_THIRTY_DAY_FREE
        ) {
            $endsAt = $startsAt
                ->copy()
                ->addDays(
                    (int) config(
                        'tradex_copy_trading.activation.later_user_account_1_free_days',
                        30
                    )
                );
        }

        $paid = $preview['paid_entitlement'] ?? null;

        return CopyTradeEntitlementPeriod::query()
            ->create([
                'broker_account_id' => $account->getKey(),
                'account_benefit_id' => $benefit->getKey(),
                'user_entitlement_id' =>
                    $paid instanceof UserEntitlement
                        ? $paid->getKey()
                        : null,
                'entitlement_type' => $type,
                'product_code' =>
                    (string) $preview['product_code'],
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);
    }
}
