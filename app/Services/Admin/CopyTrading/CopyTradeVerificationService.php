<?php

namespace App\Services\Admin\CopyTrading;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\CopyTradeBrokerAccount;
use App\Models\CopyTradeBrokerAccountReview;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CopyTradeVerificationService
{
    public function verificationQueue(array $filters): LengthAwarePaginator
    {
        $filters['queue_only'] = true;

        return $this->filteredAccounts($filters);
    }

    public function accounts(array $filters): LengthAwarePaginator
    {
        return $this->filteredAccounts($filters);
    }

    public function verify(
        CopyTradeBrokerAccount $account,
        AdminUser $admin,
        array $data,
        ?UploadedFile $evidence,
        Request $request
    ): CopyTradeBrokerAccount {
        $account->loadMissing('brokerMarket.broker');
        $this->assertReviewable($account, $admin);

        $requiredCurrency = strtoupper(
            (string) $account->brokerMarket?->required_currency
        );

        if (
            strtoupper((string) $data['verified_currency'])
            !== $requiredCurrency
        ) {
            throw new DomainException(
                'Verified broker account currency must match the configured eligible currency: '.$requiredCurrency.'.'
            );
        }

        if (
            ! (bool) ($data['partner_attribution_confirmed'] ?? false)
            || ! (bool) ($data['live_account_confirmed'] ?? false)
        ) {
            throw new DomainException(
                'Partner attribution and LIVE account confirmation are mandatory before verification.'
            );
        }

        $evidenceMeta = $this->storeEvidence(
            $evidence,
            $account->uuid,
            'verify'
        );

        try {
            return DB::transaction(function () use (
                $account,
                $admin,
                $data,
                $request,
                $evidenceMeta
            ): CopyTradeBrokerAccount {
                $locked = CopyTradeBrokerAccount::query()
                    ->lockForUpdate()
                    ->findOrFail($account->id);

                $this->assertReviewable($locked, $admin);
                $before = $this->accountSnapshot($locked);

                $locked->update([
                    'status' => CopyTradeBrokerAccount::STATUS_VERIFIED,
                    'verified_currency' => strtoupper(
                        (string) $data['verified_currency']
                    ),
                    'verified_account_mode' => 'LIVE',
                    'partner_attribution_confirmed_at' => now(),
                    'last_status_reason' => $data['reason'] ?? null,
                    'verified_at' => now(),
                    'rejected_at' => null,
                    'escalated_at' => null,
                    'verified_by_admin_id' => $admin->id,
                    'status_changed_by_admin_id' => $admin->id,
                ]);

                $review = $this->createReview(
                    $locked,
                    $admin,
                    CopyTradeBrokerAccountReview::ACTION_VERIFY,
                    $before['status'],
                    $locked->status,
                    $data,
                    $evidenceMeta,
                    true,
                    true
                );

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId: $admin->id,
                    action: 'COPY_TRADE_BROKER_ACCOUNT_VERIFIED',
                    module: 'COPY_TRADING',
                    targetType: 'COPY_TRADE_BROKER_ACCOUNT',
                    targetId: $locked->id,
                    oldValues: $before,
                    newValues: [
                        ...$this->accountSnapshot($locked),
                        'review_id' => $review->id,
                        'evidence_attached' => $evidenceMeta !== null,
                    ],
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );

                return $locked->fresh([
                    'user',
                    'broker',
                    'brokerMarket',
                    'reviews.adminUser',
                ]);
            });
        } catch (\Throwable $exception) {
            $this->deleteEvidence($evidenceMeta);
            throw $exception;
        }
    }

    public function reject(
        CopyTradeBrokerAccount $account,
        AdminUser $admin,
        array $data,
        ?UploadedFile $evidence,
        Request $request
    ): CopyTradeBrokerAccount {
        abort_unless(
            $admin->hasPermission('copy_trading.accounts.reject'),
            403
        );

        return $this->decision(
            $account,
            $admin,
            $data,
            $evidence,
            $request,
            CopyTradeBrokerAccountReview::ACTION_REJECT,
            CopyTradeBrokerAccount::STATUS_REJECTED,
            'COPY_TRADE_BROKER_ACCOUNT_REJECTED'
        );
    }

    public function requestResubmission(
        CopyTradeBrokerAccount $account,
        AdminUser $admin,
        array $data,
        ?UploadedFile $evidence,
        Request $request
    ): CopyTradeBrokerAccount {
        abort_unless(
            $admin->hasPermission(
                'copy_trading.accounts.request_resubmission'
            ),
            403
        );

        if (
            $account->resubmission_count
            >= (int) config('tradex_copy_trading.max_resubmissions', 3)
        ) {
            throw new DomainException(
                'The resubmission limit has been reached. Escalate this broker account for manual review instead.'
            );
        }

        return $this->decision(
            $account,
            $admin,
            $data,
            $evidence,
            $request,
            CopyTradeBrokerAccountReview::ACTION_REQUEST_RESUBMISSION,
            CopyTradeBrokerAccount::STATUS_NEEDS_RESUBMISSION,
            'COPY_TRADE_BROKER_ACCOUNT_RESUBMISSION_REQUESTED'
        );
    }

    public function escalate(
        CopyTradeBrokerAccount $account,
        AdminUser $admin,
        array $data,
        ?UploadedFile $evidence,
        Request $request
    ): CopyTradeBrokerAccount {
        abort_unless(
            $admin->hasPermission('copy_trading.accounts.escalate'),
            403
        );

        return $this->decision(
            $account,
            $admin,
            $data,
            $evidence,
            $request,
            CopyTradeBrokerAccountReview::ACTION_ESCALATE,
            CopyTradeBrokerAccount::STATUS_ESCALATED,
            'COPY_TRADE_BROKER_ACCOUNT_ESCALATED'
        );
    }

    private function decision(
        CopyTradeBrokerAccount $account,
        AdminUser $admin,
        array $data,
        ?UploadedFile $evidence,
        Request $request,
        string $reviewAction,
        string $newStatus,
        string $auditAction
    ): CopyTradeBrokerAccount {
        $account->loadMissing('brokerMarket.broker');

        if ($account->status === CopyTradeBrokerAccount::STATUS_ESCALATED) {
            if (! $admin->isSuperAdmin()) {
                throw new DomainException(
                    'Escalated broker accounts require Super Admin review.'
                );
            }
        } elseif (
            ! in_array(
                $account->status,
                [
                    CopyTradeBrokerAccount::STATUS_PENDING,
                    CopyTradeBrokerAccount::STATUS_NEEDS_RESUBMISSION,
                ],
                true
            )
        ) {
            throw new DomainException(
                'This broker account is not in a reviewable verification state.'
            );
        }

        if (
            $newStatus === CopyTradeBrokerAccount::STATUS_NEEDS_RESUBMISSION
            && $account->status !== CopyTradeBrokerAccount::STATUS_PENDING
        ) {
            throw new DomainException(
                'A resubmission request can only be issued for a pending broker account.'
            );
        }

        if (
            $newStatus === CopyTradeBrokerAccount::STATUS_ESCALATED
            && $account->status === CopyTradeBrokerAccount::STATUS_ESCALATED
        ) {
            throw new DomainException('This broker account is already escalated.');
        }

        $evidenceMeta = $this->storeEvidence(
            $evidence,
            $account->uuid,
            strtolower($reviewAction)
        );

        try {
            return DB::transaction(function () use (
                $account,
                $admin,
                $data,
                $request,
                $reviewAction,
                $newStatus,
                $auditAction,
                $evidenceMeta
            ): CopyTradeBrokerAccount {
                $locked = CopyTradeBrokerAccount::query()
                    ->lockForUpdate()
                    ->findOrFail($account->id);

                if (
                    $locked->status === CopyTradeBrokerAccount::STATUS_ESCALATED
                    && ! $admin->isSuperAdmin()
                ) {
                    throw new DomainException(
                        'Escalated broker accounts require Super Admin review.'
                    );
                }

                $before = $this->accountSnapshot($locked);

                $attributes = [
                    'status' => $newStatus,
                    'last_status_reason' => $data['reason'],
                    'status_changed_by_admin_id' => $admin->id,
                ];

                if ($newStatus === CopyTradeBrokerAccount::STATUS_REJECTED) {
                    $attributes['rejected_at'] = now();
                    $attributes['escalated_at'] = null;
                }

                if ($newStatus === CopyTradeBrokerAccount::STATUS_ESCALATED) {
                    $attributes['escalated_at'] = now();
                }

                if ($newStatus === CopyTradeBrokerAccount::STATUS_NEEDS_RESUBMISSION) {
                    $attributes['rejected_at'] = null;
                    $attributes['escalated_at'] = null;
                }

                $locked->update($attributes);

                $review = $this->createReview(
                    $locked,
                    $admin,
                    $reviewAction,
                    $before['status'],
                    $locked->status,
                    $data,
                    $evidenceMeta,
                    null,
                    null
                );

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId: $admin->id,
                    action: $auditAction,
                    module: 'COPY_TRADING',
                    targetType: 'COPY_TRADE_BROKER_ACCOUNT',
                    targetId: $locked->id,
                    oldValues: $before,
                    newValues: [
                        ...$this->accountSnapshot($locked),
                        'review_id' => $review->id,
                        'evidence_attached' => $evidenceMeta !== null,
                    ],
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );

                return $locked->fresh([
                    'user',
                    'broker',
                    'brokerMarket',
                    'reviews.adminUser',
                ]);
            });
        } catch (\Throwable $exception) {
            $this->deleteEvidence($evidenceMeta);
            throw $exception;
        }
    }

    private function assertReviewable(
        CopyTradeBrokerAccount $account,
        AdminUser $admin
    ): void {
        if ($account->status === CopyTradeBrokerAccount::STATUS_ESCALATED) {
            if (! $admin->isSuperAdmin()) {
                throw new DomainException(
                    'Escalated broker accounts require Super Admin review.'
                );
            }

            return;
        }

        if ($account->status !== CopyTradeBrokerAccount::STATUS_PENDING) {
            throw new DomainException(
                'Only pending broker accounts can be verified.'
            );
        }
    }

    private function createReview(
        CopyTradeBrokerAccount $account,
        AdminUser $admin,
        string $action,
        ?string $oldStatus,
        ?string $newStatus,
        array $data,
        ?array $evidenceMeta,
        ?bool $partnerConfirmed,
        ?bool $liveConfirmed
    ): CopyTradeBrokerAccountReview {
        return CopyTradeBrokerAccountReview::query()->create([
            'broker_account_id' => $account->id,
            'admin_user_id' => $admin->id,
            'action' => $action,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $data['reason'] ?? null,
            'partner_attribution_confirmed' => $partnerConfirmed,
            'live_account_confirmed' => $liveConfirmed,
            'verified_currency' => $data['verified_currency'] ?? null,
            'portal_reference' => $data['portal_reference'] ?? null,
            'evidence_disk' => $evidenceMeta['disk'] ?? null,
            'evidence_path' => $evidenceMeta['path'] ?? null,
            'evidence_mime_type' => $evidenceMeta['mime_type'] ?? null,
            'evidence_size_bytes' => $evidenceMeta['size_bytes'] ?? null,
            'created_at' => now(),
        ]);
    }

    private function storeEvidence(
        ?UploadedFile $evidence,
        string $accountUuid,
        string $action
    ): ?array {
        if (! $evidence) {
            return null;
        }

        $disk = 'private';
        $extension = strtolower(
            $evidence->guessExtension()
            ?: $evidence->getClientOriginalExtension()
            ?: 'bin'
        );

        $path = $evidence->storeAs(
            'copy-trading/verification/'.$accountUuid,
            $action.'-'.Str::uuid().'.'.$extension,
            $disk
        );

        return [
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $evidence->getMimeType(),
            'size_bytes' => $evidence->getSize(),
        ];
    }

    private function deleteEvidence(?array $evidenceMeta): void
    {
        if (
            ! $evidenceMeta
            || blank($evidenceMeta['disk'] ?? null)
            || blank($evidenceMeta['path'] ?? null)
        ) {
            return;
        }

        Storage::disk($evidenceMeta['disk'])
            ->delete($evidenceMeta['path']);
    }

    private function filteredAccounts(array $filters): LengthAwarePaginator
    {
        $query = CopyTradeBrokerAccount::query()
            ->with([
                'user:id,uuid,name,email',
                'broker:id,uuid,code,name',
                'brokerMarket:id,uuid,broker_id,market_category,required_currency',
            ]);

        if (($filters['queue_only'] ?? false) === true) {
            $query->whereIn('status', [
                CopyTradeBrokerAccount::STATUS_PENDING,
                CopyTradeBrokerAccount::STATUS_NEEDS_RESUBMISSION,
                CopyTradeBrokerAccount::STATUS_ESCALATED,
            ]);
        }

        if (
            filled($filters['status'] ?? null)
            && in_array(
                strtoupper((string) $filters['status']),
                CopyTradeBrokerAccount::VALID_STATUSES,
                true
            )
        ) {
            $query->where(
                'status',
                strtoupper((string) $filters['status'])
            );
        }

        if (filled($filters['market'] ?? null)) {
            $market = strtoupper((string) $filters['market']);
            $query->whereHas('brokerMarket', fn ($marketQuery) =>
                $marketQuery->where('market_category', $market));
        }

        if (filled($filters['search'] ?? null)) {
            $search = trim((string) $filters['search']);

            $query->where(function ($inner) use ($search): void {
                $inner->whereHas('user', function ($userQuery) use ($search): void {
                    $userQuery
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });

                if (preg_match('/^[0-9]{1,4}$/', $search)) {
                    $inner->orWhere(
                        'account_number_last4',
                        str_pad($search, 4, '0', STR_PAD_LEFT)
                    );
                }
            });
        }

        return $query
            ->latest('submitted_at')
            ->paginate((int) ($filters['per_page'] ?? 20))
            ->withQueryString();
    }

    private function accountSnapshot(
        CopyTradeBrokerAccount $account
    ): array {
        $account->loadMissing([
            'broker',
            'brokerMarket',
        ]);

        return [
            'broker_code' => $account->broker?->code,
            'market_category' => $account->brokerMarket?->market_category,
            'account_masked' => $account->maskedAccountNumber(),
            'status' => $account->status,
            'resubmission_count' => $account->resubmission_count,
            'verified_currency' => $account->verified_currency,
            'verified_account_mode' => $account->verified_account_mode,
            'partner_attribution_confirmed' =>
                $account->partner_attribution_confirmed_at !== null,
            'verified_at' => $account->verified_at?->toIso8601String(),
            'activated_at' => $account->activated_at?->toIso8601String(),
        ];
    }
}
