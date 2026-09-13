<?php

namespace App\Services\Admin\Security;

use App\Models\AuditLog;
use App\Models\UserDevice;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CustomerDeviceRiskReadService
{
    private const RISK_ACTIONS = [
        'USER_LOGIN_RISK_DETECTED',
        'USER_SUSPICIOUS_LOGIN_DETECTED',
    ];

    public function enrichSessions(LengthAwarePaginator $sessions): void
    {
        $deviceIds = $sessions->getCollection()
            ->pluck('user_device_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($deviceIds->isEmpty()) {
            return;
        }

        $latestByDevice = AuditLog::query()
            ->where('target_type', 'USER_DEVICE')
            ->whereIn('target_id', $deviceIds->all())
            ->whereIn('action', self::RISK_ACTIONS)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('target_id')
            ->map(fn (Collection $events) => $events->first());

        $sessions->getCollection()->transform(
            function (object $session) use ($latestByDevice): object {
                $event = $latestByDevice->get((int) $session->user_device_id);
                $values = is_array($event?->new_values) ? $event->new_values : [];

                $session->risk_level = (string) ($values['risk_level'] ?? 'NORMAL');
                $session->risk_score = (int) ($values['risk_score'] ?? 0);
                $session->risk_signals = array_values(
                    array_filter((array) ($values['signals'] ?? []), 'is_string')
                );
                $session->risk_event_at = $event?->created_at;

                return $session;
            }
        );
    }

    public function detail(UserDevice $device): array
    {
        $history = AuditLog::query()
            ->where('target_type', 'USER_DEVICE')
            ->where('target_id', $device->getKey())
            ->where('module', 'SECURITY')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        $riskEvent = $history->first(
            fn (AuditLog $event): bool => in_array($event->action, self::RISK_ACTIONS, true)
        );

        $values = is_array($riskEvent?->new_values)
            ? $riskEvent->new_values
            : [];

        return [
            'risk' => [
                'level' => (string) ($values['risk_level'] ?? 'NORMAL'),
                'score' => (int) ($values['risk_score'] ?? 0),
                'signals' => array_values(
                    array_filter((array) ($values['signals'] ?? []), 'is_string')
                ),
                'detected_at' => $riskEvent?->created_at,
            ],
            'history' => $history,
        ];
    }
}
