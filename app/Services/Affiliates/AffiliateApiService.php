<?php

namespace App\Services\Affiliates;

use App\Exceptions\AuthApiException;
use App\Models\AffiliateCommission;
use App\Models\AffiliateMilestoneReward;
use App\Models\AffiliateProfile;
use App\Models\AffiliateQualifiedSale;
use App\Models\AffiliateStage;
use App\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;

class AffiliateApiService
{
    private const CURRENCY_USD = 'USD';

    public function overview(
        User $user
    ): array {
        $this->assertPremium(
            $user
        );

        $profile = $this->profile(
            $user
        );

        $qualifiedSalesCount =
            $profile instanceof AffiliateProfile
                ? (int) $profile
                    ->qualified_sales_count
                : 0;

        $currentStage =
            $profile?->currentStage;

        $specialRateApplied =
            $profile instanceof AffiliateProfile
            && $profile
                ->hasApprovedSpecialCommission();

        $nextSaleStage =
            AffiliateStage::forQualifiedSalesCount(
                $qualifiedSalesCount + 1
            );

        $nextRateBps =
            $specialRateApplied
                ? (int) $profile
                    ->special_commission_bps
                : (
                    $nextSaleStage instanceof AffiliateStage
                        ? (int) $nextSaleStage
                            ->standard_commission_bps
                        : null
                );

        $earnedCommission =
            $profile instanceof AffiliateProfile
                ? (int) $profile
                    ->lifetime_commission_earned_cents
                : 0;

        $adjustments =
            $profile instanceof AffiliateProfile
                ? (int) $profile
                    ->commission_adjustment_cents
                : 0;

        $milestoneGifts =
            $profile instanceof AffiliateProfile
                ? (int) $profile
                    ->milestone_gifts_earned_cents
                : 0;

        $netCommission =
            $earnedCommission
            + $adjustments;

        return [
            'program' => [
                'model' =>
                    'PROGRESSIVE_DIRECT_SALE',

                'single_level_only' =>
                    true,

                'multi_level_commission' =>
                    false,

                'commission_base' =>
                    'PAYPAL_NET_ELIGIBLE_REVENUE',

                'normal_standard_ceiling_percent' =>
                    '20.00',

                'approved_special_ceiling_percent' =>
                    '24.00',

                'voluntary_refund_feature' =>
                    false,
            ],

            'referral' => [
                'code' =>
                    $user->referral_code,

                'url' =>
                    $this->referralUrl(
                        $user
                    ),

                'direct_referrals_count' =>
                    $user->referrals()
                        ->count(),
            ],

            'ledger_initialized' =>
                $profile instanceof AffiliateProfile,

            'qualified_sales_count' =>
                $qualifiedSalesCount,

            'lifetime_cleared_sales_count' =>
                $profile instanceof AffiliateProfile
                    ? (int) $profile
                        ->lifetime_cleared_sales_count
                    : 0,

            'current_stage' =>
                $currentStage instanceof AffiliateStage
                    ? $this->stageSummary(
                        $currentStage
                    )
                    : null,

            'next_stage' =>
                $this->nextStage(
                    $qualifiedSalesCount
                ),

            'commission' => [
                'special_exception_applied' =>
                    $specialRateApplied,

                'approved_special_rate_percent' =>
                    $specialRateApplied
                        ? $this->percentFromBps(
                            (int) $profile
                                ->special_commission_bps
                        )
                        : null,

                'next_sale_rate_percent' =>
                    $this->percentFromBps(
                        $nextRateBps
                    ),
            ],

            'financials' => [
                'qualified_net_eligible_revenue' =>
                    $this->money(
                        $profile instanceof AffiliateProfile
                            ? (int) $profile
                                ->qualified_net_eligible_revenue_cents
                            : 0
                    ),

                'lifetime_commission_earned' =>
                    $this->money(
                        $earnedCommission
                    ),

                'processor_payment_adjustments' =>
                    $this->money(
                        $adjustments
                    ),

                'net_commission' =>
                    $this->money(
                        $netCommission
                    ),

                'milestone_gifts_earned' =>
                    $this->money(
                        $milestoneGifts
                    ),

                'total_affiliate_rewards' =>
                    $this->money(
                        $netCommission
                        + $milestoneGifts
                    ),
            ],

            'milestones' => [
                'eligible' =>
                    ! $specialRateApplied,

                'next' =>
                    $specialRateApplied
                        ? null
                        : $this->nextMilestone(
                            $qualifiedSalesCount
                        ),
            ],

            /*
            | No payout balance/status is returned here because TradeX does not
            | yet have a frozen payout-ledger foundation. API-09 must not invent it.
            */
        ];
    }

    public function stages(
        User $user
    ): array {
        $this->assertPremium(
            $user
        );

        return [
            'items' =>
                AffiliateStage::query()
                    ->active()
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get()
                    ->map(
                        fn (
                            AffiliateStage $stage
                        ): array =>
                            $this->stageDetail(
                                $stage
                            )
                    )
                    ->values()
                    ->all(),

            'progressive_not_retroactive' =>
                true,

            'special_exception' => [
                'maximum_percent' =>
                    '24.00',

                'milestone_gifts_eligible' =>
                    false,
            ],
        ];
    }

    public function directReferrals(
        User $user,
        array $filters
    ): array {
        $this->assertPremium(
            $user
        );

        $paginator = User::query()
            ->where(
                'referred_by_user_id',
                $user->getKey()
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(
                $this->perPage(
                    $filters
                )
            )
            ->withQueryString();

        $items = collect(
            $paginator->items()
        );

        $referralIds = $items
            ->pluck('id')
            ->all();

        $salesStatsByReferral =
            $referralIds === []
                ? collect()
                : AffiliateQualifiedSale::query()
                    ->select('referred_user_id')
                    ->selectRaw(
                        'COUNT(*) as lifetime_count'
                    )
                    ->selectRaw(
                        'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active_count',
                        [
                            AffiliateQualifiedSale::STATUS_CLEARED,
                        ]
                    )
                    ->selectRaw(
                        'MAX(qualified_at) as latest_qualified_at'
                    )
                    ->where(
                        'affiliate_user_id',
                        $user->getKey()
                    )
                    ->whereIn(
                        'referred_user_id',
                        $referralIds
                    )
                    ->groupBy(
                        'referred_user_id'
                    )
                    ->get()
                    ->keyBy(
                        'referred_user_id'
                    );

        return [
            'items' =>
                $items
                    ->map(
                        function (
                            User $referral
                        ) use (
                            $salesStatsByReferral
                        ): array {
                            $stats =
                                $salesStatsByReferral->get(
                                    $referral->getKey()
                                );

                            $activeCount =
                                (int) (
                                    $stats?->getAttribute(
                                        'active_count'
                                    )
                                    ?? 0
                                );

                            $lifetimeCount =
                                (int) (
                                    $stats?->getAttribute(
                                        'lifetime_count'
                                    )
                                    ?? 0
                                );

                            $latestQualifiedAt =
                                $stats?->getAttribute(
                                    'latest_qualified_at'
                                );

                            return [
                                'uuid' =>
                                    $referral->uuid,

                                'name' =>
                                    $referral->name,

                                'joined_at' =>
                                    $referral->created_at
                                        ?->toISOString(),

                                'qualification' => [
                                    'has_qualified_sale' =>
                                        $activeCount > 0,

                                    'active_qualified_sales_count' =>
                                        $activeCount,

                                    'lifetime_recorded_sales_count' =>
                                        $lifetimeCount,

                                    'latest_qualified_at' =>
                                        filled(
                                            $latestQualifiedAt
                                        )
                                            ? CarbonImmutable::parse(
                                                (string) $latestQualifiedAt,
                                                'UTC'
                                            )->toISOString()
                                            : null,
                                ],
                            ];
                        }
                    )
                    ->values()
                    ->all(),

            'pagination' =>
                $this->pagination(
                    $paginator
                ),

            'scope' =>
                'DIRECT_REFERRALS_ONLY',
        ];
    }

    public function qualifiedSales(
        User $user,
        array $filters
    ): array {
        $this->assertPremium(
            $user
        );

        $query =
            AffiliateQualifiedSale::query()
                ->with([
                    'referredUser:id,uuid,name',
                    'commission.adjustments',
                ])
                ->where(
                    'affiliate_user_id',
                    $user->getKey()
                );

        if (
            filled(
                $filters['status']
                ?? null
            )
        ) {
            $query->where(
                'status',
                $filters['status']
            );
        }

        $paginator = $query
            ->orderByDesc(
                'qualified_at'
            )
            ->orderByDesc(
                'id'
            )
            ->paginate(
                $this->perPage(
                    $filters
                )
            )
            ->withQueryString();

        return [
            'items' =>
                collect(
                    $paginator->items()
                )
                    ->map(
                        fn (
                            AffiliateQualifiedSale $sale
                        ): array =>
                            $this->saleSummary(
                                $sale
                            )
                    )
                    ->values()
                    ->all(),

            'pagination' =>
                $this->pagination(
                    $paginator
                ),

            'privacy' => [
                'paypal_source_reference_exposed' =>
                    false,

                'payment_reversal_reason_exposed' =>
                    false,
            ],
        ];
    }

    public function commissions(
        User $user,
        array $filters
    ): array {
        $this->assertPremium(
            $user
        );

        $query =
            AffiliateCommission::query()
                ->with([
                    'qualifiedSale.referredUser:id,uuid,name',
                    'adjustments',
                ])
                ->where(
                    'affiliate_user_id',
                    $user->getKey()
                );

        if (
            filled(
                $filters['rate_source']
                ?? null
            )
        ) {
            $query->where(
                'rate_source',
                $filters['rate_source']
            );
        }

        $paginator = $query
            ->orderByDesc(
                'earned_at'
            )
            ->orderByDesc(
                'id'
            )
            ->paginate(
                $this->perPage(
                    $filters
                )
            )
            ->withQueryString();

        return [
            'items' =>
                collect(
                    $paginator->items()
                )
                    ->map(
                        fn (
                            AffiliateCommission $commission
                        ): array =>
                            $this->commissionSummary(
                                $commission
                            )
                    )
                    ->values()
                    ->all(),

            'pagination' =>
                $this->pagination(
                    $paginator
                ),

            'calculation_model' =>
                'PROGRESSIVE_NOT_RETROACTIVE',
        ];
    }

    public function milestones(
        User $user
    ): array {
        $this->assertPremium(
            $user
        );

        $profile = $this->profile(
            $user
        );

        $qualifiedSalesCount =
            $profile instanceof AffiliateProfile
                ? (int) $profile
                    ->qualified_sales_count
                : 0;

        $specialRateApplied =
            $profile instanceof AffiliateProfile
            && $profile
                ->hasApprovedSpecialCommission();

        $earnedRewards =
            AffiliateMilestoneReward::query()
                ->with('stage')
                ->where(
                    'affiliate_user_id',
                    $user->getKey()
                )
                ->orderBy(
                    'milestone_sales'
                )
                ->get();

        $earnedByStage =
            $earnedRewards->keyBy(
                'affiliate_stage_id'
            );

        $schedule =
            AffiliateStage::query()
                ->active()
                ->whereNotNull(
                    'milestone_sales'
                )
                ->orderBy(
                    'milestone_sales'
                )
                ->get()
                ->map(
                    function (
                        AffiliateStage $stage
                    ) use (
                        $earnedByStage,
                        $specialRateApplied,
                        $qualifiedSalesCount
                    ): array {
                        $reward =
                            $earnedByStage->get(
                                $stage->getKey()
                            );

                        return [
                            'stage_code' =>
                                $stage->code,

                            'stage_name' =>
                                $stage->name,

                            'sales_target' =>
                                (int) $stage
                                    ->milestone_sales,

                            'gift' =>
                                $this->money(
                                    (int) $stage
                                        ->milestone_gift_cents
                                ),

                            'cumulative_gift' =>
                                $this->money(
                                    (int) $stage
                                        ->cumulative_gift_cents
                                ),

                            'earned' =>
                                $reward
                                instanceof AffiliateMilestoneReward,

                            'reward_uuid' =>
                                $reward?->uuid,

                            'earned_at' =>
                                $reward?->earned_at
                                    ?->toISOString(),

                            'currently_eligible' =>
                                ! $specialRateApplied,

                            'remaining_qualified_sales' =>
                                max(
                                    0,
                                    (int) $stage
                                        ->milestone_sales
                                    - $qualifiedSalesCount
                                ),
                        ];
                    }
                )
                ->values()
                ->all();

        return [
            'eligible' =>
                ! $specialRateApplied,

            'special_exception_applied' =>
                $specialRateApplied,

            'qualified_sales_count' =>
                $qualifiedSalesCount,

            'next' =>
                $specialRateApplied
                    ? null
                    : $this->nextMilestone(
                        $qualifiedSalesCount
                    ),

            'schedule' =>
                $schedule,

            'earned_history' =>
                $earnedRewards
                    ->map(
                        fn (
                            AffiliateMilestoneReward $reward
                        ): array => [
                            'uuid' =>
                                $reward->uuid,

                            'stage_code' =>
                                $reward->stage?->code,

                            'stage_name' =>
                                $reward->stage?->name,

                            'sales_target' =>
                                (int) $reward
                                    ->milestone_sales,

                            'gift' =>
                                $this->money(
                                    (int) $reward
                                        ->gift_amount_cents,
                                    $reward->currency
                                ),

                            'status' =>
                                $reward->status,

                            'earned_at' =>
                                $reward->earned_at
                                    ?->toISOString(),
                        ]
                    )
                    ->values()
                    ->all(),
        ];
    }

    public function shareAction(
        User $user,
        string $channel,
        Request $request
    ): array {
        $this->assertPremium(
            $user
        );

        $channel = strtoupper(
            trim(
                $channel
            )
        );

        $referralUrl =
            $this->referralUrl(
                $user
            );

        $referralCode = trim(
            (string) $user
                ->referral_code
        );

        if (
            $referralUrl === null
            || $referralCode === ''
        ) {
            throw new AuthApiException(
                422,
                'AFFILIATE_REFERRAL_NOT_AVAILABLE',
                'A referral code and referral URL are required before sharing.'
            );
        }

        $message =
            $this->shareMessage(
                $referralCode,
                $referralUrl
            );

        $externalUrl = match (
            $channel
        ) {
            'WHATSAPP' =>
                'https://wa.me/?text='
                .rawurlencode(
                    $message
                ),

            'TELEGRAM' =>
                'https://t.me/share/url?url='
                .rawurlencode(
                    $referralUrl
                )
                .'&text='
                .rawurlencode(
                    $message
                ),

            default =>
                throw new AuthApiException(
                    422,
                    'AFFILIATE_SHARE_CHANNEL_INVALID',
                    'Unsupported affiliate share channel.'
                ),
        };

        AuditLog::record(
            actorType: 'USER',
            actorId: $user->getKey(),
            action: 'AFFILIATE_USER_SHARE_ACTION_PREPARED',
            module: 'AFFILIATES',
            targetType: 'USER',
            targetId: $user->getKey(),
            newValues: [
                'channel' =>
                    $channel,

                'referral_code' =>
                    $referralCode,
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return [
            'channel' =>
                $channel,

            'external_share_url' =>
                $externalUrl,

            'message' =>
                $message,

            'referral_code' =>
                $referralCode,

            'referral_url' =>
                $referralUrl,

            'delivery' => [
                'mode' =>
                    'USER_EXTERNAL_SHARE',

                'server_provider_send_triggered' =>
                    false,

                'delivery_verified_by_tradex' =>
                    false,
            ],
        ];
    }

    private function assertPremium(
        User $user
    ): void {
        if (! $user->canAccessPremium()) {
            throw new AuthApiException(
                403,
                'AFFILIATE_PREMIUM_REQUIRED',
                'Premium access is required to use the Affiliate program.'
            );
        }
    }

    private function profile(
        User $user
    ): ?AffiliateProfile {
        return AffiliateProfile::query()
            ->with(
                'currentStage'
            )
            ->where(
                'user_id',
                $user->getKey()
            )
            ->first();
    }

    private function stageSummary(
        AffiliateStage $stage
    ): array {
        return [
            'code' =>
                $stage->code,

            'name' =>
                $stage->name,

            'qualified_sales_range' => [
                'min' =>
                    (int) $stage
                        ->min_qualified_sales,

                'max' =>
                    $stage->max_qualified_sales
                    !== null
                        ? (int) $stage
                            ->max_qualified_sales
                        : null,
            ],

            'standard_commission_percent' =>
                $this->percentFromBps(
                    (int) $stage
                        ->standard_commission_bps
                ),
        ];
    }

    private function stageDetail(
        AffiliateStage $stage
    ): array {
        return [
            ...$this->stageSummary(
                $stage
            ),

            'milestone' => [
                'sales_target' =>
                    $stage->milestone_sales
                    !== null
                        ? (int) $stage
                            ->milestone_sales
                        : null,

                'gift' =>
                    $this->money(
                        (int) $stage
                            ->milestone_gift_cents
                    ),

                'cumulative_gift' =>
                    $this->money(
                        (int) $stage
                            ->cumulative_gift_cents
                    ),
            ],

            'commercial_rule' =>
                $stage->commercial_rule,
        ];
    }

    private function nextStage(
        int $qualifiedSalesCount
    ): ?array {
        $stage = AffiliateStage::query()
            ->active()
            ->where(
                'min_qualified_sales',
                '>',
                $qualifiedSalesCount
            )
            ->orderBy(
                'min_qualified_sales'
            )
            ->first();

        if (! $stage instanceof AffiliateStage) {
            return null;
        }

        return [
            'code' =>
                $stage->code,

            'name' =>
                $stage->name,

            'starts_at_qualified_sales' =>
                (int) $stage
                    ->min_qualified_sales,

            'remaining_qualified_sales' =>
                max(
                    0,
                    (int) $stage
                        ->min_qualified_sales
                    - $qualifiedSalesCount
                ),
        ];
    }

    private function nextMilestone(
        int $qualifiedSalesCount
    ): ?array {
        $stage = AffiliateStage::query()
            ->active()
            ->whereNotNull(
                'milestone_sales'
            )
            ->where(
                'milestone_sales',
                '>',
                $qualifiedSalesCount
            )
            ->orderBy(
                'milestone_sales'
            )
            ->first();

        if (! $stage instanceof AffiliateStage) {
            return null;
        }

        return [
            'stage_code' =>
                $stage->code,

            'stage_name' =>
                $stage->name,

            'sales_target' =>
                (int) $stage
                    ->milestone_sales,

            'remaining_qualified_sales' =>
                max(
                    0,
                    (int) $stage
                        ->milestone_sales
                    - $qualifiedSalesCount
                ),

            'gift' =>
                $this->money(
                    (int) $stage
                        ->milestone_gift_cents
                ),
        ];
    }

    private function saleSummary(
        AffiliateQualifiedSale $sale
    ): array {
        $commission =
            $sale->commission;

        $adjustmentTotal =
            $commission instanceof AffiliateCommission
                ? (int) $commission
                    ->adjustments
                    ->sum(
                        'amount_cents'
                    )
                : 0;

        $grossCommission =
            $commission instanceof AffiliateCommission
                ? (int) $commission
                    ->commission_amount_cents
                : 0;

        return [
            'uuid' =>
                $sale->uuid,

            'referred_user' =>
                $sale->referredUser
                instanceof User
                    ? [
                        'uuid' =>
                            $sale->referredUser
                                ->uuid,

                        'name' =>
                            $sale->referredUser
                                ->name,
                    ]
                    : null,

            'provider' =>
                $sale->source_provider,

            /*
            | source_reference is deliberately never exposed.
            */

            'ledger_sequence' =>
                (int) $sale
                    ->ledger_sequence,

            'qualified_ordinal' =>
                (int) $sale
                    ->qualified_ordinal,

            'net_eligible_revenue' =>
                $this->money(
                    (int) $sale
                        ->net_eligible_revenue_cents,
                    $sale->currency
                ),

            'status' =>
                $sale->status,

            'qualified_at' =>
                $sale->qualified_at
                    ?->toISOString(),

            'processor_exception' => [
                'recorded' =>
                    $sale
                        ->isPaymentReversed(),

                'code' =>
                    $sale
                        ->isPaymentReversed()
                            ? $sale
                                ->payment_reversal_code
                            : null,

                'recorded_at' =>
                    $sale->payment_reversed_at
                        ?->toISOString(),

                /*
                | payment_reversal_reason is deliberately not exposed.
                */
            ],

            'commission' =>
                $commission
                instanceof AffiliateCommission
                    ? [
                        'uuid' =>
                            $commission->uuid,

                        'rate_percent' =>
                            $this->percentFromBps(
                                (int) $commission
                                    ->rate_bps
                            ),

                        'rate_source' =>
                            $commission
                                ->rate_source,

                        'gross' =>
                            $this->money(
                                $grossCommission,
                                $commission->currency
                            ),

                        'processor_adjustments' =>
                            $this->money(
                                $adjustmentTotal,
                                $commission->currency
                            ),

                        'net' =>
                            $this->money(
                                $grossCommission
                                + $adjustmentTotal,
                                $commission->currency
                            ),
                    ]
                    : null,
        ];
    }

    private function commissionSummary(
        AffiliateCommission $commission
    ): array {
        $sale =
            $commission->qualifiedSale;

        $adjustmentTotal =
            (int) $commission
                ->adjustments
                ->sum(
                    'amount_cents'
                );

        return [
            'uuid' =>
                $commission->uuid,

            'qualified_ordinal' =>
                (int) $commission
                    ->qualified_ordinal,

            'rate_percent' =>
                $this->percentFromBps(
                    (int) $commission
                        ->rate_bps
                ),

            'rate_source' =>
                $commission->rate_source,

            'calculation_base' =>
                $this->money(
                    (int) $commission
                        ->calculation_base_cents,
                    $commission->currency
                ),

            'gross_commission' =>
                $this->money(
                    (int) $commission
                        ->commission_amount_cents,
                    $commission->currency
                ),

            'processor_adjustments' =>
                $this->money(
                    $adjustmentTotal,
                    $commission->currency
                ),

            'net_commission' =>
                $this->money(
                    (int) $commission
                        ->commission_amount_cents
                    + $adjustmentTotal,
                    $commission->currency
                ),

            'status' =>
                $commission->status,

            'earned_at' =>
                $commission->earned_at
                    ?->toISOString(),

            'qualified_sale' =>
                $sale
                instanceof AffiliateQualifiedSale
                    ? [
                        'uuid' =>
                            $sale->uuid,

                        'status' =>
                            $sale->status,

                        'referred_user' =>
                            $sale->referredUser
                            instanceof User
                                ? [
                                    'uuid' =>
                                        $sale
                                            ->referredUser
                                            ->uuid,

                                    'name' =>
                                        $sale
                                            ->referredUser
                                            ->name,
                                ]
                                : null,
                    ]
                    : null,
        ];
    }

    private function referralUrl(
        User $user
    ): ?string {
        $base = rtrim(
            trim(
                (string) config(
                    'tradex_affiliate_api.referral_base_url',
                    config(
                        'app.url'
                    )
                )
            ),
            '?&'
        );

        $code = trim(
            (string) $user
                ->referral_code
        );

        if (
            $base === ''
            || $code === ''
        ) {
            return null;
        }

        $separator =
            str_contains(
                $base,
                '?'
            )
                ? '&'
                : '?';

        return $base
            .$separator
            .'ref='
            .rawurlencode(
                $code
            );
    }

    private function shareMessage(
        string $referralCode,
        string $referralUrl
    ): string {
        return implode(
            "\n",
            [
                'TradeX OS — The Operating System for Serious Traders',
                'Learn professional trading with discipline and risk management.',
                'Referral code: '.$referralCode,
                $referralUrl,
            ]
        );
    }

    private function money(
        int $amountCents,
        string $currency = self::CURRENCY_USD
    ): array {
        return [
            'currency' =>
                strtoupper(
                    $currency
                ),

            'amount_cents' =>
                $amountCents,

            'amount' =>
                number_format(
                    $amountCents / 100,
                    2,
                    '.',
                    ''
                ),
        ];
    }

    private function percentFromBps(
        ?int $bps
    ): ?string {
        if ($bps === null) {
            return null;
        }

        return number_format(
            $bps / 100,
            2,
            '.',
            ''
        );
    }

    private function perPage(
        array $filters
    ): int {
        return max(
            1,
            min(
                (int) (
                    $filters['per_page']
                    ?? config(
                        'tradex_affiliate_api.default_per_page',
                        20
                    )
                ),
                max(
                    1,
                    (int) config(
                        'tradex_affiliate_api.max_per_page',
                        50
                    )
                )
            )
        );
    }

    private function pagination(
        LengthAwarePaginator $paginator
    ): array {
        return [
            'current_page' =>
                $paginator->currentPage(),

            'last_page' =>
                $paginator->lastPage(),

            'per_page' =>
                $paginator->perPage(),

            'total' =>
                $paginator->total(),
        ];
    }
}
