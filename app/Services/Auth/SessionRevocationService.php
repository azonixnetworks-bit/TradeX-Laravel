<?php

namespace App\Services\Auth;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserRefreshToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SessionRevocationService
{
    public function __construct(
        private readonly AccessTokenService
            $accessTokens
    ) {
    }

    public function logoutCurrent(
        User $user,
        UserDevice $device,
        string $sessionUuid,
        Request $request
    ): void {
        DB::transaction(
            function () use (
                $user,
                $device,
                $sessionUuid,
                $request
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
                        $sessionUuid
                    )
                    ->whereNull(
                        'revoked_at'
                    )
                    ->update([
                        'revoked_at' => now(),
                        'revoke_reason' =>
                            'USER_LOGOUT',
                    ]);

                $this->accessTokens
                    ->revokeSession(
                        $user,
                        $device,
                        $sessionUuid
                    );

                AuditLog::record(
                    'USER',
                    $user->getKey(),
                    'API_LOGOUT',
                    'AUTH',
                    'USER_DEVICE',
                    $device->getKey(),
                    null,
                    [
                        'session_uuid' =>
                            $sessionUuid,
                    ],
                    $request->ip(),
                    $request->userAgent()
                );
            }
        );

        $this->forgetSanctumUser();
    }

    public function logoutAll(
        User $user,
        Request $request
    ): void {
        DB::transaction(
            function () use (
                $user,
                $request
            ): void {
                $refreshCount =
                    UserRefreshToken::query()
                        ->where(
                            'user_id',
                            $user->getKey()
                        )
                        ->whereNull(
                            'revoked_at'
                        )
                        ->update([
                            'revoked_at' => now(),
                            'revoke_reason' =>
                                'USER_LOGOUT_ALL',
                        ]);

                $accessCount =
                    $this->accessTokens
                        ->revokeAll(
                            $user
                        );

                AuditLog::record(
                    'USER',
                    $user->getKey(),
                    'API_LOGOUT_ALL',
                    'AUTH',
                    'USER',
                    $user->getKey(),
                    null,
                    [
                        'refresh_tokens_revoked' =>
                            $refreshCount,
                        'access_tokens_revoked' =>
                            $accessCount,
                    ],
                    $request->ip(),
                    $request->userAgent()
                );
            }
        );

        $this->forgetSanctumUser();
    }

    public function revokeCurrentDevice(
        User $user,
        UserDevice $device,
        Request $request
    ): void {
        DB::transaction(
            function () use (
                $user,
                $device,
                $request
            ): void {
                $device->forceFill([
                    'is_active' => false,
                    'revoked_at' => now(),
                    'revoke_reason' =>
                        'USER_SELF_REVOKE',
                ])->save();

                UserRefreshToken::query()
                    ->where(
                        'user_id',
                        $user->getKey()
                    )
                    ->where(
                        'user_device_id',
                        $device->getKey()
                    )
                    ->whereNull(
                        'revoked_at'
                    )
                    ->update([
                        'revoked_at' => now(),
                        'revoke_reason' =>
                            'USER_DEVICE_REVOKE',
                    ]);

                $this->accessTokens
                    ->revokeDevice(
                        $user,
                        $device
                    );

                AuditLog::record(
                    'USER',
                    $user->getKey(),
                    'API_DEVICE_SELF_REVOKE',
                    'AUTH',
                    'USER_DEVICE',
                    $device->getKey(),
                    null,
                    [
                        'device_slot' =>
                            $device->device_slot,
                    ],
                    $request->ip(),
                    $request->userAgent()
                );
            }
        );

        $this->forgetSanctumUser();
    }

    private function forgetSanctumUser(): void
    {
        Auth::guard('sanctum')
            ->forgetUser();
    }
}