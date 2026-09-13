<?php

namespace App\Services\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CustomerSecurityHistoryService
{
    private const HISTORY_ACTIONS = [
        'USER_NEW_TRUSTED_DEVICE_ADDED',
        'USER_LOGIN_RISK_DETECTED',
        'USER_SUSPICIOUS_LOGIN_DETECTED',
        'USER_TRUSTED_DEVICE_REMOVED',
        'CUSTOMER_DEVICE_FORCE_REVOKED',
        'API_LOGIN_SUCCESS',
        'API_LOGOUT',
        'API_LOGOUT_ALL',
        'API_DEVICE_SELF_REVOKE',
    ];

    private const NOTIFICATION_ACTIONS = [
        'USER_NEW_TRUSTED_DEVICE_ADDED',
        'USER_LOGIN_RISK_DETECTED',
        'USER_SUSPICIOUS_LOGIN_DETECTED',
        'USER_TRUSTED_DEVICE_REMOVED',
        'CUSTOMER_DEVICE_FORCE_REVOKED',
    ];

    public function history(User $user, int $limit = 30): Collection
    {
        return $this->queryForUser($user)
            ->whereIn('action', self::HISTORY_ACTIONS)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($this->boundedLimit($limit))
            ->get()
            ->map(fn (AuditLog $event): array => $this->safeEvent($event));
    }

    public function notifications(User $user, int $limit = 20): Collection
    {
        return $this->queryForUser($user)
            ->whereIn('action', self::NOTIFICATION_ACTIONS)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($this->boundedLimit($limit))
            ->get()
            ->map(fn (AuditLog $event): array => $this->safeEvent($event));
    }

    private function queryForUser(User $user): Builder
    {
        $deviceIds = $user->devices()
            ->pluck('id')
            ->all();

        return AuditLog::query()
            ->where(function (Builder $query) use ($user, $deviceIds): void {
                $query->where(function (Builder $owned): void use ($user) {
                    $owned
                        ->where('actor_type', 'USER')
                        ->where('actor_id', $user->getKey());
                });

                if ($deviceIds !== []) {
                    $query->orWhere(function (Builder $targeted) use ($deviceIds): void {
                        $targeted
                            ->where('target_type', 'USER_DEVICE')
                            ->whereIn('target_id', $deviceIds);
                    });
                }
            });
    }

    private function safeEvent(AuditLog $event): array
    {
        $values = is_array($event->new_values)
            ? $event->new_values
            : [];

        $riskLevel = strtoupper((string) ($values['risk_level'] ?? ''));
        $severity = match (true) {
            $event->action === 'USER_SUSPICIOUS_LOGIN_DETECTED' => 'HIGH',
            $event->action === 'CUSTOMER_DEVICE_FORCE_REVOKED' => 'HIGH',
            $riskLevel === 'SUSPICIOUS' => 'HIGH',
            $event->action === 'USER_LOGIN_RISK_DETECTED' => 'MEDIUM',
            $riskLevel === 'REVIEW' => 'MEDIUM',
            default => 'INFO',
        };

        [$title, $message] = $this->copyFor($event->action, $values);

        return [
            'event_id' => (int) $event->getKey(),
            'action' => $event->action,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'occurred_at' => $event->created_at?->toISOString(),
            'ip_address' => $event->ip_address,
            'device' => [
                'uuid' => $values['device_uuid'] ?? null,
                'platform' => $values['platform'] ?? null,
                'name' => $values['device_name'] ?? null,
                'model' => $values['device_model'] ?? null,
            ],
            'risk' => [
                'level' => $values['risk_level'] ?? null,
                'score' => isset($values['risk_score'])
                    ? (int) $values['risk_score']
                    : null,
                'signals' => array_values(array_map(
                    static fn ($signal): string => (string) $signal,
                    (array) ($values['signals'] ?? [])
                )),
            ],
        ];
    }

    private function copyFor(string $action, array $values): array
    {
        $deviceName = trim((string) ($values['device_name'] ?? ''));
        $deviceLabel = $deviceName !== '' ? $deviceName : 'a device';

        return match ($action) {
            'USER_NEW_TRUSTED_DEVICE_ADDED' => [
                'New trusted device',
                "{$deviceLabel} was added to your trusted devices after successful verification.",
            ],
            'USER_LOGIN_RISK_DETECTED' => [
                'Login needs review',
                'A login was detected with a new device, network, platform or device profile.',
            ],
            'USER_SUSPICIOUS_LOGIN_DETECTED' => [
                'Suspicious login detected',
                'A login matched multiple unusual security signals. Review your devices and secure your account if this was not you.',
            ],
            'USER_TRUSTED_DEVICE_REMOVED' => [
                'Trusted device removed',
                "{$deviceLabel} was removed from your trusted devices.",
            ],
            'CUSTOMER_DEVICE_FORCE_REVOKED' => [
                'Device access revoked',
                "{$deviceLabel} was revoked by TradeX security administration.",
            ],
            'API_LOGIN_SUCCESS' => [
                'Login successful',
                'Your TradeX account signed in successfully with multi-factor verification.',
            ],
            'API_LOGOUT' => [
                'Session signed out',
                'A TradeX session was signed out.',
            ],
            'API_LOGOUT_ALL' => [
                'All sessions signed out',
                'All active TradeX sessions were signed out.',
            ],
            'API_DEVICE_SELF_REVOKE' => [
                'Device revoked',
                'A trusted device revoked its own TradeX access.',
            ],
            default => [
                'Security activity',
                'A security-related change occurred on your TradeX account.',
            ],
        };
    }

    private function boundedLimit(int $limit): int
    {
        return max(1, min(50, $limit));
    }
}
