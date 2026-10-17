<?php

namespace App\Services\Auth;

use App\Exceptions\AuthApiException;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserDeviceQuickUnlockSetting;
use App\Services\Security\QuickUnlock\QuickUnlockPolicyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginService
{
    public function __construct(
        private readonly TotpService $totp,
        private readonly MfaRecoveryCodeService $recoveryCodes,
        private readonly DeviceBindingService $devices,
        private readonly AccessTokenService $accessTokens,
        private readonly RefreshTokenService $refreshTokens,
        private readonly QuickUnlockPolicyService $quickUnlockPolicy
    ) {}

    public function login(
        array $payload,
        Request $request
    ): array {
        $normalizedEmail = mb_strtolower(trim((string) $payload['email']));
        $lockoutKey = 'api-login-account|'.hash('sha256', $normalizedEmail);
        $maximumAttempts = max(1, (int) config(
            'tradex_auth.rate_limits.login_failed_attempts',
            5
        ));

        if (RateLimiter::tooManyAttempts($lockoutKey, $maximumAttempts)) {
            throw new AuthApiException(
                429,
                'ACCOUNT_LOGIN_LOCKED',
                'Too many failed login attempts. Try again later.'
            );
        }

        $candidate = User::query()
            ->whereRaw(
                'LOWER(email) = ?',
                [$normalizedEmail]
            )
            ->first();

        if (
            $candidate === null
            || ! Hash::check(
                (string) $payload['password'],
                (string) $candidate->password
            )
        ) {
            RateLimiter::hit(
                $lockoutKey,
                max(1, (int) config(
                    'tradex_auth.rate_limits.login_lockout_seconds',
                    900
                ))
            );

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

        $result = DB::transaction(
            function () use (
                $candidate,
                $payload,
                $request
            ): array {
                $user = User::query()
                    ->whereKey(
                        $candidate->getKey()
                    )
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

                $device =
                    $this->devices
                        ->bindAfterStrongLogin(
                            $user,
                            $payload,
                            $request
                        );

                $quickUnlockMethod =
                    $this->enableQuickUnlockAfterStrongLogin(
                        $user,
                        $device,
                        $payload,
                        $request
                    );

                $familyUuid =
                    (string) Str::uuid();

                $access =
                    $this->accessTokens
                        ->issue(
                            $user,
                            $device,
                            $familyUuid
                        );

                $refresh =
                    $this->refreshTokens
                        ->issueInitial(
                            $user,
                            $device,
                            $familyUuid
                        );

                $user->forceFill([
                    'last_login_at' => now(),
                    'last_login_ip' => $request->ip(),
                ])->save();

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
                        'quick_unlock_method' => $quickUnlockMethod,
                    ],
                    $request->ip(),
                    $request->userAgent()
                );

                return [
                    'access_token' => $access->plainTextToken,
                    'token_type' => 'Bearer',
                    'expires_in' => (int) config(
                        'tradex_auth.access_token_minutes',
                        15
                    ) * 60,
                    'refresh_token' => $refresh[
                            'plain_text_token'
                        ],
                    'refresh_expires_in' => (int) config(
                        'tradex_auth.refresh_token_days',
                        30
                    ) * 86400,
                    'session_uuid' => $familyUuid,
                    'mfa_method' => $mfaMethod,
                    'user' => $this->safeUser(
                        $user
                    ),
                    'device' => $this->safeDevice(
                        $device
                    ),
                    'quick_unlock_method' => $quickUnlockMethod,
                ];
            }
        );

        RateLimiter::clear($lockoutKey);

        return $result;
    }

    private function enableQuickUnlockAfterStrongLogin(
        User $user,
        UserDevice $device,
        array $payload,
        Request $request
    ): ?string {
        $method = $payload['quick_unlock_method'] ?? null;

        if (! is_string($method) || $method === '') {
            return null;
        }

        if (! $device->isMobile() || $device->trusted_at === null) {
            throw new AuthApiException(
                422,
                'QUICK_UNLOCK_MOBILE_ONLY',
                'Quick unlock is available only on a trusted mobile device.'
            );
        }

        if (
            $method === UserDeviceQuickUnlockSetting::METHOD_BIOMETRIC
            && ! (bool) config(
                'tradex_mobile_security.quick_unlock.biometric_allowed',
                true
            )
        ) {
            throw new AuthApiException(
                409,
                'QUICK_UNLOCK_BIOMETRIC_DISABLED',
                'Fingerprint quick unlock is currently disabled by policy.'
            );
        }

        UserDeviceQuickUnlockSetting::query()->updateOrCreate(
            ['user_device_id' => $device->getKey()],
            [
                'enabled' => true,
                'method' => $method,
                'trusted_at_snapshot' => $device->trusted_at,
                'enrolled_at' => now(),
                'disabled_at' => null,
            ]
        );

        AuditLog::record(
            'USER',
            $user->getKey(),
            'QUICK_UNLOCK_ENABLED',
            'SECURITY',
            'USER_DEVICE',
            $device->getKey(),
            null,
            [
                'method' => $method,
                'enrollment' => 'STRONG_LOGIN',
                'policy_version' => $this->quickUnlockPolicy->policy()['version'],
            ],
            $request->ip(),
            $request->userAgent()
        );

        return $method;
    }

    private function safeUser(
        User $user
    ): array {
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

    private function safeDevice(
        $device
    ): array {
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
