<?php

namespace App\Services\Auth\Registration;

use App\Exceptions\AuthApiException;
use App\Support\Security\BrandedEmailOtp;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class RegistrationOtpDeliveryService
{
    public function sendEmail(
        string $email,
        string $code
    ): void {
        $driver = strtolower(
            (string) config(
                'tradex_registration.delivery.email_driver',
                'mail'
            )
        );

        if ($driver === 'log') {
            $this->assertLogDriverAllowed();

            Log::info(
                'TradeX registration email OTP.',
                [
                    'email' =>
                        $this->maskEmail(
                            $email
                        ),
                    'otp' => $code,
                ]
            );

            return;
        }

        if ($driver !== 'mail') {
            throw new AuthApiException(
                503,
                'EMAIL_OTP_DRIVER_INVALID',
                'Email OTP delivery is not configured.'
            );
        }

        try {
            $displayCode = BrandedEmailOtp::format($code);

            Mail::raw(
                "Your TradeX OS verification code is {$displayCode}. "
                ."This code expires soon. Do not share it with anyone.",
                function ($message) use (
                    $email
                ): void {
                    $message
                        ->to($email)
                        ->subject(
                            'TradeX OS verification code'
                        );
                }
            );
        } catch (Throwable) {
            throw new AuthApiException(
                503,
                'EMAIL_OTP_DELIVERY_FAILED',
                'Email verification code could not be delivered.'
            );
        }
    }

    public function sendWhatsApp(
        string $whatsappNumber,
        string $code
    ): void {
        $driver = strtolower(
            (string) config(
                'tradex_registration.delivery.whatsapp_driver',
                'log'
            )
        );

        if ($driver === 'log') {
            $this->assertLogDriverAllowed();

            Log::info(
                'TradeX registration WhatsApp OTP.',
                [
                    'whatsapp' =>
                        $this->maskPhone(
                            $whatsappNumber
                        ),
                    'otp' => $code,
                ]
            );

            return;
        }

        if ($driver !== 'meta') {
            throw new AuthApiException(
                503,
                'WHATSAPP_OTP_DRIVER_INVALID',
                'WhatsApp OTP delivery is not configured.'
            );
        }

        $graphVersion = (string) config(
            'tradex_registration.delivery.meta.graph_version'
        );

        $phoneNumberId = (string) config(
            'tradex_registration.delivery.meta.phone_number_id'
        );

        $accessToken = (string) config(
            'tradex_registration.delivery.meta.access_token'
        );

        $templateName = (string) config(
            'tradex_registration.delivery.meta.otp_template_name'
        );

        $templateLanguage = (string) config(
            'tradex_registration.delivery.meta.otp_template_language',
            'en_US'
        );

        if (
            ! preg_match(
                '/^v\d+\.\d+$/',
                $graphVersion
            )
            || blank($phoneNumberId)
            || blank($accessToken)
            || blank($templateName)
        ) {
            throw new AuthApiException(
                503,
                'WHATSAPP_OTP_NOT_CONFIGURED',
                'WhatsApp OTP provider credentials are incomplete.'
            );
        }

        try {
            $response = Http::withToken(
                $accessToken
            )
                ->acceptJson()
                ->asJson()
                ->timeout(10)
                ->post(
                    "https://graph.facebook.com/{$graphVersion}/{$phoneNumberId}/messages",
                    [
                        'messaging_product' =>
                            'whatsapp',

                        'to' =>
                            ltrim(
                                $whatsappNumber,
                                '+'
                            ),

                        'type' =>
                            'template',

                        'template' => [
                            'name' =>
                                $templateName,

                            'language' => [
                                'code' =>
                                    $templateLanguage,
                            ],

                            'components' => [
                                [
                                    'type' =>
                                        'body',

                                    'parameters' => [
                                        [
                                            'type' =>
                                                'text',

                                            'text' =>
                                                $code,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ]
                );

            if ($response->failed()) {
                throw new AuthApiException(
                    503,
                    'WHATSAPP_OTP_DELIVERY_FAILED',
                    'WhatsApp verification code could not be delivered.'
                );
            }
        } catch (AuthApiException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new AuthApiException(
                503,
                'WHATSAPP_OTP_DELIVERY_FAILED',
                'WhatsApp verification code could not be delivered.'
            );
        }
    }

    private function assertLogDriverAllowed(): void
    {
        if (app()->environment('production')) {
            throw new AuthApiException(
                503,
                'OTP_LOG_DRIVER_FORBIDDEN',
                'OTP log delivery is disabled in production.'
            );
        }
    }

    private function maskEmail(
        string $email
    ): string {
        [$local, $domain] =
            array_pad(
                explode('@', $email, 2),
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

    private function maskPhone(
        string $phone
    ): string {
        return mb_substr(
            $phone,
            0,
            4
        )
            .'***'
            .mb_substr(
                $phone,
                -3
            );
    }
}
