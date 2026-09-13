<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerSecurityCenterService
{
    public function __construct(
        private readonly CustomerDeviceSecurityService $devices,
        private readonly CustomerSecurityHistoryService $history
    ) {
    }

    public function center(
        User $user,
        UserDevice $currentDevice
    ): array {
        $trustedDevices = $this->devices
            ->activeTrustedDevices($user, $currentDevice)
            ->values();

        $notifications = $this->history
            ->notifications($user, 10)
            ->values();

        $historyPreview = $this->history
            ->history($user, 10)
            ->values();

        $pendingCount = $this->pendingReviewCount($user);

        $pendingAlert = $notifications->first(
            fn (array $event): bool =>
                ($event['review']['required'] ?? false)
                && ($event['review']['status'] ?? null) === 'PENDING'
        );

        return [
            'summary' => [
                'trusted_devices' => $trustedDevices->count(),
                'max_trusted_devices' => $this->devices->maxTrustedDevices(),
                'pending_review_count' => $pendingCount,
                'has_pending_review' => $pendingCount > 0,
                'highest_pending_severity' => $this->highestPendingSeverity($user),
            ],
            'banner' => $this->banner($pendingAlert, $pendingCount),
            'current_device' => $trustedDevices->firstWhere('is_current', true),
            'devices' => $trustedDevices,
            'notifications' => $notifications,
            'history_preview' => $historyPreview,
        ];
    }

    private function pendingReviewCount(User $user): int
    {
        return DB::table('audit_logs as risk')
            ->where('risk.actor_type', 'USER')
            ->where('risk.actor_id', $user->getKey())
            ->whereIn('risk.action', [
                'USER_LOGIN_RISK_DETECTED',
                'USER_SUSPICIOUS_LOGIN_DETECTED',
            ])
            ->whereNotExists(function ($query) use ($user): void {
                $query
                    ->selectRaw('1')
                    ->from('audit_logs as review')
                    ->whereColumn('review.target_id', 'risk.id')
                    ->where('review.target_type', 'AUDIT_LOG')
                    ->where('review.actor_type', 'USER')
                    ->where('review.actor_id', $user->getKey())
                    ->whereIn('review.action', [
                        CustomerSecurityEventReviewService::ACTION_CONFIRMED,
                        CustomerSecurityEventReviewService::ACTION_REPORTED,
                    ]);
            })
            ->count();
    }

    private function highestPendingSeverity(User $user): ?string
    {
        $actions = DB::table('audit_logs as risk')
            ->where('risk.actor_type', 'USER')
            ->where('risk.actor_id', $user->getKey())
            ->whereIn('risk.action', [
                'USER_LOGIN_RISK_DETECTED',
                'USER_SUSPICIOUS_LOGIN_DETECTED',
            ])
            ->whereNotExists(function ($query) use ($user): void {
                $query
                    ->selectRaw('1')
                    ->from('audit_logs as review')
                    ->whereColumn('review.target_id', 'risk.id')
                    ->where('review.target_type', 'AUDIT_LOG')
                    ->where('review.actor_type', 'USER')
                    ->where('review.actor_id', $user->getKey())
                    ->whereIn('review.action', [
                        CustomerSecurityEventReviewService::ACTION_CONFIRMED,
                        CustomerSecurityEventReviewService::ACTION_REPORTED,
                    ]);
            })
            ->pluck('risk.action');

        if ($actions->contains('USER_SUSPICIOUS_LOGIN_DETECTED')) {
            return 'HIGH';
        }

        if ($actions->isNotEmpty()) {
            return 'MEDIUM';
        }

        return null;
    }

    private function banner(
        ?array $pendingAlert,
        int $pendingCount
    ): array {
        if ($pendingAlert === null || $pendingCount < 1) {
            return [
                'visible' => false,
                'severity' => null,
                'event_id' => null,
                'title' => null,
                'message' => null,
                'action' => null,
            ];
        }

        return [
            'visible' => true,
            'severity' => $pendingAlert['severity'] ?? 'MEDIUM',
            'event_id' => $pendingAlert['event_id'] ?? null,
            'title' => $pendingAlert['title'] ?? 'Security review required',
            'message' => $pendingAlert['message'] ?? 'Review recent security activity on your account.',
            'action' => 'REVIEW_SECURITY_EVENT',
        ];
    }
}
