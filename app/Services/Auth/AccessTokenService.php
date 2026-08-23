<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\UserDevice;
use Laravel\Sanctum\NewAccessToken;

class AccessTokenService
{
    public function issue(
        User $user,
        UserDevice $device,
        string $sessionUuid
    ): NewAccessToken {
        $minutes = (int) config(
            'tradex_auth.access_token_minutes',
            15
        );

        $newAccessToken =
            $user->createToken(
                'tradex-mobile-access',
                ['mobile-api'],
                now()->addMinutes(
                    $minutes
                )
            );

        $newAccessToken->accessToken
            ->forceFill([
                'user_device_id' =>
                    $device->getKey(),

                'session_uuid' =>
                    $sessionUuid,
            ])
            ->save();

        return $newAccessToken;
    }

    public function revokeSession(
        User $user,
        UserDevice $device,
        string $sessionUuid
    ): int {
        return $user
            ->tokens()
            ->where(
                'user_device_id',
                $device->getKey()
            )
            ->where(
                'session_uuid',
                $sessionUuid
            )
            ->delete();
    }

    public function revokeDevice(
        User $user,
        UserDevice $device
    ): int {
        return $user
            ->tokens()
            ->where(
                'user_device_id',
                $device->getKey()
            )
            ->delete();
    }

    public function revokeAll(
        User $user
    ): int {
        return $user
            ->tokens()
            ->delete();
    }
}