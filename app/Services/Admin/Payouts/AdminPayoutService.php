<?php

namespace App\Services\Admin\Payouts;

use App\Models\AdminUser;
use App\Models\AffiliateCommission;
use App\Models\AffiliateCommissionAdjustment;
use App\Models\AuditLog;
use App\Models\PayoutAllocation;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Services\Payouts\PayoutBalanceService;
use App\Services\Payouts\PayoutDestinationService;
use App\Services\Payouts\PayoutMoney;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AdminPayoutService
{
    public function __construct(
        private readonly PayoutBalanceService $balances,
        private readonly PayoutDestinationService $destinations,
        private readonly PayoutMoney $money
    ) {
    }

    public function dashboard(array $filters): array
    {
        $query = PayoutRequest::query()
            ->with('user')
            ->orderByDesc('requested_at')
            ->orderByDesc('id');

        $this->applyFilters($query, $filters);

        $defaultPerPage = max(
            1,
            (int) config('tradex_payouts.pagination.default_per_page', 20)
        );

        $maxPerPage = max(
            $defaultPerPage,
            (int) config('tradex_payouts.pagination.max_per_page', 50)
        );

        $perPage = min(
            $maxPerPage,
            max(
                1,
                (int) ($filters['per_page'] ?? $defaultPerPage)
            )
        );

        /** @var LengthAwarePaginator $payouts */
        $payouts = $query
            ->paginate($perPage)
            ->withQueryString();

        return [
            'payouts' => $payouts,
            'stats' => [
                'all' => PayoutRequest::query()->count(),
                'pending' => PayoutRequest::query()
                    ->where('status', PayoutRequest::STATUS_PENDING_REVIEW)
                    ->count(),
                'approved' => PayoutRequest::query()
                    ->where('status', PayoutRequest::STATUS_APPROVED)
                    ->count(),
                'paid' => PayoutRequest::query()
                    ->where('status', PayoutRequest::STATUS_PAID)
                    ->count(),
                'rejected' => PayoutRequest::query()
                    ->where('status', PayoutRequest::STATUS_REJECTED)
                    ->count(),
                'pending_cents' => (int) PayoutRequest::query()
                    ->where('status', PayoutRequest::STATUS_PENDING_REVIEW)
                    ->sum('amount_cents'),
                'paid_cents' => (int) PayoutRequest::query()
                    ->where('status', PayoutRequest::STATUS_PAID)
                    ->sum('amount_cents'),
            ],
            'money' => $this->money,
        ];
    }

    public function detail(
        PayoutRequest $payout
    ): array {
        $payout->load([
            'user',
            'reviewedByAdmin',
            'allocations.commission.qualifiedSale',
            'allocations.commission.adjustments',
        ]);

        if (! $payout->user instanceof User) {
            throw new DomainException(
                'The payout owner could not be resolved.'
            );
        }

        return [
            'payout' => $payout,
            'balance' => $this->balances
                ->summaryCents($payout->user),
            'destination' => $this->destinations
                ->masked((array) $payout->destination_snapshot),
            'money' => $this->money,
        ];
    }

    public function sensitiveDestination(
        PayoutRequest $payout,
        AdminUser $admin,
        Request $request
    ): array {
        $payout->load('user');

        $snapshot = (array) $payout->destination_snapshot;

        $this->assertDestinationIntegrity(
            $payout,
            $snapshot
        );

        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->getKey(),
            action: 'PAYOUT_DESTINATION_ACCESSED',
            module: 'PAYOUTS',
            targetType: 'PAYOUT_REQUEST',
            targetId: $payout->getKey(),
            newValues: [
                'payout_uuid' => $payout->uuid,
                'method' => $payout->method,
                'sensitive_destination_accessed' => true,
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return [
            'payout' => $payout,
            'destination' => $snapshot,
        ];
    }

    public function approve(
        PayoutRequest $payout,
        AdminUser $admin,
        string $reason,
        ?string $internalNote,
        Request $request
    ): PayoutRequest {
        return DB::transaction(
            function () use (
                $payout,
                $admin,
                $reason,
                $internalNote,
                $request
            ): PayoutRequest {
                $locked = $this->lockPayoutAndUser(
                    $payout
                );

                if (
                    $locked->status
                    !== PayoutRequest::STATUS_PENDING_REVIEW
                ) {
                    throw new DomainException(
                        'Only a payout waiting for review can be approved.'
                    );
                }

                $this->assertDestinationIntegrity(
                    $locked,
                    (array) $locked->destination_snapshot
                );

                $this->assertAllocationIntegrity(
                    $locked
                );

                $oldStatus = $locked->status;

                $locked->forceFill([
                    'status' => PayoutRequest::STATUS_APPROVED,
                    'approved_at' => now(),
                    'reviewed_by_admin_id' => $admin->getKey(),
                    'internal_notes' => $this->appendNote(
                        $locked->internal_notes,
                        'APPROVAL',
                        $reason,
                        $internalNote
                    ),
                ])->save();

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId: $admin->getKey(),
                    action: 'PAYOUT_APPROVED',
                    module: 'PAYOUTS',
                    targetType: 'PAYOUT_REQUEST',
                    targetId: $locked->getKey(),
                    oldValues: [
                        'status' => $oldStatus,
                    ],
                    newValues: [
                        'payout_uuid' => $locked->uuid,
                        'status' => PayoutRequest::STATUS_APPROVED,
                        'method' => $locked->method,
                        'amount_cents' => (int) $locked->amount_cents,
                        'currency' => $locked->currency,
                        'review_note_recorded' => true,
                    ],
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );

                return $locked->fresh([
                    'user',
                    'allocations',
                ]);
            },
            3
        );
    }

    public function reject(
        PayoutRequest $payout,
        AdminUser $admin,
        string $reason,
        ?string $internalNote,
        Request $request
    ): PayoutRequest {
        return DB::transaction(
            function () use (
                $payout,
                $admin,
                $reason,
                $internalNote,
                $request
            ): PayoutRequest {
                $locked = $this->lockPayoutAndUser(
                    $payout
                );

                if (
                    $locked->status
                    !== PayoutRequest::STATUS_PENDING_REVIEW
                ) {
                    throw new DomainException(
                        'Only a payout waiting for review can be rejected.'
                    );
                }

                $oldStatus = $locked->status;

                $locked->forceFill([
                    'status' => PayoutRequest::STATUS_REJECTED,
                    'user_visible_reason' => trim($reason),
                    'rejected_at' => now(),
                    'reviewed_by_admin_id' => $admin->getKey(),
                    'internal_notes' => $this->appendNote(
                        $locked->internal_notes,
                        'REJECTION',
                        $reason,
                        $internalNote
                    ),
                ])->save();

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId: $admin->getKey(),
                    action: 'PAYOUT_REJECTED',
                    module: 'PAYOUTS',
                    targetType: 'PAYOUT_REQUEST',
                    targetId: $locked->getKey(),
                    oldValues: [
                        'status' => $oldStatus,
                    ],
                    newValues: [
                        'payout_uuid' => $locked->uuid,
                        'status' => PayoutRequest::STATUS_REJECTED,
                        'method' => $locked->method,
                        'amount_cents' => (int) $locked->amount_cents,
                        'currency' => $locked->currency,
                        'balance_released' => true,
                        'reason_recorded' => true,
                    ],
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );

                return $locked->fresh([
                    'user',
                    'allocations',
                ]);
            },
            3
        );
    }

    public function markPaid(
        PayoutRequest $payout,
        AdminUser $admin,
        string $providerReference,
        ?string $internalNote,
        Request $request
    ): PayoutRequest {
        return DB::transaction(
            function () use (
                $payout,
                $admin,
                $providerReference,
                $internalNote,
                $request
            ): PayoutRequest {
                $locked = $this->lockPayoutAndUser(
                    $payout
                );

                if (
                    $locked->status
                    !== PayoutRequest::STATUS_APPROVED
                ) {
                    throw new DomainException(
                        'Only an approved payout can be marked as paid.'
                    );
                }

                $this->assertDestinationIntegrity(
                    $locked,
                    (array) $locked->destination_snapshot
                );

                $this->assertAllocationIntegrity(
                    $locked
                );

                $providerReference = trim(
                    $providerReference
                );

                $oldStatus = $locked->status;

                $locked->forceFill([
                    'status' => PayoutRequest::STATUS_PAID,
                    'provider_reference' => $providerReference,
                    'paid_at' => now(),
                    'reviewed_by_admin_id' => $admin->getKey(),
                    'internal_notes' => $this->appendNote(
                        $locked->internal_notes,
                        'PAID',
                        'External payout completion was confirmed.',
                        $internalNote
                    ),
                ])->save();

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId: $admin->getKey(),
                    action: 'PAYOUT_MARKED_PAID',
                    module: 'PAYOUTS',
                    targetType: 'PAYOUT_REQUEST',
                    targetId: $locked->getKey(),
                    oldValues: [
                        'status' => $oldStatus,
                    ],
                    newValues: [
                        'payout_uuid' => $locked->uuid,
                        'status' => PayoutRequest::STATUS_PAID,
                        'method' => $locked->method,
                        'amount_cents' => (int) $locked->amount_cents,
                        'currency' => $locked->currency,
                        'provider_reference_hash' => hash(
                            'sha256',
                            $providerReference
                        ),
                    ],
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );

                return $locked->fresh([
                    'user',
                    'allocations',
                ]);
            },
            3
        );
    }

    private function applyFilters(
        Builder $query,
        array $filters
    ): void {
        $search = trim(
            (string) ($filters['search'] ?? '')
        );

        if ($search !== '') {
            $like = '%'.$search.'%';

            $query->where(
                function (Builder $nested) use ($like): void {
                    $nested
                        ->where('uuid', 'like', $like)
                        ->orWhereHas(
                            'user',
                            function (Builder $user) use ($like): void {
                                $user
                                    ->where('name', 'like', $like)
                                    ->orWhere('email', 'like', $like)
                                    ->orWhere('referral_code', 'like', $like);
                            }
                        );
                }
            );
        }

        $method = strtoupper(
            trim((string) ($filters['method'] ?? ''))
        );

        if (
            $method !== ''
            && in_array($method, PayoutRequest::METHODS, true)
        ) {
            $query->where('method', $method);
        }

        $status = strtoupper(
            trim((string) ($filters['status'] ?? ''))
        );

        if (
            $status !== ''
            && in_array($status, PayoutRequest::STATUSES, true)
        ) {
            $query->where('status', $status);
        }

        $from = trim(
            (string) ($filters['from'] ?? '')
        );

        if ($from !== '') {
            $query->whereDate(
                'requested_at',
                '>=',
                $from
            );
        }

        $to = trim(
            (string) ($filters['to'] ?? '')
        );

        if ($to !== '') {
            $query->whereDate(
                'requested_at',
                '<=',
                $to
            );
        }
    }

    private function lockPayoutAndUser(
        PayoutRequest $payout
    ): PayoutRequest {
        $locked = PayoutRequest::query()
            ->whereKey($payout->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        User::query()
            ->whereKey($locked->user_id)
            ->lockForUpdate()
            ->firstOrFail();

        return $locked;
    }

    private function assertDestinationIntegrity(
        PayoutRequest $payout,
        array $snapshot
    ): void {
        if ($snapshot === []) {
            throw new DomainException(
                'The payout destination snapshot is missing.'
            );
        }

        $calculated = $this->destinations
            ->fingerprint($snapshot);

        if (
            ! hash_equals(
                (string) $payout->destination_fingerprint,
                $calculated
            )
        ) {
            throw new DomainException(
                'The payout destination integrity check failed.'
            );
        }
    }

    private function assertAllocationIntegrity(
        PayoutRequest $payout
    ): void {
        $allocations = PayoutAllocation::query()
            ->where(
                'payout_request_id',
                $payout->getKey()
            )
            ->lockForUpdate()
            ->get();

        if ($allocations->isEmpty()) {
            throw new DomainException(
                'The payout has no commission allocations.'
            );
        }

        $allocationTotal = (int) $allocations
            ->sum('amount_cents');

        if (
            $allocationTotal
            !== (int) $payout->amount_cents
        ) {
            throw new DomainException(
                'The payout allocation total does not match the requested amount.'
            );
        }

        $commissionIds = $allocations
            ->pluck('affiliate_commission_id')
            ->unique()
            ->values();

        $commissions = AffiliateCommission::query()
            ->whereIn('id', $commissionIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($allocations as $allocation) {
            $amount = (int) $allocation->amount_cents;

            if ($amount <= 0) {
                throw new DomainException(
                    'A payout allocation contains an invalid amount.'
                );
            }

            $commission = $commissions->get(
                $allocation->affiliate_commission_id
            );

            if (! $commission instanceof AffiliateCommission) {
                throw new DomainException(
                    'A payout allocation references a missing commission.'
                );
            }

            if (
                (int) $commission->affiliate_user_id
                !== (int) $payout->user_id
            ) {
                throw new DomainException(
                    'A payout allocation belongs to another affiliate.'
                );
            }

            if (
                $commission->status
                !== AffiliateCommission::STATUS_EARNED
            ) {
                throw new DomainException(
                    'A payout allocation references a non-earned commission.'
                );
            }

            if (
                strtoupper((string) $commission->currency)
                !== strtoupper((string) $payout->currency)
            ) {
                throw new DomainException(
                    'A payout allocation currency does not match the payout.'
                );
            }
        }

        foreach ($commissions as $commission) {
            $adjustments = AffiliateCommissionAdjustment::query()
                ->where(
                    'affiliate_commission_id',
                    $commission->getKey()
                )
                ->lockForUpdate()
                ->get([
                    'id',
                    'amount_cents',
                ]);

            $net = (int) $commission->commission_amount_cents
                + (int) $adjustments->sum('amount_cents');

            $consumed = (int) PayoutAllocation::query()
                ->join(
                    'payout_requests',
                    'payout_requests.id',
                    '=',
                    'payout_allocations.payout_request_id'
                )
                ->where(
                    'payout_allocations.affiliate_commission_id',
                    $commission->getKey()
                )
                ->whereIn(
                    'payout_requests.status',
                    PayoutRequest::BALANCE_CONSUMING_STATUSES
                )
                ->sum(
                    'payout_allocations.amount_cents'
                );

            if ($consumed > max(0, $net)) {
                throw new DomainException(
                    'The affiliate ledger changed after this payout was requested. Review the payout before proceeding.'
                );
            }
        }
    }

    private function appendNote(
        ?string $existing,
        string $label,
        string $reason,
        ?string $internalNote
    ): string {
        $parts = [];

        if (filled($existing)) {
            $parts[] = trim((string) $existing);
        }

        $entry = '['
            .$label
            .' '
            .now()->toIso8601String()
            .'] '
            .trim($reason);

        if (filled($internalNote)) {
            $entry .= ' | Internal: '
                .trim((string) $internalNote);
        }

        $parts[] = $entry;

        return implode("\n", $parts);
    }
}
