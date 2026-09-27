<?php

namespace App\Services\Dashboard;

use App\Models\AffiliateProfile;
use App\Models\AffiliateStage;
use App\Models\ChannelVideo;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\TradeIdea;
use App\Models\User;
use App\Models\UserLessonProgress;
use App\Services\OurChannel\OurChannelApiService;
use App\Services\TradeIdeas\TradeIdeaApiService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class DashboardApiService
{
    private const CURRENCY_USD = 'USD';

    public function __construct(
        private readonly TradeIdeaApiService
            $tradeIdeas,
        private readonly OurChannelApiService
            $ourChannel
    ) {
    }

    public function dashboard(
        User $user
    ): array {
        $ttlSeconds = max(
            0,
            (int) config(
                'tradex.performance.dashboard_cache_seconds',
                10
            )
        );

        if ($ttlSeconds === 0) {
            return $this->buildDashboard(
                $user
            );
        }

        return Cache::remember(
            $this->dashboardCacheKey($user),
            now()->addSeconds($ttlSeconds),
            fn (): array => $this->buildDashboard($user)
        );
    }

    private function buildDashboard(
        User $user
    ): array {
        return [
            'account' =>
                $this->accountSummary(
                    $user
                ),

            'referral' =>
                $this->referralSummary(
                    $user
                ),

            'affiliate' =>
                $this->affiliateSummary(
                    $user
                ),

            'learning' =>
                $this->learningSummary(
                    $user
                ),

            'trade_ideas' =>
                $this->tradeIdeaSummary(
                    $user
                ),

            'our_channel' =>
                $this->ourChannelSummary(
                    $user
                ),
        ];
    }

    private function dashboardCacheKey(
        User $user
    ): string {
        return implode(':', [
            'tradex',
            'dashboard',
            'v1',
            (string) $user->getKey(),
            (string) ($user->updated_at?->getTimestamp() ?? 0),
            (string) $user->account_type,
            (string) $user->language_code,
        ]);
    }

    private function accountSummary(
        User $user
    ): array {
        return [
            'uuid' =>
                $user->uuid,

            'name' =>
                $user->name,

            'account_type' =>
                $user->account_type,

            'status' =>
                $user->status,

            'language_code' =>
                $user->language_code,

            'country_code' =>
                $user->country_code,

            'premium_access' =>
                $user->canAccessPremium(),
        ];
    }

    private function referralSummary(
        User $user
    ): array {
        return [
            'code' =>
                $user->referral_code,

            'url' =>
                $this->referralUrl(
                    $user
                ),

            'direct_referrals_count' =>
                $user->referrals()
                    ->count(),
        ];
    }

    private function affiliateSummary(
        User $user
    ): array {
        $profile = AffiliateProfile::query()
            ->with(
                'currentStage'
            )
            ->where(
                'user_id',
                $user->getKey()
            )
            ->first();

        $qualifiedSalesCount =
            $profile instanceof AffiliateProfile
                ? (int) $profile
                    ->qualified_sales_count
                : 0;

        $specialRateApplied =
            $profile instanceof AffiliateProfile
            && $profile
                ->hasApprovedSpecialCommission();

        $currentStage =
            $profile?->currentStage;

        $nextSaleStage =
            AffiliateStage::forQualifiedSalesCount(
                $qualifiedSalesCount + 1
            );

        $nextSaleRateBps =
            $specialRateApplied
                ? (int) $profile
                    ->special_commission_bps
                : (
                    $nextSaleStage instanceof AffiliateStage
                        ? (int) $nextSaleStage
                            ->standard_commission_bps
                        : null
                );

        $commissionEarnedCents =
            $profile instanceof AffiliateProfile
                ? (int) $profile
                    ->lifetime_commission_earned_cents
                : 0;

        $processorAdjustmentCents =
            $profile instanceof AffiliateProfile
                ? (int) $profile
                    ->commission_adjustment_cents
                : 0;

        $netCommissionCents =
            $commissionEarnedCents
            + $processorAdjustmentCents;

        $milestoneGiftCents =
            $profile instanceof AffiliateProfile
                ? (int) $profile
                    ->milestone_gifts_earned_cents
                : 0;

        return [
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

            'commission' => [
                'model' =>
                    'PROGRESSIVE_DIRECT_SALE',

                'special_exception_applied' =>
                    $specialRateApplied,

                'next_sale_rate_percent' =>
                    $this->percentFromBps(
                        $nextSaleRateBps
                    ),

                'normal_standard_ceiling_percent' =>
                    '20.00',

                'approved_special_ceiling_percent' =>
                    '24.00',
            ],

            'milestones' => [
                'eligible' =>
                    ! $specialRateApplied,

                'next' =>
                    $specialRateApplied
                        ? null
                        : $this
                            ->nextMilestone(
                                $qualifiedSalesCount
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
                        $commissionEarnedCents
                    ),

                /*
                | No voluntary refund concept is exposed by API-08.
                | This value represents only externally forced payment-system /
                | legal / duplicate / unauthorized / confirmed billing exception
                | adjustments already recorded by the AFF-01 immutable ledger.
                */
                'processor_payment_adjustments' =>
                    $this->money(
                        $processorAdjustmentCents
                    ),

                'net_commission' =>
                    $this->money(
                        $netCommissionCents
                    ),

                'milestone_gifts_earned' =>
                    $this->money(
                        $milestoneGiftCents
                    ),

                'total_affiliate_rewards' =>
                    $this->money(
                        $netCommissionCents
                        + $milestoneGiftCents
                    ),
            ],
        ];
    }

    private function learningSummary(
        User $user
    ): array {
        $accessibleLessons = $this
            ->accessiblePublishedLessons(
                $user
            )
            ->select('lessons.id');

        $total = (clone $accessibleLessons)
            ->count();

        $completed = 0;
        $inProgress = 0;
        $continueProgress = null;

        if ($total > 0) {
            $statusCounts = UserLessonProgress::query()
                ->select('status')
                ->selectRaw('COUNT(*) as aggregate')
                ->where(
                    'user_id',
                    $user->getKey()
                )
                ->whereIn(
                    'lesson_id',
                    clone $accessibleLessons
                )
                ->whereIn(
                    'status',
                    [
                        UserLessonProgress::STATUS_COMPLETED,
                        UserLessonProgress::STATUS_IN_PROGRESS,
                    ]
                )
                ->groupBy('status')
                ->pluck('aggregate', 'status');

            $completed = (int) $statusCounts->get(
                UserLessonProgress::STATUS_COMPLETED,
                0
            );

            $inProgress = (int) $statusCounts->get(
                UserLessonProgress::STATUS_IN_PROGRESS,
                0
            );

            if ($inProgress > 0) {
                $continueProgress = UserLessonProgress::query()
                    ->with([
                        'lesson.module.course',
                        'lastVideo',
                    ])
                    ->where(
                        'user_id',
                        $user->getKey()
                    )
                    ->whereIn(
                        'lesson_id',
                        clone $accessibleLessons
                    )
                    ->where(
                        'status',
                        UserLessonProgress::STATUS_IN_PROGRESS
                    )
                    ->orderByDesc(
                        'last_activity_at'
                    )
                    ->orderByDesc(
                        'id'
                    )
                    ->first();
            }
        }

        return [
            'accessible_published_lessons' =>
                $total,

            'completed_lessons' =>
                $completed,

            'in_progress_lessons' =>
                $inProgress,

            'completion_percent' =>
                $total === 0
                    ? 0
                    : (int) round(
                        ($completed / $total)
                        * 100
                    ),

            'continue_learning' =>
                $continueProgress
                instanceof UserLessonProgress
                    ? $this
                        ->continueLearningSummary(
                            $continueProgress
                        )
                    : null,
        ];
    }

    private function tradeIdeaSummary(
        User $user
    ): array {
        if (! $user->canAccessPremium()) {
            return [
                'available' => false,
                'premium_required' => true,
                'latest' => null,
            ];
        }

        $tradeIdea = TradeIdea::query()
            ->whereIn(
                'status',
                [
                    TradeIdea::STATUS_PUBLISHED,
                    TradeIdea::STATUS_CLOSED,
                ]
            )
            ->whereNotNull(
                'published_at'
            )
            ->where(
                'published_at',
                '<=',
                now()
            )
            ->orderByDesc(
                'published_at'
            )
            ->orderByDesc(
                'id'
            )
            ->first();

        return [
            'available' => true,
            'premium_required' => false,
            'latest' =>
                $tradeIdea instanceof TradeIdea
                    ? [
                        ...$this->tradeIdeas
                            ->summary(
                                $tradeIdea
                            ),
                        'detail_url' =>
                            route(
                                'api.v1.trade-ideas.show',
                                [
                                    'tradeIdea' =>
                                        $tradeIdea->uuid,
                                ]
                            ),
                    ]
                    : null,
        ];
    }

    private function ourChannelSummary(
        User $user
    ): array {
        $preferredLanguage = trim(
            (string) $user->language_code
        );

        $video = null;
        $matchedPreferredLanguage = false;

        if ($preferredLanguage !== '') {
            $video = ChannelVideo::query()
                ->published()
                ->where(
                    'language_code',
                    $preferredLanguage
                )
                ->orderByDesc(
                    'published_at'
                )
                ->orderByDesc(
                    'id'
                )
                ->first();

            $matchedPreferredLanguage =
                $video instanceof ChannelVideo;
        }

        if (! $video instanceof ChannelVideo) {
            $video = ChannelVideo::query()
                ->published()
                ->orderByDesc(
                    'published_at'
                )
                ->orderByDesc(
                    'id'
                )
                ->first();
        }

        return [
            'preferred_language_code' =>
                $preferredLanguage !== ''
                    ? $preferredLanguage
                    : null,

            'matched_preferred_language' =>
                $matchedPreferredLanguage,

            'latest_video' =>
                $video instanceof ChannelVideo
                    ? [
                        ...$this->ourChannel
                            ->summary(
                                $video
                            ),
                        'detail_url' =>
                            route(
                                'api.v1.our-channel.videos.show',
                                [
                                    'channelVideo' =>
                                        $video->uuid,
                                ]
                            ),
                    ]
                    : null,
        ];
    }

    private function accessiblePublishedLessons(
        User $user
    ): Builder {
        $query = Lesson::query()
            ->published()
            ->whereHas(
                'module',
                fn (Builder $moduleQuery): Builder =>
                    $moduleQuery
                        ->published()
                        ->whereHas(
                            'course',
                            fn (Builder $courseQuery): Builder =>
                                $courseQuery
                                    ->published()
                        )
            );

        if (! $user->canAccessPremium()) {
            $query->where(
                function (Builder $accessQuery): void {
                    $accessQuery
                        ->where(
                            'is_preview',
                            true
                        )
                        ->orWhereHas(
                            'module.course',
                            fn (Builder $courseQuery): Builder =>
                                $courseQuery
                                    ->where(
                                        'access_level',
                                        Course::ACCESS_FREE
                                    )
                        );
                }
            );
        }

        return $query;
    }

    private function continueLearningSummary(
        UserLessonProgress $progress
    ): ?array {
        $lesson = $progress->lesson;
        $module = $lesson?->module;
        $course = $module?->course;

        if (
            ! $lesson instanceof Lesson
            || $module === null
            || ! $course instanceof Course
        ) {
            return null;
        }

        return [
            'course' => [
                'uuid' =>
                    $course->uuid,
                'title' =>
                    $course->title,
            ],

            'module' => [
                'uuid' =>
                    $module->uuid,
                'title' =>
                    $module->title,
            ],

            'lesson' => [
                'uuid' =>
                    $lesson->uuid,
                'title' =>
                    $lesson->title,
                'is_preview' =>
                    (bool) $lesson->is_preview,
            ],

            'last_video_uuid' =>
                $progress->lastVideo?->uuid,

            'last_position_seconds' =>
                (int) $progress
                    ->last_position_seconds,

            'last_activity_at' =>
                $progress->last_activity_at
                    ?->toISOString(),

            'lesson_url' =>
                route(
                    'api.v1.education.lessons.show',
                    [
                        'lesson' =>
                            $lesson->uuid,
                    ]
                ),
        ];
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

    private function referralUrl(
        User $user
    ): ?string {
        $base = trim(
            (string) config(
                'tradex_trade_ideas.share.affiliate_base_url',
                config(
                    'app.url'
                )
            )
        );

        $referralCode = trim(
            (string) $user->referral_code
        );

        if (
            $base === ''
            || $referralCode === ''
        ) {
            return null;
        }

        $separator = str_contains(
            $base,
            '?'
        )
            ? '&'
            : '?';

        return $base
            .$separator
            .'ref='
            .rawurlencode(
                $referralCode
            );
    }

    private function percentFromBps(
        ?int $basisPoints
    ): ?string {
        if ($basisPoints === null) {
            return null;
        }

        return number_format(
            $basisPoints / 100,
            2,
            '.',
            ''
        );
    }

    private function money(
        int $amountCents
    ): array {
        return [
            'currency' =>
                self::CURRENCY_USD,

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
}
