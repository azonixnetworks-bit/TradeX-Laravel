<?php

namespace App\Services\Profile;

use App\Models\User;
use App\Models\UserProfile;

class ProfilePresenter
{
    public function present(
        User $user
    ): array {
        $profile =
            $user->profile;

        return [
            'identity' => [
                'uuid' =>
                    $user->uuid,

                'name' =>
                    $user->name,

                'email' =>
                    $user->email,

                'country_code' =>
                    $user->country_code,

                'account_type' =>
                    $user->account_type,

                'status' =>
                    $user->status,

                'referral_code' =>
                    $user->referral_code,

                'locked_fields' => [
                    'name',
                    'email',
                    'country_code',
                ],
            ],

            'preferences' => [
                'language_code' =>
                    $user->language_code,
            ],

            'contact' => [
                'whatsapp_number' =>
                    $user->whatsapp_number,

                'whatsapp_verified' =>
                    $user->whatsapp_verified_at
                    !== null,

                'telegram_username' =>
                    $profile
                        ?->telegram_username,

                'contact_change_security' =>
                    'TOTP_AND_EMAIL_OTP',
            ],

            'financial' =>
                $this->financial(
                    $profile
                ),

            'security' => [
                'totp_confirmed' =>
                    $user->hasConfirmedTotp(),

                'sensitive_changes_require_totp' =>
                    true,
            ],
        ];
    }

    private function financial(
        ?UserProfile $profile
    ): array {
        if (! $profile) {
            return [
                'paypal' => [
                    'configured' => false,
                    'email_masked' => null,
                ],

                'binance' => [
                    'configured' => false,
                    'email_masked' => null,
                ],

                'usdt_trc20' => [
                    'configured' => false,
                    'address_masked' => null,
                ],

                'bank' => [
                    'configured' => false,
                    'name_masked' => null,
                    'branch_masked' => null,
                    'holder_masked' => null,
                    'account_masked' => null,
                ],

                'updated_at' => null,
            ];
        }

        return [
            'paypal' => [
                'configured' =>
                    filled(
                        $profile->paypal_email
                    ),

                'email_masked' =>
                    $this->maskEmail(
                        $profile->paypal_email
                    ),
            ],

            'binance' => [
                'configured' =>
                    filled(
                        $profile->binance_email
                    ),

                'email_masked' =>
                    $this->maskEmail(
                        $profile->binance_email
                    ),
            ],

            'usdt_trc20' => [
                'configured' =>
                    filled(
                        $profile->usdt_trc20_address
                    ),

                'address_masked' =>
                    $this->maskMiddle(
                        $profile->usdt_trc20_address,
                        6,
                        6
                    ),
            ],

            'bank' => [
                'configured' =>
                    filled(
                        $profile->bank_account_number
                    ),

                'name_masked' =>
                    $this->maskText(
                        $profile->bank_name
                    ),

                'branch_masked' =>
                    $this->maskText(
                        $profile->bank_branch
                    ),

                'holder_masked' =>
                    $this->maskText(
                        $profile->bank_account_holder
                    ),

                'account_masked' =>
                    $this->maskMiddle(
                        $profile->bank_account_number,
                        2,
                        4
                    ),
            ],

            'updated_at' =>
                $profile
                    ->financial_updated_at
                    ?->toIso8601String(),
        ];
    }

    private function maskEmail(
        ?string $value
    ): ?string {
        if (blank($value)) {
            return null;
        }

        [$local, $domain] =
            array_pad(
                explode(
                    '@',
                    $value,
                    2
                ),
                2,
                ''
            );

        return mb_substr(
            $local,
            0,
            2
        )
            .'***@'
            .$domain;
    }

    private function maskText(
        ?string $value
    ): ?string {
        if (blank($value)) {
            return null;
        }

        $length =
            mb_strlen(
                $value
            );

        if ($length <= 4) {
            return str_repeat(
                '*',
                $length
            );
        }

        return mb_substr(
            $value,
            0,
            2
        )
            .str_repeat(
                '*',
                max(
                    2,
                    $length - 4
                )
            )
            .mb_substr(
                $value,
                -2
            );
    }

    private function maskMiddle(
        ?string $value,
        int $start,
        int $end
    ): ?string {
        if (blank($value)) {
            return null;
        }

        $length =
            strlen(
                $value
            );

        if (
            $length
            <= ($start + $end)
        ) {
            return str_repeat(
                '*',
                $length
            );
        }

        return substr(
            $value,
            0,
            $start
        )
            .'***'
            .substr(
                $value,
                -$end
            );
    }
}
