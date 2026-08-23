<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Support\Str;

trait CreatesTradeXMobileSession
{
    protected function createApiUser(
        array $overrides = []
    ): User {
        $security = [
            'totp_secret' =>
                $overrides['totp_secret']
                ?? 'JBSWY3DPEHPK3PXP',

            'totp_confirmed_at' =>
                $overrides['totp_confirmed_at']
                ?? now(),
        ];

        unset(
            $overrides['totp_secret'],
            $overrides['totp_confirmed_at']
        );

        $user = User::query()
            ->create(
                array_merge(
                    [
                        'uuid' =>
                            (string) Str::uuid(),

                        'name' =>
                            'Profile User',

                        'email' =>
                            'profile-'
                            .Str::lower(
                                Str::random(8)
                            )
                            .'@example.test',

                        'email_verified_at' =>
                            now(),

                        'whatsapp_number' =>
                            '+9477'
                            .random_int(
                                1000000,
                                9999999
                            ),

                        'whatsapp_verified_at' =>
                            now(),

                        'password' =>
                            'TradeX-Profile-Password-123!',

                        'referral_code' =>
                            'TX'
                            .Str::upper(
                                Str::random(10)
                            ),

                        'country_code' =>
                            'LK',

                        'language_code' =>
                            'en',

                        'account_type' =>
                            'FREE',

                        'status' =>
                            'ACTIVE',
                    ],
                    $overrides
                )
            );

        $user->forceFill(
            $security
        )->save();

        return $user->fresh();
    }

    protected function issueBoundToken(
        User $user
    ): string {
        $device =
            UserDevice::query()
                ->create([
                    'uuid' =>
                        (string) Str::uuid(),

                    'user_id' =>
                        $user->getKey(),

                    'device_slot' =>
                        UserDevice::SLOT_MOBILE,

                    'platform' =>
                        UserDevice::PLATFORM_ANDROID,

                    'device_name' =>
                        'Test Phone',

                    'installation_id_hash' =>
                        hash(
                            'sha256',
                            (string) Str::uuid()
                        ),

                    'is_trusted' =>
                        true,

                    'is_active' =>
                        true,

                    'registered_at' =>
                        now(),

                    'trusted_at' =>
                        now(),
                ]);

        $sessionUuid =
            (string) Str::uuid();

        $token =
            $user->createToken(
                'profile-test',
                ['mobile-api'],
                now()->addMinutes(15)
            );

        $token->accessToken
            ->forceFill([
                'user_device_id' =>
                    $device->getKey(),

                'session_uuid' =>
                    $sessionUuid,
            ])
            ->save();

        return $token->plainTextToken;
    }
}
