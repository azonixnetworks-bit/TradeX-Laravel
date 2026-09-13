<?php

namespace App\Services\Auth;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Http\Request;

class CustomerLoginRiskService
{
    public const NORMAL = 'NORMAL';
    public const REVIEW = 'REVIEW';
    public const SUSPICIOUS = 'SUSPICIOUS';

    public function analyze(
        User $user,
        array $payload,
        Request $request,
        bool $knownActiveTrustedDevice
    ): array {
        $signals = [];
        $score = 0;
        $ip = (string) ($request->ip() ?? '');
        $platform = strtoupper((string) ($payload['platform'] ?? ''));
        $deviceModel = trim((string) ($payload['device_model'] ?? ''));

        $recentDevices = $user->devices()
            ->active()
            ->trusted()
            ->orderByDesc('last_login_at')
            ->limit(5)
            ->get();

        if (! $knownActiveTrustedDevice && $recentDevices->isNotEmpty()) {
            $signals[] = 'NEW_DEVICE';
            $score += 30;
        }

        if ($recentDevices->isNotEmpty()) {
            $knownIps = $recentDevices
                ->pluck('last_login_ip')
                ->filter()
                ->unique();

            if ($ip !== '' && $knownIps->isNotEmpty() && ! $knownIps->contains($ip)) {
                $signals[] = 'NEW_IP';
                $score += 20;
            }

            $knownPlatforms = $recentDevices
                ->pluck('platform')
                ->filter()
                ->map(fn ($value) => strtoupper((string) $value))
                ->unique();

            if ($platform !== '' && $knownPlatforms->isNotEmpty() && ! $knownPlatforms->contains($platform)) {
                $signals[] = 'NEW_PLATFORM';
                $score += 25;
            }

            $knownModels = $recentDevices
                ->pluck('device_model')
                ->filter()
                ->map(fn ($value) => mb_strtolower(trim((string) $value)))
                ->unique();

            if (
                $deviceModel !== ''
                && $knownModels->isNotEmpty()
                && ! $knownModels->contains(mb_strtolower($deviceModel))
            ) {
                $signals[] = 'NEW_DEVICE_MODEL';
                $score += 10;
            }
        }

        $signals = array_values(array_unique($signals));

        $level = match (true) {
            $score >= 60 => self::SUSPICIOUS,
            $score >= 30 => self::REVIEW,
            default => self::NORMAL,
        };

        return [
            'level' => $level,
            'score' => $score,
            'signals' => $signals,
        ];
    }

    public function record(
        User $user,
        UserDevice $device,
        array $risk,
        string $mfaMethod,
        Request $request
    ): void {
        if (($risk['level'] ?? self::NORMAL) === self::NORMAL) {
            return;
        }

        AuditLog::record(
            actorType: 'USER',
            actorId: $user->getKey(),
            action: ($risk['level'] ?? null) === self::SUSPICIOUS
                ? 'USER_SUSPICIOUS_LOGIN_DETECTED'
                : 'USER_LOGIN_RISK_DETECTED',
            module: 'SECURITY',
            targetType: 'USER_DEVICE',
            targetId: $device->getKey(),
            newValues: [
                'risk_level' => $risk['level'],
                'risk_score' => $risk['score'],
                'signals' => $risk['signals'],
                'device_uuid' => $device->uuid,
                'platform' => $device->platform,
                'device_name' => $device->device_name,
                'device_model' => $device->device_model,
                'mfa_method' => $mfaMethod,
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );
    }
}
