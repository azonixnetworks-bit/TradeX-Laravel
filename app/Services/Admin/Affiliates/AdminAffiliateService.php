<?php

namespace App\Services\Admin\Affiliates;

use App\Models\AdminUser;
use App\Models\AffiliateCommission;
use App\Models\AffiliateProfile;
use App\Models\AffiliateQualifiedSale;
use App\Models\AffiliateStage;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Affiliates\AffiliateCommercialService;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminAffiliateService
{
    public function __construct(
        private readonly AffiliateCommercialService $commercialService
    ) {
    }

    public function dashboard(array $filters): array
    {
        $query = $this->affiliateUserQuery();

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('users.name', 'like', '%'.$search.'%')
                    ->orWhere('users.email', 'like', '%'.$search.'%')
                    ->orWhere('users.referral_code', 'like', '%'.$search.'%');
            });
        }

        $stageCode = trim((string) ($filters['stage'] ?? ''));
        if ($stageCode !== '') {
            $stage = AffiliateStage::query()
                ->where('code', $stageCode)
                ->first();

            if ($stage instanceof AffiliateStage) {
                $query->whereIn(
                    'users.id',
                    AffiliateProfile::query()
                        ->where('current_stage_id', $stage->getKey())
                        ->select('user_id')
                );
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ((string) ($filters['special'] ?? '') === '1') {
            $query->whereIn(
                'users.id',
                AffiliateProfile::query()
                    ->whereNotNull('special_commission_bps')
                    ->whereNotNull('special_commission_approved_at')
                    ->select('user_id')
            );
        }

        $sort = (string) ($filters['sort'] ?? 'qualified_desc');

        match ($sort) {
            'name_asc' => $query->orderBy('users.name'),
            'name_desc' => $query->orderByDesc('users.name'),
            'newest' => $query->orderByDesc('users.created_at'),
            default => $query
                ->orderByDesc('qualified_sales_count')
                ->orderByDesc('users.id'),
        };

        $perPage = $this->perPage($filters);

        $affiliates = $query
            ->paginate($perPage)
            ->withQueryString();

        $stageMap = AffiliateStage::query()
            ->get()
            ->keyBy('id');

        return [
            'affiliates' => $affiliates,
            'stageMap' => $stageMap,
            'stages' => AffiliateStage::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'stats' => $this->stats(),
        ];
    }

    public function affiliateDetail(User $affiliate): array
    {
        $this->assertAffiliate($affiliate);

        $profile = AffiliateProfile::query()
            ->with([
                'currentStage',
                'specialCommissionApprovedByAdmin:id,name,email',
            ])
            ->where('user_id', $affiliate->getKey())
            ->first();

        $directReferrals = User::query()
            ->where('referred_by_user_id', $affiliate->getKey())
            ->orderByDesc('created_at')
            ->limit(25)
            ->get([
                'id',
                'uuid',
                'name',
                'email',
                'account_type',
                'status',
                'created_at',
            ]);

        $sales = AffiliateQualifiedSale::query()
            ->with([
                'referredUser:id,uuid,name,email',
                'commission.adjustments',
            ])
            ->where('affiliate_user_id', $affiliate->getKey())
            ->orderByDesc('qualified_at')
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        $commissions = AffiliateCommission::query()
            ->with([
                'qualifiedSale.referredUser:id,uuid,name,email',
                'adjustments',
            ])
            ->where('affiliate_user_id', $affiliate->getKey())
            ->orderByDesc('earned_at')
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        return compact(
            'profile',
            'directReferrals',
            'sales',
            'commissions'
        );
    }

    public function directReferralTree(
        User $affiliate,
        array $filters
    ): array {
        $this->assertAffiliate($affiliate);

        $query = User::query()
            ->where('referred_by_user_id', $affiliate->getKey());

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('referral_code', 'like', '%'.$search.'%');
            });
        }

        $referrals = $query
            ->orderByDesc('created_at')
            ->paginate($this->perPage($filters))
            ->withQueryString();

        $referralIds = collect($referrals->items())
            ->pluck('id')
            ->all();

        $qualifiedByReferral = $referralIds === []
            ? collect()
            : AffiliateQualifiedSale::query()
                ->where('affiliate_user_id', $affiliate->getKey())
                ->whereIn('referred_user_id', $referralIds)
                ->selectRaw(
                    'referred_user_id, '
                    .'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS cleared_count, '
                    .'COUNT(*) AS lifetime_count, '
                    .'MAX(qualified_at) AS latest_qualified_at',
                    [AffiliateQualifiedSale::STATUS_CLEARED]
                )
                ->groupBy('referred_user_id')
                ->get()
                ->keyBy('referred_user_id');

        return [
            'referrals' => $referrals,
            'qualifiedByReferral' => $qualifiedByReferral,
            'scope' => 'DIRECT_REFERRALS_ONLY',
        ];
    }

    public function commissionLedger(array $filters): array
    {
        $query = AffiliateCommission::query()
            ->with([
                'affiliate:id,uuid,name,email,referral_code',
                'qualifiedSale.referredUser:id,uuid,name,email',
                'adjustments.createdByAdmin:id,name,email',
            ]);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->whereHas(
                'affiliate',
                function (Builder $builder) use ($search): void {
                    $builder
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('referral_code', 'like', '%'.$search.'%');
                }
            );
        }

        $rateSource = trim((string) ($filters['rate_source'] ?? ''));
        if (in_array(
            $rateSource,
            [
                AffiliateCommission::RATE_SOURCE_STANDARD,
                AffiliateCommission::RATE_SOURCE_SPECIAL,
            ],
            true
        )) {
            $query->where('rate_source', $rateSource);
        }

        $from = trim((string) ($filters['from'] ?? ''));
        if ($from !== '') {
            $query->whereDate('earned_at', '>=', $from);
        }

        $to = trim((string) ($filters['to'] ?? ''));
        if ($to !== '') {
            $query->whereDate('earned_at', '<=', $to);
        }

        $commissions = $query
            ->orderByDesc('earned_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($filters))
            ->withQueryString();

        $grossEarnedCents = (int) AffiliateCommission::query()
            ->sum('commission_amount_cents');

        $adjustmentCents = (int) AffiliateProfile::query()
            ->sum('commission_adjustment_cents');

        return [
            'commissions' => $commissions,
            'stats' => [
                'entries' => AffiliateCommission::query()->count(),
                'gross_earned_cents' => $grossEarnedCents,
                'adjustment_cents' => $adjustmentCents,
                'net_earned_cents' => $grossEarnedCents + $adjustmentCents,
                'special_entries' => AffiliateCommission::query()
                    ->where('rate_source', AffiliateCommission::RATE_SOURCE_SPECIAL)
                    ->count(),
            ],
        ];
    }

    public function stages(): Collection
    {
        return AffiliateStage::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function approveSpecialCommission(
        User $affiliate,
        float $ratePercent,
        AdminUser $admin,
        string $reason
    ): AffiliateProfile {
        $this->assertAffiliateCandidate($affiliate);

        $rateBps = (int) round($ratePercent * 100);

        return $this->commercialService
            ->approveSpecialCommissionRate(
                $affiliate,
                $rateBps,
                $admin,
                $reason
            );
    }

    public function clearSpecialCommission(
        User $affiliate,
        AdminUser $admin,
        Request $request
    ): AffiliateProfile {
        $this->assertAffiliateCandidate($affiliate);

        return DB::transaction(function () use (
            $affiliate,
            $admin,
            $request
        ): AffiliateProfile {
            $profile = AffiliateProfile::query()
                ->where('user_id', $affiliate->getKey())
                ->lockForUpdate()
                ->first();

            if (! $profile instanceof AffiliateProfile
                || ! $profile->hasApprovedSpecialCommission()) {
                throw new DomainException(
                    'This affiliate does not have an approved special commission rate.'
                );
            }

            $before = $this->specialCommissionSnapshot($profile);

            $profile->forceFill([
                'special_commission_bps' => null,
                'special_commission_approved_at' => null,
                'special_commission_approved_by_admin_id' => null,
                'special_commission_reason' => null,
            ])->save();

            AuditLog::record(
                actorType: 'ADMIN',
                actorId: $admin->getKey(),
                action: 'AFFILIATE_SPECIAL_COMMISSION_CLEARED',
                module: 'AFFILIATES',
                targetType: 'AFFILIATE_PROFILE',
                targetId: $profile->getKey(),
                oldValues: $before,
                newValues: $this->specialCommissionSnapshot($profile),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            return $profile->fresh();
        }, 3);
    }

    public function updateStage(
        AffiliateStage $stage,
        array $data,
        AdminUser $admin,
        Request $request
    ): AffiliateStage {
        $rateBps = (int) round(((float) $data['standard_commission_percent']) * 100);
        $giftCents = (int) round(((float) $data['milestone_gift_usd']) * 100);

        if ($rateBps > AffiliateCommercialService::STANDARD_MAX_COMMISSION_BPS) {
            throw new DomainException(
                'Standard affiliate commission cannot exceed 20%.'
            );
        }

        return DB::transaction(function () use (
            $stage,
            $data,
            $rateBps,
            $giftCents,
            $admin,
            $request
        ): AffiliateStage {
            $locked = AffiliateStage::query()
                ->whereKey($stage->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $before = $this->stageSnapshot($locked);

            $locked->forceFill([
                'name' => trim((string) $data['name']),
                'standard_commission_bps' => $rateBps,
                'milestone_gift_cents' => $giftCents,
                'commercial_rule' => trim((string) $data['commercial_rule']),
            ])->save();

            $runningCumulative = 0;

            AffiliateStage::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->each(function (AffiliateStage $row) use (&$runningCumulative): void {
                    $runningCumulative += (int) $row->milestone_gift_cents;

                    if ((int) $row->cumulative_gift_cents !== $runningCumulative) {
                        $row->forceFill([
                            'cumulative_gift_cents' => $runningCumulative,
                        ])->save();
                    }
                });

            $locked = $locked->fresh();

            AuditLog::record(
                actorType: 'ADMIN',
                actorId: $admin->getKey(),
                action: 'AFFILIATE_STAGE_COMMERCIAL_UPDATED',
                module: 'AFFILIATES',
                targetType: 'AFFILIATE_STAGE',
                targetId: $locked->getKey(),
                oldValues: $before,
                newValues: $this->stageSnapshot($locked),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            return $locked;
        }, 3);
    }

    public function assertAffiliate(User $affiliate): void
    {
        if (! $this->isAffiliate($affiliate)) {
            abort(404);
        }
    }

    public function isAffiliate(User $affiliate): bool
    {
        return AffiliateProfile::query()
            ->where('user_id', $affiliate->getKey())
            ->exists()
            || User::query()
                ->where('referred_by_user_id', $affiliate->getKey())
                ->exists();
    }

    private function assertAffiliateCandidate(User $affiliate): void
    {
        abort_if($affiliate->trashed(), 404);
    }

    private function affiliateUserQuery(): Builder
    {
        return User::query()
            ->where(function (Builder $builder): void {
                $builder
                    ->whereHas('referrals')
                    ->orWhereIn(
                        'users.id',
                        AffiliateProfile::query()->select('user_id')
                    );
            })
            ->withCount('referrals')
            ->addSelect([
                'affiliate_profile_id' => AffiliateProfile::query()
                    ->select('id')
                    ->whereColumn('affiliate_profiles.user_id', 'users.id')
                    ->limit(1),
                'current_stage_id' => AffiliateProfile::query()
                    ->select('current_stage_id')
                    ->whereColumn('affiliate_profiles.user_id', 'users.id')
                    ->limit(1),
                'qualified_sales_count' => AffiliateProfile::query()
                    ->select('qualified_sales_count')
                    ->whereColumn('affiliate_profiles.user_id', 'users.id')
                    ->limit(1),
                'lifetime_commission_earned_cents' => AffiliateProfile::query()
                    ->select('lifetime_commission_earned_cents')
                    ->whereColumn('affiliate_profiles.user_id', 'users.id')
                    ->limit(1),
                'commission_adjustment_cents' => AffiliateProfile::query()
                    ->select('commission_adjustment_cents')
                    ->whereColumn('affiliate_profiles.user_id', 'users.id')
                    ->limit(1),
                'milestone_gifts_earned_cents' => AffiliateProfile::query()
                    ->select('milestone_gifts_earned_cents')
                    ->whereColumn('affiliate_profiles.user_id', 'users.id')
                    ->limit(1),
                'special_commission_bps' => AffiliateProfile::query()
                    ->select('special_commission_bps')
                    ->whereColumn('affiliate_profiles.user_id', 'users.id')
                    ->limit(1),
            ]);
    }

    private function stats(): array
    {
        $profileStats = AffiliateProfile::query()
            ->selectRaw(
                'COUNT(*) AS profiles_count, '
                .'COALESCE(SUM(qualified_sales_count), 0) AS qualified_sales_count, '
                .'COALESCE(SUM(lifetime_commission_earned_cents), 0) AS commission_cents, '
                .'COALESCE(SUM(commission_adjustment_cents), 0) AS adjustment_cents, '
                .'COALESCE(SUM(milestone_gifts_earned_cents), 0) AS milestone_cents, '
                .'SUM(CASE WHEN special_commission_bps IS NOT NULL '
                .'AND special_commission_approved_at IS NOT NULL THEN 1 ELSE 0 END) AS special_count'
            )
            ->first();

        return [
            'affiliates' => $this->affiliateUserQuery()->count(),
            'profiles' => (int) ($profileStats?->profiles_count ?? 0),
            'qualified_sales' => (int) ($profileStats?->qualified_sales_count ?? 0),
            'special_rates' => (int) ($profileStats?->special_count ?? 0),
            'net_commission_cents' =>
                (int) ($profileStats?->commission_cents ?? 0)
                + (int) ($profileStats?->adjustment_cents ?? 0),
            'milestone_gifts_cents' => (int) ($profileStats?->milestone_cents ?? 0),
        ];
    }

    private function perPage(array $filters): int
    {
        $value = (int) ($filters['per_page'] ?? 20);

        return in_array($value, [10, 20, 50, 100], true)
            ? $value
            : 20;
    }

    private function specialCommissionSnapshot(AffiliateProfile $profile): array
    {
        return [
            'special_commission_bps' => $profile->special_commission_bps,
            'special_commission_approved_at' =>
                $profile->special_commission_approved_at?->toISOString(),
            'special_commission_approved_by_admin_id' =>
                $profile->special_commission_approved_by_admin_id,
            'special_commission_reason' => $profile->special_commission_reason,
        ];
    }

    private function stageSnapshot(AffiliateStage $stage): array
    {
        return [
            'code' => $stage->code,
            'name' => $stage->name,
            'min_qualified_sales' => (int) $stage->min_qualified_sales,
            'max_qualified_sales' => $stage->max_qualified_sales,
            'standard_commission_bps' => (int) $stage->standard_commission_bps,
            'milestone_sales' => $stage->milestone_sales,
            'milestone_gift_cents' => (int) $stage->milestone_gift_cents,
            'cumulative_gift_cents' => (int) $stage->cumulative_gift_cents,
            'commercial_rule' => $stage->commercial_rule,
            'sort_order' => (int) $stage->sort_order,
            'is_active' => (bool) $stage->is_active,
        ];
    }
}
