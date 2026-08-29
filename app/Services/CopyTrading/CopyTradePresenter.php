<?php

namespace App\Services\CopyTrading;

use App\Models\CopyTradeActivationEvaluationGate;
use App\Models\CopyTradeBrokerAccount;
use App\Models\CopyTradeBrokerMarket;
use App\Models\CopyTradeEntitlementPeriod;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;

class CopyTradePresenter
{
    public function bootstrap(
        User $user,
        Collection $brokerMarkets,
        Collection $accounts
    ): array {
        $premiumActive =
            $user->canAccessPremium();

        return [
            'first_time_user' => $accounts->isEmpty(),
            'next_step' =>
                $premiumActive
                    ? $this->nextStep(
                        $accounts
                    )
                    : 'EDUCATION_PREMIUM_REQUIRED',
            'education_premium' => [
                'required' =>
                    true,
                'active' =>
                    $premiumActive,
                'product_code' =>
                    Product::CODE_EDUCATION_PREMIUM,
                'reason_code' =>
                    $premiumActive
                        ? 'EDUCATION_PREMIUM_ACTIVE'
                        : 'EDUCATION_PREMIUM_REQUIRED',
                'products_endpoint' =>
                    '/api/v1/products',
                'payment_methods_endpoint' =>
                    '/api/v1/payments/methods',
            ],
            'has_verified_broker_account' => $accounts->contains(
                fn (
                    CopyTradeBrokerAccount $account
                ): bool =>
                    $account
                        ->hasVerifiedBrokerRelationship()
            ),
            'copy_trade_ready' =>
                $premiumActive
                && $accounts->contains(
                    fn (
                        CopyTradeBrokerAccount $account
                    ): bool =>
                        $account->isActive()
                ),
            'brokers' => $brokerMarkets
                ->map(
                    fn (
                        CopyTradeBrokerMarket $market
                    ): array =>
                        $this->brokerMarket(
                            $market
                        )
                )
                ->values()
                ->all(),
            'broker_accounts' => $accounts
                ->map(
                    fn (
                        CopyTradeBrokerAccount $account
                    ): array =>
                        $this->account(
                            $account
                        )
                )
                ->values()
                ->all(),
            'activation_policy' => [
                'verification_does_not_auto_activate' =>
                    true,
                'education_premium_required' =>
                    true,
                'required_broker_account_mode' =>
                    'LIVE',
                'required_currency' =>
                    (string) config(
                        'tradex_copy_trading.activation.currency',
                        config(
                            'tradex_copy_trading.required_currency',
                            'USD'
                        )
                    ),
                'minimum_verified_billable_balance_minor' =>
                    (int) config(
                        'tradex_copy_trading.activation.minimum_verified_billable_balance_minor',
                        25000
                    ),
                'activation_requires_additional_eligibility_gates' =>
                    true,
            ],
        ];
    }

    public function brokerMarket(
        CopyTradeBrokerMarket $market
    ): array {
        $market->loadMissing('broker');

        $broker = $market->broker;

        return [
            'broker_uuid' =>
                $broker?->uuid,
            'broker_code' =>
                $broker?->code,
            'broker_name' =>
                $broker?->name,
            'broker_market_uuid' =>
                $market->uuid,
            'market_category' =>
                $market->market_category,
            'required_currency' =>
                $market->required_currency,
            'live_account_only' =>
                (bool) $market
                    ->live_account_only,
            'logo_url' =>
                $broker?->hasLogo()
                    ? route(
                        'api.v1.copy-trade.brokers.logo',
                        [
                            'copyTradeBroker' =>
                                $broker,
                        ]
                    )
                    : null,
            'open_account_action_required' =>
                true,
        ];
    }

    public function account(
        CopyTradeBrokerAccount $account
    ): array {
        $account->loadMissing([
            'user',
            'broker',
            'brokerMarket',
        ]);

        return [
            'uuid' =>
                $account->uuid,
            'broker' => [
                'uuid' =>
                    $account->broker?->uuid,
                'code' =>
                    $account->broker?->code,
                'name' =>
                    $account->broker?->name,
            ],
            'market_category' =>
                $account
                    ->brokerMarket
                    ?->market_category,
            'account_number_masked' =>
                $account
                    ->maskedAccountNumber(),
            'status' =>
                $account->status,
            'status_reason' =>
                $this
                    ->userVisibleStatusReason(
                        $account
                    ),
            'resubmission_count' =>
                $account
                    ->resubmission_count,
            'submitted_at' =>
                $account
                    ->submitted_at
                    ?->toIso8601String(),
            'verified_at' =>
                $account
                    ->verified_at
                    ?->toIso8601String(),
            'activated_at' =>
                $account
                    ->activated_at
                    ?->toIso8601String(),
            'activation_state' =>
                $this
                    ->activationState(
                        $account
                    ),
            'copy_trade_ready' =>
                $account->isActive()
                && $account->user
                    ?->canAccessPremium(),
            'activation' =>
                $this->activation(
                    $account
                ),
        ];
    }

    public function activation(
        CopyTradeBrokerAccount $account
    ): array {
        $account->loadMissing([
            'user',
            'latestVerifiedBalanceSnapshot',
            'accountBenefit',
            'entitlementPeriods',
            'latestActivationEvaluation.gates',
        ]);

        $snapshot =
            $account
                ->latestVerifiedBalanceSnapshot;

        $benefit =
            $account
                ->accountBenefit;

        $period =
            $account
                ->entitlementPeriods
                ->sortByDesc('starts_at')
                ->first();

        $evaluation =
            $account
                ->latestActivationEvaluation;

        return [
            'state' =>
                $this
                    ->activationState(
                        $account
                    ),
            'education_premium_active' =>
                (bool) $account->user
                    ?->canAccessPremium(),
            'copy_trade_ready' =>
                $account->isActive()
                && (bool) $account->user
                    ?->canAccessPremium(),
            'minimum_verified_billable_balance_minor' =>
                (int) config(
                    'tradex_copy_trading.activation.minimum_verified_billable_balance_minor',
                    25000
                ),
            'required_currency' =>
                (string) config(
                    'tradex_copy_trading.activation.currency',
                    'USD'
                ),
            'latest_verified_billable_balance' =>
                $snapshot
                    ? [
                        'currency' =>
                            $snapshot->currency,
                        'amount_minor' =>
                            (int) $snapshot
                                ->verified_billable_balance_minor,
                        'observed_at' =>
                            $snapshot
                                ->observed_at
                                ?->toIso8601String(),
                    ]
                    : null,
            'benefit' =>
                $benefit
                    ? [
                        'account_slot' =>
                            (int) $benefit
                                ->account_slot,
                        'benefit_type' =>
                            $benefit
                                ->benefit_type,
                    ]
                    : null,
            'entitlement' =>
                $period
                instanceof CopyTradeEntitlementPeriod
                    ? [
                        'type' =>
                            $period
                                ->entitlement_type,
                        'product_code' =>
                            $period
                                ->product_code,
                        'starts_at' =>
                            $period
                                ->starts_at
                                ?->toIso8601String(),
                        'ends_at' =>
                            $period
                                ->ends_at
                                ?->toIso8601String(),
                    ]
                    : null,
            'latest_evaluation' =>
                $evaluation
                    ? [
                        'overall_status' =>
                            $evaluation
                                ->overall_status,
                        'evaluated_at' =>
                            $evaluation
                                ->evaluated_at
                                ?->toIso8601String(),
                        'activated_at' =>
                            $evaluation
                                ->activated_at
                                ?->toIso8601String(),
                        'gates' =>
                            $evaluation
                                ->gates
                                ->map(
                                    fn (
                                        CopyTradeActivationEvaluationGate $gate
                                    ): array => [
                                        'gate_code' =>
                                            $gate
                                                ->gate_code,
                                        'passed' =>
                                            (bool) $gate
                                                ->passed,
                                        'reason_code' =>
                                            $gate
                                                ->reason_code,
                                    ]
                                )
                                ->values()
                                ->all(),
                    ]
                    : null,
        ];
    }

    private function nextStep(
        Collection $accounts
    ): string {
        if (
            $accounts->contains(
                fn (
                    CopyTradeBrokerAccount $account
                ): bool =>
                    $account->isActive()
            )
        ) {
            return 'COPY_TRADE_READY';
        }

        if (
            $accounts->contains(
                fn (
                    CopyTradeBrokerAccount $account
                ): bool =>
                    $account->isVerified()
            )
        ) {
            return 'ACTIVATION_PENDING';
        }

        if (
            $accounts->contains(
                fn (
                    CopyTradeBrokerAccount $account
                ): bool =>
                    $account
                        ->needsResubmission()
                    || $account
                        ->isRejected()
            )
        ) {
            return 'ACTION_REQUIRED';
        }

        if (
            $accounts->contains(
                fn (
                    CopyTradeBrokerAccount $account
                ): bool =>
                    $account->isPending()
                    || $account
                        ->isEscalated()
            )
        ) {
            return 'WAITING_FOR_VERIFICATION';
        }

        return 'CONNECT_BROKER';
    }

    private function userVisibleStatusReason(
        CopyTradeBrokerAccount $account
    ): ?string {
        if (
            ! in_array(
                $account->status,
                [
                    CopyTradeBrokerAccount::STATUS_REJECTED,
                    CopyTradeBrokerAccount::STATUS_NEEDS_RESUBMISSION,
                ],
                true
            )
        ) {
            return null;
        }

        return $account
            ->last_status_reason;
    }

    private function activationState(
        CopyTradeBrokerAccount $account
    ): string {
        return match (
            $account->status
        ) {
            CopyTradeBrokerAccount::STATUS_ACTIVE =>
                'ACTIVE',

            CopyTradeBrokerAccount::STATUS_VERIFIED =>
                'VERIFIED_PENDING_GATES',

            CopyTradeBrokerAccount::STATUS_SUSPENDED =>
                'SUSPENDED',

            CopyTradeBrokerAccount::STATUS_DISCONNECTED =>
                'DISCONNECTED',

            default =>
                'NOT_ELIGIBLE',
        };
    }
}
