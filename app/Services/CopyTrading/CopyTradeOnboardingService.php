<?php

namespace App\Services\CopyTrading;

use App\Models\AuditLog;
use App\Models\CopyTradeBrokerAccount;
use App\Models\CopyTradeBrokerMarket;
use App\Models\CopyTradeOnboardingIntent;
use App\Models\User;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

class CopyTradeOnboardingService
{
    public function availableBrokerMarkets(): Collection
    {
        return CopyTradeBrokerMarket::query()
            ->with('broker')
            ->where('is_active', true)
            ->whereHas('broker', fn ($query) =>
                $query->where('is_active', true))
            ->orderBy('market_category')
            ->orderBy('id')
            ->get();
    }

    public function accountsForUser(User $user): Collection
    {
        return CopyTradeBrokerAccount::query()
            ->with([
                'broker',
                'brokerMarket',
            ])
            ->where('user_id', $user->id)
            ->latest('submitted_at')
            ->get();
    }

    public function openAccountAction(
        User $user,
        CopyTradeBrokerMarket $market,
        Request $request
    ): CopyTradeOnboardingIntent {
        $market->loadMissing('broker');

        if (! $market->isAvailableForOnboarding()) {
            throw new DomainException(
                'This broker market is not currently available for onboarding.'
            );
        }

        $intent = CopyTradeOnboardingIntent::query()->create([
            'user_id' => $user->id,
            'broker_market_id' => $market->id,
            'referral_url_snapshot' => $market->referral_url,
            'opened_at' => now(),
            'expires_at' => now()->addDays(
                (int) config(
                    'tradex_copy_trading.onboarding_intent_ttl_days',
                    30
                )
            ),
            'consumed_at' => null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        AuditLog::record(
            actorType: 'USER',
            actorId: $user->id,
            action: 'COPY_TRADE_PARTNER_LINK_OPENED',
            module: 'COPY_TRADING',
            targetType: 'COPY_TRADE_ONBOARDING_INTENT',
            targetId: $intent->id,
            newValues: [
                'broker_code' => $market->broker?->code,
                'market_category' => $market->market_category,
                'intent_uuid' => $intent->uuid,
                'expires_at' => $intent->expires_at?->toIso8601String(),
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return $intent;
    }

    public function submitBrokerAccount(
        User $user,
        CopyTradeOnboardingIntent $intent,
        string $accountNumber,
        Request $request
    ): CopyTradeBrokerAccount {
        $intent->loadMissing('brokerMarket.broker');

        if (! $intent->isUsableBy($user)) {
            throw new DomainException(
                'Open the broker account through the TradeX partner action before submitting the account.'
            );
        }

        $market = $intent->brokerMarket;

        if (! $market instanceof CopyTradeBrokerMarket) {
            throw new DomainException('Broker market configuration is unavailable.');
        }

        $market->loadMissing('broker');

        if (! $market->isAvailableForOnboarding()) {
            throw new DomainException(
                'This broker market is no longer available for onboarding.'
            );
        }

        $normalized = $this->normalizeAccountNumber($accountNumber);
        $this->assertMarketAccountLimit($user, $market);

        $fingerprint = $this->fingerprint(
            $market->broker_id,
            $normalized
        );

        $this->assertNoDuplicateFingerprint(
            $user,
            $market,
            $fingerprint,
            $request
        );

        try {
            return DB::transaction(function () use (
                $user,
                $intent,
                $market,
                $normalized,
                $fingerprint,
                $request
            ): CopyTradeBrokerAccount {
                $lockedIntent = CopyTradeOnboardingIntent::query()
                    ->lockForUpdate()
                    ->findOrFail($intent->id);

                if (! $lockedIntent->isUsableBy($user)) {
                    throw new DomainException(
                        'This TradeX broker onboarding action is no longer valid.'
                    );
                }

                $account = CopyTradeBrokerAccount::query()->create([
                    'user_id' => $user->id,
                    'broker_id' => $market->broker_id,
                    'broker_market_id' => $market->id,
                    'onboarding_intent_id' => $lockedIntent->id,
                    'account_number' => $normalized,
                    'account_number_fingerprint' => $fingerprint,
                    'account_number_last4' => substr($normalized, -4),
                    'status' => CopyTradeBrokerAccount::STATUS_PENDING,
                    'resubmission_count' => 0,
                    'submitted_at' => now(),
                ]);

                $lockedIntent->update([
                    'consumed_at' => now(),
                ]);

                AuditLog::record(
                    actorType: 'USER',
                    actorId: $user->id,
                    action: 'COPY_TRADE_BROKER_ACCOUNT_SUBMITTED',
                    module: 'COPY_TRADING',
                    targetType: 'COPY_TRADE_BROKER_ACCOUNT',
                    targetId: $account->id,
                    newValues: [
                        'broker_code' => $market->broker?->code,
                        'market_category' => $market->market_category,
                        'account_masked' => $account->maskedAccountNumber(),
                        'status' => $account->status,
                    ],
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );

                return $account->load([
                    'broker',
                    'brokerMarket',
                ]);
            });
        } catch (QueryException $exception) {
            if (
                CopyTradeBrokerAccount::query()
                    ->where('account_number_fingerprint', $fingerprint)
                    ->exists()
            ) {
                throw new DomainException(
                    'This broker account cannot be submitted because it is already registered with TradeX.'
                );
            }

            throw $exception;
        }
    }

    public function resubmitBrokerAccount(
        User $user,
        CopyTradeBrokerAccount $account,
        string $accountNumber,
        Request $request
    ): CopyTradeBrokerAccount {
        if ($account->user_id !== $user->id) {
            throw new DomainException('Broker account not found.');
        }

        if (! $account->needsResubmission()) {
            throw new DomainException(
                'Only broker accounts marked NEEDS_RESUBMISSION can be resubmitted.'
            );
        }

        $max = (int) config(
            'tradex_copy_trading.max_resubmissions',
            3
        );

        if ($account->resubmission_count >= $max) {
            throw new DomainException(
                'The broker account has reached the resubmission limit and requires manual review.'
            );
        }

        $account->loadMissing('brokerMarket.broker');
        $market = $account->brokerMarket;

        if (! $market instanceof CopyTradeBrokerMarket) {
            throw new DomainException('Broker market configuration is unavailable.');
        }

        $normalized = $this->normalizeAccountNumber($accountNumber);
        $fingerprint = $this->fingerprint(
            $account->broker_id,
            $normalized
        );

        $duplicate = CopyTradeBrokerAccount::query()
            ->where('account_number_fingerprint', $fingerprint)
            ->whereKeyNot($account->id)
            ->exists();

        if ($duplicate) {
            AuditLog::record(
                actorType: 'USER',
                actorId: $user->id,
                action: 'COPY_TRADE_BROKER_ACCOUNT_DUPLICATE_BLOCKED',
                module: 'COPY_TRADING',
                targetType: 'COPY_TRADE_BROKER_ACCOUNT',
                targetId: $account->id,
                newValues: [
                    'broker_code' => $account->broker?->code,
                    'market_category' => $market->market_category,
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            throw new DomainException(
                'This broker account cannot be submitted because it is already registered with TradeX.'
            );
        }

        return DB::transaction(function () use (
            $account,
            $user,
            $normalized,
            $fingerprint,
            $request
        ): CopyTradeBrokerAccount {
            $locked = CopyTradeBrokerAccount::query()
                ->lockForUpdate()
                ->findOrFail($account->id);

            if (! $locked->needsResubmission()) {
                throw new DomainException(
                    'This broker account is no longer awaiting resubmission.'
                );
            }

            $before = [
                'status' => $locked->status,
                'account_masked' => $locked->maskedAccountNumber(),
                'resubmission_count' => $locked->resubmission_count,
            ];

            $locked->update([
                'account_number' => $normalized,
                'account_number_fingerprint' => $fingerprint,
                'account_number_last4' => substr($normalized, -4),
                'status' => CopyTradeBrokerAccount::STATUS_PENDING,
                'resubmission_count' => $locked->resubmission_count + 1,
                'last_status_reason' => null,
                'submitted_at' => now(),
                'rejected_at' => null,
                'escalated_at' => null,
                'status_changed_by_admin_id' => null,
            ]);

            AuditLog::record(
                actorType: 'USER',
                actorId: $user->id,
                action: 'COPY_TRADE_BROKER_ACCOUNT_RESUBMITTED',
                module: 'COPY_TRADING',
                targetType: 'COPY_TRADE_BROKER_ACCOUNT',
                targetId: $locked->id,
                oldValues: $before,
                newValues: [
                    'status' => $locked->status,
                    'account_masked' => $locked->maskedAccountNumber(),
                    'resubmission_count' => $locked->resubmission_count,
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            return $locked->load([
                'broker',
                'brokerMarket',
            ]);
        });
    }

    public function fingerprint(
        int $brokerId,
        string $accountNumber
    ): string {
        $key = (string) config(
            'tradex_copy_trading.fingerprint_key',
            ''
        );

        if ($key === '') {
            throw new LogicException(
                'Copy Trade account fingerprint key is not configured.'
            );
        }

        return hash_hmac(
            'sha256',
            $brokerId.'|'.$accountNumber,
            $key
        );
    }

    private function normalizeAccountNumber(string $accountNumber): string
    {
        $normalized = trim($accountNumber);
        $max = (int) config(
            'tradex_copy_trading.max_account_number_digits',
            15
        );

        if (
            ! preg_match('/^[0-9]{1,'.$max.'}$/', $normalized)
        ) {
            throw new DomainException(
                'Broker account number must contain digits only and must not exceed '.$max.' digits.'
            );
        }

        return $normalized;
    }

    private function assertMarketAccountLimit(
        User $user,
        CopyTradeBrokerMarket $market
    ): void {
        $max = (int) config(
            'tradex_copy_trading.max_accounts_per_market',
            5
        );

        $count = CopyTradeBrokerAccount::query()
            ->where('user_id', $user->id)
            ->whereNotIn('status', [
                CopyTradeBrokerAccount::STATUS_REJECTED,
                CopyTradeBrokerAccount::STATUS_DISCONNECTED,
            ])
            ->whereHas('brokerMarket', fn ($query) =>
                $query->where(
                    'market_category',
                    $market->market_category
                ))
            ->count();

        if ($count >= $max) {
            throw new DomainException(
                'The maximum number of Copy Trade accounts for this market category has been reached.'
            );
        }
    }

    private function assertNoDuplicateFingerprint(
        User $user,
        CopyTradeBrokerMarket $market,
        string $fingerprint,
        Request $request
    ): void {
        if (
            ! CopyTradeBrokerAccount::query()
                ->where('account_number_fingerprint', $fingerprint)
                ->exists()
        ) {
            return;
        }

        AuditLog::record(
            actorType: 'USER',
            actorId: $user->id,
            action: 'COPY_TRADE_BROKER_ACCOUNT_DUPLICATE_BLOCKED',
            module: 'COPY_TRADING',
            targetType: 'COPY_TRADE_BROKER_MARKET',
            targetId: $market->id,
            newValues: [
                'broker_code' => $market->broker?->code,
                'market_category' => $market->market_category,
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        throw new DomainException(
            'This broker account cannot be submitted because it is already registered with TradeX.'
        );
    }
}
