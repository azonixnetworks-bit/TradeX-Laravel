<?php

namespace App\Services\Admin\TradingFloor;

use App\Models\TradingFloorAccessAttempt;
use App\Models\TradingFloorAccessCredential;
use App\Models\TradingFloorAccessDevice;
use App\Models\TradingFloorAccessLog;
use App\Models\TradingFloorAccessSetting;
use App\Models\TradingFloorClient;
use App\Models\TradingFloorCommissionInvoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TradingFloorAccessService
{
    /** @return array<string, mixed> */
    public function process(
        TradingFloorAccessDevice $device,
        string $eventId,
        string $credentialType,
        string $rawCredentialReference,
        string $direction,
        ?string $ip = null
    ): array {
        $eventId = trim($eventId);
        $credentialType = strtoupper(trim($credentialType));
        $direction = strtoupper(trim($direction));

        $existing = TradingFloorAccessAttempt::query()
            ->where('trading_floor_access_device_id', $device->id)
            ->where('device_event_id', $eventId)
            ->first();

        if ($existing !== null && is_array($existing->response_json)) {
            $response = $existing->response_json;
            $response['replayed'] = true;

            return $response;
        }

        $credentialFingerprint = $this->credentialFingerprint($rawCredentialReference);
        $settings = TradingFloorAccessSetting::current();

        $credential = TradingFloorAccessCredential::query()
            ->with('client')
            ->where('credential_type', $credentialType)
            ->where('credential_reference', $credentialFingerprint)
            ->first();

        $client = $credential?->client;
        $commission = $client !== null
            ? $this->commissionSnapshot($client)
            : $this->emptyCommissionSnapshot();

        [$decision, $reasonCode, $message] = $this->decision(
            $settings,
            $device,
            $credential,
            $client,
            $direction,
            $commission
        );

        $occurredAt = now();
        $response = [
            'event_id' => $eventId,
            'decision' => $decision,
            'reason_code' => $reasonCode,
            'message' => $message,
            'occurred_at' => $occurredAt->toIso8601String(),
            'replayed' => false,
            'client' => $client === null ? null : [
                'client_code' => $client->client_code,
                'full_name' => $client->full_name,
                'status' => $client->status,
                'floor_code' => $client->floor_code,
                'desk_code' => $client->desk_code,
                'package_code' => $client->package_code,
            ],
            'access' => [
                'direction' => $direction,
                'method' => $credentialType,
                'device_code' => $device->device_code,
            ],
            'commission' => $commission,
        ];

        DB::transaction(function () use (
            $device,
            $eventId,
            $credential,
            $client,
            $credentialType,
            $credentialFingerprint,
            $direction,
            $decision,
            $reasonCode,
            $message,
            $occurredAt,
            $response,
            $commission,
            $ip
        ): void {
            TradingFloorAccessAttempt::create([
                'trading_floor_access_device_id' => $device->id,
                'device_code' => $device->device_code,
                'device_event_id' => $eventId,
                'trading_floor_client_id' => $client?->id,
                'trading_floor_access_credential_id' => $credential?->id,
                'credential_type' => $credentialType,
                'credential_fingerprint' => $credentialFingerprint,
                'direction' => $direction,
                'decision' => $decision,
                'reason_code' => $reasonCode,
                'reason' => $message,
                'occurred_at' => $occurredAt,
                'response_json' => $response,
                'metadata' => [
                    'request_ip' => $ip,
                    'device_floor_code' => $device->floor_code,
                    'client_floor_code' => $client?->floor_code,
                ],
            ]);

            if ($client !== null) {
                TradingFloorAccessLog::create([
                    'trading_floor_client_id' => $client->id,
                    'trading_floor_access_credential_id' => $credential?->id,
                    'direction' => $direction,
                    'method' => $credentialType,
                    'device_code' => $device->device_code,
                    'decision' => $decision,
                    'decision_reason' => $message,
                    'accessed_at' => $occurredAt,
                    'metadata' => [
                        'device_event_id' => $eventId,
                        'reason_code' => $reasonCode,
                        'commission' => $commission,
                    ],
                ]);
            }
        });

        return $response;
    }

    private function credentialFingerprint(string $reference): string
    {
        return hash_hmac(
            'sha256',
            trim($reference),
            (string) config('app.key')
        );
    }

    /** @return array{0:string,1:string,2:string} */
    private function decision(
        TradingFloorAccessSetting $settings,
        TradingFloorAccessDevice $device,
        ?TradingFloorAccessCredential $credential,
        ?TradingFloorClient $client,
        string $direction,
        array $commission
    ): array {
        if (! $settings->api_enabled) {
            return ['DENIED', 'ACCESS_API_DISABLED', 'Trading Floor access API is disabled by administration.'];
        }

        if ($credential === null) {
            return ['DENIED', 'UNKNOWN_CREDENTIAL', 'NFC card or fingerprint reference is not registered.'];
        }

        if ($credential->status !== TradingFloorAccessCredential::STATUS_ACTIVE) {
            return ['DENIED', 'CREDENTIAL_REVOKED', 'This access credential is revoked or inactive.'];
        }

        if ($client === null) {
            return ['DENIED', 'CLIENT_NOT_FOUND', 'The access credential is not linked to a Trading Floor customer.'];
        }

        if ($settings->require_active_client && $client->status !== TradingFloorClient::STATUS_ACTIVE) {
            return ['DENIED', 'CLIENT_NOT_ACTIVE', 'The Trading Floor customer account is not active.'];
        }

        $overrideMode = strtoupper((string) ($client->access_override_mode ?: TradingFloorClient::ACCESS_OVERRIDE_AUTO));
        $overrideActive = $overrideMode !== TradingFloorClient::ACCESS_OVERRIDE_AUTO
            && ($client->access_override_until === null || $client->access_override_until->isFuture());

        if ($direction === TradingFloorAccessLog::DIRECTION_EXIT && $settings->allow_exit_when_blocked) {
            if ((bool) $commission['has_overdue']) {
                return ['WARNING', 'EXIT_ALLOWED_WITH_OVERDUE', 'Exit allowed. Customer has overdue commission.'];
            }

            return ['GRANTED', 'EXIT_ALLOWED', 'Exit access granted.'];
        }

        if ($overrideActive && $overrideMode === TradingFloorClient::ACCESS_OVERRIDE_FORCE_DENY) {
            return ['DENIED', 'ADMIN_FORCE_DENY', 'Access denied by an active administrator override.'];
        }

        if ($overrideActive && $overrideMode === TradingFloorClient::ACCESS_OVERRIDE_FORCE_ALLOW) {
            return ['GRANTED', 'ADMIN_FORCE_ALLOW', 'Access granted by an active administrator override.'];
        }

        if (
            $settings->enforce_floor_match
            && filled($device->floor_code)
            && filled($client->floor_code)
            && strtoupper((string) $device->floor_code) !== strtoupper((string) $client->floor_code)
        ) {
            return ['DENIED', 'FLOOR_MISMATCH', 'Customer is not assigned to this Trading Floor reader location.'];
        }

        if (! (bool) $commission['has_overdue']) {
            return ['GRANTED', 'ACCESS_OK', 'Access granted.'];
        }

        $mode = strtoupper((string) $settings->commission_enforcement_mode);

        if ($mode === TradingFloorAccessSetting::MODE_NONE) {
            return ['GRANTED', 'OVERDUE_NOT_ENFORCED', 'Access granted. Overdue commission is not enforced by current policy.'];
        }

        if ($mode === TradingFloorAccessSetting::MODE_WARNING) {
            return ['WARNING', 'COMMISSION_OVERDUE_WARNING', 'Access granted with warning: commission payment is overdue.'];
        }

        if ($mode === TradingFloorAccessSetting::MODE_BLOCK_IMMEDIATE) {
            return ['DENIED', 'COMMISSION_OVERDUE_BLOCKED', 'Access denied because commission payment is overdue.'];
        }

        if ($mode === TradingFloorAccessSetting::MODE_BLOCK_AFTER_GRACE) {
            $graceDays = max(
                (int) $settings->block_after_overdue_days,
                (int) $client->grace_days
            );

            if ((int) $commission['days_overdue'] > $graceDays) {
                return ['DENIED', 'COMMISSION_GRACE_EXPIRED', 'Access denied because the overdue commission grace period has expired.'];
            }

            return ['WARNING', 'COMMISSION_GRACE_ACTIVE', 'Access granted with warning during the overdue commission grace period.'];
        }

        return ['WARNING', 'COMMISSION_POLICY_FALLBACK', 'Access granted with warning because commission is overdue.'];
    }

    /** @return array<string, mixed> */
    private function commissionSnapshot(TradingFloorClient $client): array
    {
        $query = TradingFloorCommissionInvoice::query()
            ->where('trading_floor_client_id', $client->id)
            ->whereDate('due_date', '<', now()->toDateString())
            ->where('balance_amount', '>', 0)
            ->whereNotIn('status', [
                TradingFloorCommissionInvoice::STATUS_PAID,
                TradingFloorCommissionInvoice::STATUS_WAIVED,
                TradingFloorCommissionInvoice::STATUS_DISPUTED,
            ]);

        $overdueCount = (clone $query)->count();
        $earliestDue = (clone $query)->min('due_date');
        $amounts = (clone $query)
            ->selectRaw('currency, SUM(balance_amount) as total')
            ->groupBy('currency')
            ->pluck('total', 'currency')
            ->map(fn ($amount): float => round((float) $amount, 2))
            ->all();

        $daysOverdue = 0;
        if ($earliestDue !== null) {
            $daysOverdue = Carbon::parse($earliestDue)
                ->startOfDay()
                ->diffInDays(now()->startOfDay());
        }

        return [
            'has_overdue' => $overdueCount > 0,
            'overdue_invoices' => $overdueCount,
            'days_overdue' => $daysOverdue,
            'earliest_due_date' => $earliestDue,
            'outstanding_by_currency' => $amounts,
        ];
    }

    /** @return array<string, mixed> */
    private function emptyCommissionSnapshot(): array
    {
        return [
            'has_overdue' => false,
            'overdue_invoices' => 0,
            'days_overdue' => 0,
            'earliest_due_date' => null,
            'outstanding_by_currency' => [],
        ];
    }
}
