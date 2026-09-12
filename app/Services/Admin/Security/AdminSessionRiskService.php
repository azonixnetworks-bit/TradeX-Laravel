<?php

namespace App\Services\Admin\Security;

use App\Models\AdminSession;
use Illuminate\Support\Collection;

class AdminSessionRiskService
{
    public const RISK_NORMAL = 'NORMAL';
    public const RISK_REVIEW = 'REVIEW';
    public const RISK_SUSPICIOUS = 'SUSPICIOUS';

    public function analyze(AdminSession $session): array
    {
        $previous = AdminSession::query()
            ->where('admin_user_id', $session->admin_user_id)
            ->where('id', '<', $session->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get([
                'id',
                'ip_address',
                'device_name',
                'device_type',
                'browser',
                'platform',
                'login_at',
            ]);

        if ($previous->isEmpty()) {
            return [
                'level' => self::RISK_NORMAL,
                'signals' => ['FIRST_TRACKED_SESSION'],
                'score' => 0,
            ];
        }

        $signals = [];
        $score = 0;

        if (! $this->hasMatch($previous, 'ip_address', $session->ip_address)) {
            $signals[] = 'NEW_IP';
            $score += 2;
        }

        if (! $this->hasMatch($previous, 'platform', $session->platform)) {
            $signals[] = 'NEW_PLATFORM';
            $score += 2;
        }

        if (! $this->hasMatch($previous, 'browser', $session->browser)) {
            $signals[] = 'NEW_BROWSER';
            $score += 1;
        }

        if (! $this->hasDeviceMatch($previous, $session)) {
            $signals[] = 'NEW_DEVICE_PROFILE';
            $score += 2;
        }

        $recent = $previous->first();

        if (
            $recent
            && $recent->login_at
            && $session->login_at
            && $recent->login_at->diffInMinutes($session->login_at) <= 10
            && $recent->ip_address !== null
            && $session->ip_address !== null
            && $recent->ip_address !== $session->ip_address
            && $recent->platform !== null
            && $session->platform !== null
            && $recent->platform !== $session->platform
        ) {
            $signals[] = 'RAPID_IP_PLATFORM_CHANGE';
            $score += 3;
        }

        $level = match (true) {
            $score >= 5 => self::RISK_SUSPICIOUS,
            $score >= 2 => self::RISK_REVIEW,
            default => self::RISK_NORMAL,
        };

        return [
            'level' => $level,
            'signals' => $signals === [] ? ['KNOWN_PATTERN'] : $signals,
            'score' => $score,
        ];
    }

    public function readableSignal(string $signal): string
    {
        return match ($signal) {
            'FIRST_TRACKED_SESSION' => 'First tracked session for this admin',
            'NEW_IP' => 'New IP address',
            'NEW_PLATFORM' => 'New operating system / platform',
            'NEW_BROWSER' => 'New browser',
            'NEW_DEVICE_PROFILE' => 'New device profile',
            'RAPID_IP_PLATFORM_CHANGE' => 'Rapid IP and platform change',
            'KNOWN_PATTERN' => 'Matches known login pattern',
            default => str_replace('_', ' ', $signal),
        };
    }

    private function hasMatch(Collection $sessions, string $field, mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return $sessions->contains(
            fn (AdminSession $item): bool => (string) $item->{$field} === (string) $value
        );
    }

    private function hasDeviceMatch(Collection $sessions, AdminSession $session): bool
    {
        return $sessions->contains(function (AdminSession $item) use ($session): bool {
            return (string) $item->device_type === (string) $session->device_type
                && (string) $item->platform === (string) $session->platform
                && (string) $item->browser === (string) $session->browser;
        });
    }
}
