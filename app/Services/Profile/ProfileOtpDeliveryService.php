<?php

namespace App\Services\Profile;

use App\Exceptions\AuthApiException;
use App\Support\Security\BrandedEmailOtp;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ProfileOtpDeliveryService
{
    public function sendEmail(
        string $email,
        string $code,
        string $purpose
    ): void {
        $driver = strtolower(
            (string) config(
                'tradex_profile.contact_change.email_driver',
                'mail'
            )
        );

        if ($driver === 'log') {
            if (
                app()->environment(
                    'production'
                )
            ) {
                throw new AuthApiException(
                    503,
                    'PROFILE_OTP_LOG_DRIVER_FORBIDDEN',
                    'Profile OTP log delivery is disabled in production.'
                );
            }

            Log::info(
                'TradeX profile change email OTP.',
                [
                    'email' =>
                        $this->maskEmail(
                            $email
                        ),

                    'purpose' =>
                        $purpose,

                    'otp' =>
                        $code,
                ]
            );

            return;
        }

        if ($driver !== 'mail') {
            throw new AuthApiException(
                503,
                'PROFILE_EMAIL_OTP_DRIVER_INVALID',
                'Profile verification email delivery is not configured.'
            );
        }

        try {
            $displayCode = BrandedEmailOtp::format($code);

            Mail::raw(
                "Your TradeX OS profile-change verification code is {$displayCode}. "
                ."This code expires soon. Do not share it with anyone.",
                function ($message) use (
                    $email
                ): void {
                    $message
                        ->to($email)
                        ->subject(
                            'TradeX OS profile verification code'
                        );
                }
            );
        } catch (Throwable) {
            throw new AuthApiException(
                503,
                'PROFILE_EMAIL_OTP_DELIVERY_FAILED',
                'Profile verification code could not be delivered.'
            );
        }
    }

    private function maskEmail(
        string $email
    ): string {
        [$local, $domain] =
            array_pad(
                explode(
                    '@',
                    $email,
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
}
