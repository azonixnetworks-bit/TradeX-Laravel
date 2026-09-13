<?php

namespace App\Services\Auth;

use App\Exceptions\AuthApiException;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LoginService
{
    public function __construct(
        private readonly TotpService $totp,
        private readonly MfaRecoveryCodeService $recoveryCodes,
        private readonly DeviceBindingService $devices,
        private readonly AccessTokenService $accessTokens,
        private readonly RefreshTokenService $refreshTokens,
        private readonly CustomerLoginRiskService $riskService
    ) {
    }

    public function login(array $payload, Request $request): array
    {
        $candidate = User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower((string) $payload['email'])])
            ->first();

        if (
            $candidate === null
            || ! Hash::check((string) $payload['password'], (string) $candidate->password)
        ) {
            throw new AuthApiException(
                401,
                'INVALID_CREDENTIALS',
                'The email or password is incorrect.'
            );
        }

        if (! $candidate->canLogin()) {
            throw new AuthApiException(
                403,
                'ACCOUNT_NOT_ACTIVE',
                'This account is not allowed to login.'
            );
        }

        return DB::transaction(function () use ($candidate, $payload, $request): array {
            $user = User::query()
                ->whereKey($candidate->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $user->canLogin()) {
                throw new AuthApiException(
                    403,
                    'ACCOUNT_NOT_ACTIVE',
                    'This account is not allowed to login.'
                );
            }

            $mfaMethod = 'TOTP';

            if (filled($payload['recovery_code'] ?? null)) {
                $this->recoveryCodes->consume(
                    $user,
                    (string) $payload['recovery_code'],
                    $request
                );
                $mfaMethod = 'RECOVERY_CODE';
            } else {
                $this->totp->consume(
                    $user,
                    (string) $payload['totp_code']
                );
            }

            $installationHash = $this->devices->hashInstallationId(
                (string) $payload['installation_id']
            );

            $wasKnownActiveTrustedDevice = $user
                ->devices()
                ->where('installation_id_hash', $installationHash)
                ->where('is_active', true)
                ->where('is_trusted', true)
                ->whereNull('revoked_at')
                ->exists();

            $risk = $this->riskService->analyze(
                $user,
                $payload,
                $request,
                $wasKnownActiveTrustedDevice
            );

            $device = $this->devices->bindAfterStrongLogin(
                $user,
                $payload,
                $request
            );

            $familyUuid = (string) Str::uuid();

            $access = $this->accessTokens->issue(
                $user,
                $device,
                $familyUuid
            );

            $refresh = $this->refreshTokens->issueInitial(
                $user,
                $device,
                $familyUuid
            );

            $user->forceFill([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ])->save();

            if (! $wasKnownActiveTrustedDevice) {
                AuditLog::record(
                    actorType: 'USER',
                    actorId: $user->getKey(),
                    action: 'USER_NEW_TRUSTED_DEVICE_ADDED',
                    module: 'SECURITY',
                    targetType: 'USER_DEVICE',
                    targetId: $device->getKey(),
                    newValues: [
                        'device_uuid' => $device->uuid,
                        'device_slot' => $device->device_slot,
                        'platform' => $device->platform,
                        'device_name' => $device->device_name,
                        'device_model' => $device->device_model,
                        'app_version' => $device->app_version,
                        'mfa_method' => $mfaMethod,
                        'active_trusted_devices' => $user
                            ->devices()
                            ->where('is_active', true)
                            ->where('is_trusted', true)
                            ->whereNull('revoked_at')
                            ->count(),
                    ],
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );
            }

            $this->riskService->record(
                $user,
                $device,
                $risk,
                $mfaMethod,
                $request
            );

            AuditLog::record(
                'USER',
                $user->getKey(),
                'API_LOGIN_SUCCESS',
                'AUTH',
                'USER_DEVICE',
                $device->getKey(),
                null,
                [
                    'device_slot' => $device->device_slot,
                    'platform' => $device->platform,
                    'session_uuid' => $familyUuid,
                    'mfa_method' => $mfaMethod,
                    'risk_level' => $risk['level'],
                    'risk_score' => $risk['score'],
                ],
                $request->ip(),
                $request->userAgent()
            );

            return [
                'access_token' => $access->plainTextToken,
                'token_type' => 'Bearer',
                'expires_in' => (int) config('tradex_auth.access_token_minutes', 15) * 60,
                'refresh_token' => $refresh['plain_text_token'],
                'refresh_expires_in' => (int) config('tradex_auth.refresh_token_days', 30) * 86400,
                'session_uuid' => $familyUuid,
                'mfa_method' => $mfaMethod,
                'security' => [
                    'risk_level' => $risk['level'],
                    'risk_score' => $risk['score'],
                    'signals' => $risk['signals'],
                ],
                'user' => $this->safeUser($user),
                'device' => $this->safeDevice($device),
            ];
        });
    }

    private function safeUser(User $user): array
    {
        return [
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'referral_code' => $user->referral_code,
            'country_code' => $user->country_code,
            'language_code' => $user->language_code,
            'account_type' => $user->account_type,
            'status' => $user->status,
        ];
    }

    private function safeDevice($device): array
    {
        return [
            'uuid' => $device->uuid,
            'device_slot' => $device->device_slot,
            'platform' => $device->platform,
            'device_name' => $device->device_name,
            'device_model' => $device->device_model,
            'app_version' => $device->app_version,
            'is_trusted' => (bool) $device->is_trusted,
        ];
    }
}
