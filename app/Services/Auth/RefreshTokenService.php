<?php

namespace App\Services\Auth;

use App\Exceptions\AuthApiException;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserRefreshToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RefreshTokenService
{
    public function __construct(
        private readonly AccessTokenService
            $accessTokens,
        private readonly DeviceBindingService
            $deviceBinding
    ) {
    }

    public function issueInitial(
        User $user,
        UserDevice $device,
        string $familyUuid
    ): array {
        return $this->createRefreshToken(
            $user,
            $device,
            $familyUuid
        );
    }

    public function rotate(
        string $plainTextToken,
        string $installationId,
        Request $request
    ): array {
        $tokenHash = hash(
            'sha256',
            $plainTextToken
        );

        $token = UserRefreshToken::query()
            ->where(
                'token_hash',
                $tokenHash
            )
            ->first();

        if ($token === null) {
            throw new AuthApiException(
                401,
                'INVALID_REFRESH_TOKEN',
                'The refresh token is invalid.'
            );
        }

        $user = $token->user;
        $device = $token->device;

        if (
            ! $user instanceof User
            || ! $device instanceof UserDevice
        ) {
            throw new AuthApiException(
                401,
                'INVALID_REFRESH_TOKEN',
                'The refresh token session is invalid.'
            );
        }

        if (! $user->canLogin()) {
            throw new AuthApiException(
                403,
                'ACCOUNT_NOT_ACTIVE',
                'This account is not allowed to access the API.'
            );
        }

        if (
            ! hash_equals(
                (string) $device
                    ->installation_id_hash,
                $this->deviceBinding
                    ->hashInstallationId(
                        $installationId
                    )
            )
        ) {
            throw new AuthApiException(
                401,
                'DEVICE_BINDING_MISMATCH',
                'The refresh token is not valid for this device installation.'
            );
        }

        if (
            $device->isRevoked()
            || ! $device->is_trusted
        ) {
            throw new AuthApiException(
                401,
                'DEVICE_SESSION_REVOKED',
                'The device session is no longer active.'
            );
        }

        $result = DB::transaction(
            function () use (
                $token,
                $user,
                $device,
                $request
            ): array {
                $locked =
                    UserRefreshToken::query()
                        ->whereKey(
                            $token->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if ($locked->isRevoked()) {
                    $this->revokeFamily(
                        $user,
                        $device,
                        $locked->family_uuid,
                        'SECURITY_REFRESH_REUSE'
                    );

                    AuditLog::record(
                        'USER',
                        $user->getKey(),
                        'API_REFRESH_REUSE_DETECTED',
                        'AUTH',
                        'USER_DEVICE',
                        $device->getKey(),
                        null,
                        [
                            'family_uuid' =>
                                $locked
                                    ->family_uuid,
                        ],
                        $request->ip(),
                        $request->userAgent()
                    );

                    return [
                        'status' => 'reuse',
                    ];
                }

                if ($locked->isExpired()) {
                    $locked->forceFill([
                        'revoked_at' => now(),
                        'revoke_reason' =>
                            'EXPIRED',
                    ])->save();

                    return [
                        'status' => 'expired',
                    ];
                }

                $locked->forceFill([
                    'last_used_at' => now(),
                    'revoked_at' => now(),
                    'revoke_reason' =>
                        'ROTATED',
                ])->save();

                $this->accessTokens
                    ->revokeSession(
                        $user,
                        $device,
                        $locked->family_uuid
                    );

                $newRefresh =
                    $this->createRefreshToken(
                        $user,
                        $device,
                        $locked->family_uuid
                    );

                $newAccess =
                    $this->accessTokens
                        ->issue(
                            $user,
                            $device,
                            $locked->family_uuid
                        );

                $device->forceFill([
                    'last_seen_at' => now(),
                ])->save();

                AuditLog::record(
                    'USER',
                    $user->getKey(),
                    'API_REFRESH_ROTATED',
                    'AUTH',
                    'USER_DEVICE',
                    $device->getKey(),
                    null,
                    [
                        'family_uuid' =>
                            $locked
                                ->family_uuid,
                    ],
                    $request->ip(),
                    $request->userAgent()
                );

                return [
                    'status' => 'rotated',
                    'access' => $newAccess,
                    'refresh' => $newRefresh,
                ];
            }
        );

        if (
            $result['status']
            === 'reuse'
        ) {
            throw new AuthApiException(
                401,
                'REFRESH_TOKEN_REUSED',
                'Refresh token reuse was detected. The session family has been revoked.'
            );
        }

        if (
            $result['status']
            === 'expired'
        ) {
            throw new AuthApiException(
                401,
                'REFRESH_TOKEN_EXPIRED',
                'The refresh token has expired.'
            );
        }

        return [
            'access_token' =>
                $result['access']
                    ->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' =>
                (int) config(
                    'tradex_auth.access_token_minutes',
                    15
                ) * 60,
            'refresh_token' =>
                $result['refresh']
                    ['plain_text_token'],
            'refresh_expires_in' =>
                (int) config(
                    'tradex_auth.refresh_token_days',
                    30
                ) * 86400,
            'session_uuid' =>
                $result['refresh']
                    ['model']
                    ->family_uuid,
        ];
    }

    public function revokeFamily(
        User $user,
        UserDevice $device,
        string $familyUuid,
        string $reason
    ): void {
        UserRefreshToken::query()
            ->where(
                'user_id',
                $user->getKey()
            )
            ->where(
                'user_device_id',
                $device->getKey()
            )
            ->where(
                'family_uuid',
                $familyUuid
            )
            ->whereNull(
                'revoked_at'
            )
            ->update([
                'revoked_at' => now(),
                'revoke_reason' => $reason,
            ]);

        $this->accessTokens
            ->revokeSession(
                $user,
                $device,
                $familyUuid
            );
    }

    private function createRefreshToken(
        User $user,
        UserDevice $device,
        string $familyUuid
    ): array {
        $plainTextToken =
            Str::random(96)
            .Str::random(96);

        $model =
            UserRefreshToken::query()
                ->create([
                    'uuid' =>
                        (string) Str::uuid(),
                    'user_id' =>
                        $user->getKey(),
                    'user_device_id' =>
                        $device->getKey(),
                    'token_hash' =>
                        hash(
                            'sha256',
                            $plainTextToken
                        ),
                    'family_uuid' =>
                        $familyUuid,
                    'expires_at' =>
                        now()->addDays(
                            (int) config(
                                'tradex_auth.refresh_token_days',
                                30
                            )
                        ),
                ]);

        return [
            'model' => $model,
            'plain_text_token' =>
                $plainTextToken,
        ];
    }
}
