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
    public function __construct(
        private readonly WhatsAppMetaConfigurationSafety
            $metaConfiguration
    ) {
    }

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
            trim(
                (string) config(
                    'tradex_registration.delivery.whatsapp_driver',
                    'log'
                )
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

        $this->metaConfiguration
            ->assertConfigured();

        $recipient = ltrim(
            trim($whatsappNumber),
            '+'
        );

        try {
            /*
             * Do not automatically retry this POST. A request can be accepted
             * by Meta even if the client loses the response, so an automatic
             * retry could deliver the same OTP more than once. TradeX's
             * existing controlled resend/cooldown flow remains authoritative.
             */
            $response = Http::withToken(
                $this->metaConfiguration
                    ->accessToken()
            )
                ->acceptJson()
                ->asJson()
                ->connectTimeout(
                    $this->metaConfiguration
                        ->connectTimeoutSeconds()
                )
                ->timeout(
                    $this->metaConfiguration
                        ->timeoutSeconds()
                )
                ->post(
                    $this->metaConfiguration
                        ->endpoint(),
                    [
                        'messaging_product' =>
                            'whatsapp',

                        'to' =>
                            $recipient,

                        'type' =>
                            'template',

                        'template' => [
                            'name' =>
                                $this->metaConfiguration
                                    ->templateName(),

                            'language' => [
                                'code' =>
                                    $this->metaConfiguration
                                        ->templateLanguage(),
                            ],

                            /*
                             * Meta AUTHENTICATION templates place the OTP in
                             * the body and in the OTP button parameter. The
                             * button is represented as a URL subtype when the
                             * approved authentication template is sent.
                             */
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

                                [
                                    'type' =>
                                        'button',

                                    'sub_type' =>
                                        'url',

                                    'index' =>
                                        '0',

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

            $payload = $response->json();

            $messageId =
                is_array($payload)
                    ? trim(
                        (string) data_get(
                            $payload,
                            'messages.0.id'
                        )
                    )
                    : '';

            if ($messageId === '') {
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
