<?php

namespace App\Services\Admin\Security;

use App\Models\AdminSession;
use App\Models\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AdminDashboardSecurityService
{
    public function __construct(
        private readonly AdminSessionRiskService $risk
    ) {
    }

    public function summary(): array
    {
        $activeSessions = AdminSession::query()
            ->whereNull('revoked_at')
            ->whereNull('logged_out_at')
            ->orderByDesc('id')
            ->limit(250)
            ->get();

        $review = 0;
        $suspicious = 0;
        $online = 0;

        foreach ($activeSessions as $session) {
            if ($session->activityStatus() === 'ONLINE') {
                $online++;
            }

            $analysis = $this->risk->analyze($session);

            if (($analysis['level'] ?? null) === AdminSessionRiskService::RISK_REVIEW) {
                $review++;
            }

            if (($analysis['level'] ?? null) === AdminSessionRiskService::RISK_SUSPICIOUS) {
                $suspicious++;
            }
        }

        return [
            'active_sessions' => $activeSessions->count(),
            'online_sessions' => $online,
            'review_sessions' => $review,
            'suspicious_sessions' => $suspicious,
            'revoked_24h' => AdminSession::query()
                ->whereNotNull('revoked_at')
                ->where('revoked_at', '>=', now()->subDay())
                ->count(),
            'security_events_24h' => AuditLog::query()
                ->whereIn('module', ['AUTH', 'SECURITY'])
                ->where('created_at', '>=', now()->subDay())
                ->count(),
        ];
    }

    public function recentAlerts(int $limit = 5): Collection
    {
        return AuditLog::query()
            ->where('module', 'SECURITY')
            ->whereIn('action', [
                'ADMIN_LOGIN_RISK_DETECTED',
                'ADMIN_SUSPICIOUS_LOGIN_DETECTED',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(max(1, min($limit, 10)))
            ->get();
    }

    public function alertTimeDisplay(AuditLog $alert, string $timezone): string
    {
        $raw = $alert->getRawOriginal('created_at');

        if ($raw === null || trim((string) $raw) === '') {
            return '—';
        }

        try {
            return CarbonImmutable::parse((string) $raw, 'UTC')
                ->setTimezone($timezone)
                ->format('d M Y h:i:s A');
        } catch (\Throwable) {
            return '—';
        }
    }
}
