<?php

namespace App\Services\CopyTrading;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\CopyTradeActivationEvaluation;
use App\Models\CopyTradeActivationEvaluationGate;
use App\Models\CopyTradeBrokerAccount;
use App\Models\CopyTradeVerifiedBalanceSnapshot;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CopyTradeActivationService
{
    public const GATE_BROKER =
        'BROKER_PARTNER_ELIGIBILITY';

    public const GATE_BALANCE =
        'VERIFIED_BILLABLE_BALANCE';

    public const GATE_BENEFIT =
        'ACCOUNT_BENEFIT_ASSIGNMENT';

    public const GATE_ENTITLEMENT =
        'ENTITLEMENT';

    public function __construct(
        private readonly CopyTradeBenefitService $benefits
    ) {
    }

    public function activate(
        CopyTradeBrokerAccount $account,
        ?AdminUser $admin = null,
        string $triggerSource = 'ADMIN'
    ): array {
        $this->authorize(
            $admin,
            $triggerSource
        );

        return DB::transaction(
            function () use (
                $account,
                $admin,
                $triggerSource
            ): array {
                /*
                 * User row acts as the per-user activation mutex.
                 * It serializes simultaneous market/account activation attempts
                 * so account-slot assignment remains deterministic.
                 */
                User::query()
                    ->whereKey($account->user_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $locked = CopyTradeBrokerAccount::query()
                    ->with([
                        'user',
                        'broker',
                        'brokerMarket.broker',
                        'accountBenefit.educationEntitlement',
                        'entitlementPeriods',
                    ])
                    ->whereKey($account->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($locked->isActive()) {
                    return $this->alreadyActive(
                        $locked
                    );
                }

                $now = now();

                $brokerGate =
                    $this->brokerGate($locked);

                $balanceGate =
                    $this->balanceGate($locked);

                $benefitPreview =
                    $this->benefits
                        ->preview($locked);

                $benefitGate = [
                    'passed' =>
                        (bool) $benefitPreview[
                            'benefit_resolved'
                        ],
                    'reason_code' =>
                        (string) $benefitPreview[
                            'benefit_reason_code'
                        ],
                    'evidence' => [
                        'cohort_position' =>
                            $benefitPreview[
                                'cohort_position'
                            ],
                        'account_slot' =>
                            $benefitPreview[
                                'account_slot'
                            ],
                        'benefit_type' =>
                            $benefitPreview[
                                'benefit_type'
                            ],
                        'previous_free_used' =>
                            (bool) $benefitPreview[
                                'previous_free_used'
                            ],
                    ],
                ];

                $entitlementGate = [
                    'passed' =>
                        (bool) $benefitPreview[
                            'entitlement_passed'
                        ],
                    'reason_code' =>
                        (string) $benefitPreview[
                            'entitlement_reason_code'
                        ],
                    'evidence' => [
                        'product_code' =>
                            $benefitPreview[
                                'product_code'
                            ],
                        'paid_entitlement_uuid' =>
                            $benefitPreview[
                                'paid_entitlement'
                            ]?->uuid,
                    ],
                ];

                $gates = [
                    self::GATE_BROKER =>
                        $brokerGate,

                    self::GATE_BALANCE =>
                        $balanceGate,

                    self::GATE_BENEFIT =>
                        $benefitGate,

                    self::GATE_ENTITLEMENT =>
                        $entitlementGate,
                ];

                $passed = collect($gates)
                    ->every(
                        fn (array $gate): bool =>
                            (bool) $gate['passed']
                    );

                $evaluation =
                    CopyTradeActivationEvaluation::query()
                        ->create([
                            'broker_account_id' =>
                                $locked->getKey(),
                            'overall_status' =>
                                $passed
                                    ? CopyTradeActivationEvaluation::STATUS_PASS
                                    : CopyTradeActivationEvaluation::STATUS_FAIL,
                            'trigger_source' =>
                                strtoupper(
                                    trim(
                                        $triggerSource
                                    )
                                ),
                            'evaluated_by_admin_id' =>
                                $admin?->getKey(),
                            'evaluated_at' =>
                                $now,
                            'activated_at' =>
                                $passed
                                    ? $now
                                    : null,
                        ]);

                foreach ($gates as $code => $gate) {
                    CopyTradeActivationEvaluationGate::query()
                        ->create([
                            'activation_evaluation_id' =>
                                $evaluation->getKey(),
                            'gate_code' =>
                                $code,
                            'passed' =>
                                (bool) $gate['passed'],
                            'reason_code' =>
                                (string) $gate[
                                    'reason_code'
                                ],
                            'evidence' =>
                                $gate['evidence'],
                        ]);
                }

                $persisted = null;

                if ($passed) {
                    $persisted = $this->benefits
                        ->persist(
                            $locked,
                            $benefitPreview,
                            Carbon::instance(
                                $now
                            )
                        );

                    $locked->forceFill([
                        'status' =>
                            CopyTradeBrokerAccount::STATUS_ACTIVE,
                        'activated_at' =>
                            $now,
                        'status_changed_by_admin_id' =>
                            $admin?->getKey(),
                    ])->save();
                }

                AuditLog::record(
                    actorType:
                        $admin instanceof AdminUser
                            ? 'ADMIN'
                            : 'SYSTEM',
                    actorId:
                        $admin?->getKey(),
                    action:
                        'COPY_TRADE_ACTIVATION_EVALUATED',
                    module:
                        'COPY_TRADING',
                    targetType:
                        'COPY_TRADE_BROKER_ACCOUNT',
                    targetId:
                        $locked->getKey(),
                    newValues: [
                        'evaluation_uuid' =>
                            $evaluation->uuid,
                        'overall_status' =>
                            $evaluation->overall_status,
                        'trigger_source' =>
                            $evaluation->trigger_source,
                        'activated' =>
                            $passed,
                        'gate_results' =>
                            collect($gates)
                                ->map(
                                    fn (
                                        array $gate
                                    ): array => [
                                        'passed' =>
                                            (bool) $gate[
                                                'passed'
                                            ],
                                        'reason_code' =>
                                            $gate[
                                                'reason_code'
                                            ],
                                    ]
                                )
                                ->all(),
                    ]
                );

                if ($passed) {
                    AuditLog::record(
                        actorType:
                            $admin instanceof AdminUser
                                ? 'ADMIN'
                                : 'SYSTEM',
                        actorId:
                            $admin?->getKey(),
                        action:
                            'COPY_TRADE_BROKER_ACCOUNT_ACTIVATED',
                        module:
                            'COPY_TRADING',
                        targetType:
                            'COPY_TRADE_BROKER_ACCOUNT',
                        targetId:
                            $locked->getKey(),
                        newValues: [
                            'evaluation_uuid' =>
                                $evaluation->uuid,
                            'account_masked' =>
                                $locked
                                    ->maskedAccountNumber(),
                            'market_category' =>
                                $locked
                                    ->brokerMarket
                                    ?->market_category,
                            'account_slot' =>
                                $persisted[
                                    'benefit'
                                ]?->account_slot,
                            'benefit_type' =>
                                $persisted[
                                    'benefit'
                                ]?->benefit_type,
                            'entitlement_period_uuid' =>
                                $persisted[
                                    'period'
                                ]?->uuid,
                            'activated_at' =>
                                $now
                                    ->toIso8601String(),
                        ]
                    );
                }

                return $this->present(
                    $locked->fresh([
                        'broker',
                        'brokerMarket',
                        'accountBenefit',
                        'entitlementPeriods',
                    ]),
                    $evaluation->load('gates'),
                    $benefitPreview,
                    $passed,
                    false
                );
            },
            3
        );
    }

    private function brokerGate(
        CopyTradeBrokerAccount $account
    ): array {
        $requiredCurrency = strtoupper(
            (string) (
                $account
                    ->brokerMarket
                    ?->required_currency
                ?: config(
                    'tradex_copy_trading.activation.currency',
                    'USD'
                )
            )
        );

        $passed =
            $account->status
                === CopyTradeBrokerAccount::STATUS_VERIFIED
            && $account->verified_at
                !== null
            && strtoupper(
                (string) $account
                    ->verified_account_mode
            ) === 'LIVE'
            && strtoupper(
                (string) $account
                    ->verified_currency
            ) === $requiredCurrency
            && $account
                ->partner_attribution_confirmed_at
                !== null
            && (bool) $account
                ->brokerMarket
                ?->is_active
            && (bool) $account
                ->broker
                ?->is_active;

        return [
            'passed' => $passed,
            'reason_code' =>
                $passed
                    ? 'BROKER_PARTNER_ELIGIBLE'
                    : 'BROKER_PARTNER_NOT_ELIGIBLE',
            'evidence' => [
                'status' =>
                    $account->status,
                'required_currency' =>
                    $requiredCurrency,
                'verified_currency' =>
                    $account
                        ->verified_currency,
                'verified_account_mode' =>
                    $account
                        ->verified_account_mode,
                'partner_attribution_confirmed' =>
                    $account
                        ->partner_attribution_confirmed_at
                        !== null,
                'broker_active' =>
                    (bool) $account
                        ->broker
                        ?->is_active,
                'market_active' =>
                    (bool) $account
                        ->brokerMarket
                        ?->is_active,
            ],
        ];
    }

    private function balanceGate(
        CopyTradeBrokerAccount $account
    ): array {
        $snapshot =
            CopyTradeVerifiedBalanceSnapshot::query()
                ->where(
                    'broker_account_id',
                    $account->getKey()
                )
                ->orderByDesc('observed_at')
                ->orderByDesc('id')
                ->first();

        $requiredCurrency = strtoupper(
            (string) config(
                'tradex_copy_trading.activation.currency',
                'USD'
            )
        );

        $minimum = (int) config(
            'tradex_copy_trading.activation.minimum_verified_billable_balance_minor',
            25000
        );

        $passed =
            $snapshot
                instanceof CopyTradeVerifiedBalanceSnapshot
            && strtoupper(
                (string) $snapshot->currency
            ) === $requiredCurrency
            && (int) $snapshot
                ->verified_billable_balance_minor
                >= $minimum;

        return [
            'passed' => $passed,
            'reason_code' =>
                ! $snapshot
                    ? 'VERIFIED_BILLABLE_BALANCE_MISSING'
                    : (
                        $passed
                            ? 'VERIFIED_BILLABLE_BALANCE_ELIGIBLE'
                            : 'VERIFIED_BILLABLE_BALANCE_BELOW_MINIMUM'
                    ),
            'evidence' => [
                'snapshot_uuid' =>
                    $snapshot?->uuid,
                'currency' =>
                    $snapshot?->currency,
                'verified_billable_balance_minor' =>
                    $snapshot
                        ?->verified_billable_balance_minor,
                'minimum_required_minor' =>
                    $minimum,
                'source' =>
                    $snapshot?->source,
                'observed_at' =>
                    $snapshot
                        ?->observed_at
                        ?->toIso8601String(),
            ],
        ];
    }

    private function authorize(
        ?AdminUser $admin,
        string $triggerSource
    ): void {
        $source = strtoupper(
            trim(
                $triggerSource
            )
        );

        if (
            ! in_array(
                $source,
                (array) config(
                    'tradex_copy_trading.activation.trigger_sources',
                    []
                ),
                true
            )
        ) {
            throw new \DomainException(
                'Unsupported Copy Trade activation trigger source.'
            );
        }

        if (
            $source === 'ADMIN'
            && (
                ! $admin instanceof AdminUser
                || (
                    ! $admin->isSuperAdmin()
                    && ! $admin->hasPermission(
                        'copy_trading.accounts.activate'
                    )
                )
            )
        ) {
            throw new AuthorizationException(
                'Copy Trade activation permission is required.'
            );
        }
    }

    private function alreadyActive(
        CopyTradeBrokerAccount $account
    ): array {
        $evaluation = $account
            ->activationEvaluations()
            ->with('gates')
            ->first();

        return [
            'activated' => true,
            'already_active' => true,
            'account' => [
                'uuid' =>
                    $account->uuid,
                'status' =>
                    $account->status,
                'activated_at' =>
                    $account
                        ->activated_at
                        ?->toIso8601String(),
            ],
            'evaluation' =>
                $evaluation
                    ? $this->presentEvaluation(
                        $evaluation
                    )
                    : null,
            'benefit' =>
                $account->accountBenefit
                    ? [
                        'cohort_position' =>
                            (int) $account
                                ->accountBenefit
                                ->cohort_position,
                        'account_slot' =>
                            (int) $account
                                ->accountBenefit
                                ->account_slot,
                        'benefit_type' =>
                            $account
                                ->accountBenefit
                                ->benefit_type,
                    ]
                    : null,
        ];
    }

    private function present(
        CopyTradeBrokerAccount $account,
        CopyTradeActivationEvaluation $evaluation,
        array $preview,
        bool $activated,
        bool $alreadyActive
    ): array {
        return [
            'activated' =>
                $activated,
            'already_active' =>
                $alreadyActive,
            'account' => [
                'uuid' =>
                    $account->uuid,
                'status' =>
                    $account->status,
                'activated_at' =>
                    $account
                        ->activated_at
                        ?->toIso8601String(),
            ],
            'evaluation' =>
                $this->presentEvaluation(
                    $evaluation
                ),
            'benefit' => [
                'cohort_position' =>
                    $preview[
                        'cohort_position'
                    ],
                'account_slot' =>
                    $preview[
                        'account_slot'
                    ],
                'benefit_type' =>
                    $preview[
                        'benefit_type'
                    ],
                'product_code' =>
                    $preview[
                        'product_code'
                    ],
                'previous_free_used' =>
                    (bool) $preview[
                        'previous_free_used'
                    ],
            ],
        ];
    }

    private function presentEvaluation(
        CopyTradeActivationEvaluation $evaluation
    ): array {
        $evaluation->loadMissing('gates');

        return [
            'uuid' =>
                $evaluation->uuid,
            'overall_status' =>
                $evaluation->overall_status,
            'trigger_source' =>
                $evaluation->trigger_source,
            'evaluated_at' =>
                $evaluation
                    ->evaluated_at
                    ?->toIso8601String(),
            'activated_at' =>
                $evaluation
                    ->activated_at
                    ?->toIso8601String(),
            'gates' =>
                $evaluation->gates
                    ->map(
                        fn (
                            CopyTradeActivationEvaluationGate $gate
                        ): array => [
                            'gate_code' =>
                                $gate->gate_code,
                            'passed' =>
                                (bool) $gate->passed,
                            'reason_code' =>
                                $gate->reason_code,
                            'evidence' =>
                                $gate->evidence,
                        ]
                    )
                    ->values()
                    ->all(),
        ];
    }
}
