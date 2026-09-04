<?php

namespace App\Services\Auth\PasswordRecovery;

use App\Exceptions\AuthApiException;
use App\Models\PasswordRecoverySession;
use App\Support\Security\BrandedEmailOtp;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Throwable;

class PasswordRecoveryDeliveryService
{
    public function sendEmailOtp(string $email, string $code): void
    {
        $driver = strtolower(
            (string) config(
                'tradex_password_recovery.delivery.email_driver',
                'mail'
            )
        );

        if ($driver === 'null') {
            $this->assertNullDriverAllowed();
            return;
        }

        if ($driver !== 'mail') {
            throw new AuthApiException(
                503,
                'PASSWORD_RECOVERY_EMAIL_DRIVER_INVALID',
                'Password recovery email delivery is not configured.'
            );
        }

        try {
            $displayCode = BrandedEmailOtp::format($code);

            Mail::raw(
                "Your TradeX OS password recovery code is {$displayCode}. "
                ."This code expires soon. Do not share it with anyone.",
                function ($message) use ($email): void {
                    $message
                        ->to($email)
                        ->subject('TradeX OS password recovery code');
                }
            );
        } catch (Throwable) {
            throw new AuthApiException(
                503,
                'PASSWORD_RECOVERY_EMAIL_DELIVERY_FAILED',
                'Password recovery email could not be delivered.'
            );
        }
    }

    public function sendWhatsAppOtp(string $whatsappNumber, string $code): void
    {
        $driver = strtolower(
            (string) config(
                'tradex_password_recovery.delivery.whatsapp_driver',
                'log'
            )
        );

        if ($driver === 'null') {
            $this->assertNullDriverAllowed();
            return;
        }

        if ($driver !== 'meta') {
            throw new AuthApiException(
                503,
                'PASSWORD_RECOVERY_WHATSAPP_DRIVER_INVALID',
                'Password recovery WhatsApp delivery is not configured.'
            );
        }

        $graphVersion = (string) config(
            'tradex_password_recovery.delivery.meta.graph_version'
        );
        $phoneNumberId = (string) config(
            'tradex_password_recovery.delivery.meta.phone_number_id'
        );
        $accessToken = (string) config(
            'tradex_password_recovery.delivery.meta.access_token'
        );
        $templateName = (string) config(
            'tradex_password_recovery.delivery.meta.otp_template_name'
        );
        $templateLanguage = (string) config(
            'tradex_password_recovery.delivery.meta.otp_template_language',
            'en_US'
        );

        if (
            ! preg_match('/^v\d+\.\d+$/', $graphVersion)
            || blank($phoneNumberId)
            || blank($accessToken)
            || blank($templateName)
        ) {
            throw new AuthApiException(
                503,
                'PASSWORD_RECOVERY_WHATSAPP_NOT_CONFIGURED',
                'Password recovery WhatsApp provider credentials are incomplete.'
            );
        }

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->asJson()
                ->timeout(10)
                ->post(
                    "https://graph.facebook.com/{$graphVersion}/{$phoneNumberId}/messages",
                    [
                        'messaging_product' => 'whatsapp',
                        'to' => ltrim($whatsappNumber, '+'),
                        'type' => 'template',
                        'template' => [
                            'name' => $templateName,
                            'language' => ['code' => $templateLanguage],
                            'components' => [
                                [
                                    'type' => 'body',
                                    'parameters' => [
                                        ['type' => 'text', 'text' => $code],
                                    ],
                                ],
                            ],
                        ],
                    ]
                );

            if ($response->failed()) {
                throw new AuthApiException(
                    503,
                    'PASSWORD_RECOVERY_WHATSAPP_DELIVERY_FAILED',
                    'Password recovery WhatsApp code could not be delivered.'
                );
            }
        } catch (AuthApiException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new AuthApiException(
                503,
                'PASSWORD_RECOVERY_WHATSAPP_DELIVERY_FAILED',
                'Password recovery WhatsApp code could not be delivered.'
            );
        }
    }

    public function sendResetLink(
        string $email,
        PasswordRecoverySession $session,
        string $resetToken
    ): void {
        $driver = strtolower(
            (string) config(
                'tradex_password_recovery.delivery.email_driver',
                'mail'
            )
        );

        if ($driver === 'null') {
            $this->assertNullDriverAllowed();
            return;
        }

        if ($driver !== 'mail') {
            throw new AuthApiException(
                503,
                'PASSWORD_RECOVERY_EMAIL_DRIVER_INVALID',
                'Password recovery email delivery is not configured.'
            );
        }

        $baseUrl = rtrim(
            (string) config(
                'tradex_password_recovery.reset_link_url',
                'tradex://password-reset'
            ),
            '?&'
        );

        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        $url = $baseUrl
            .$separator
            .http_build_query([
                'recovery_id' => $session->uuid,
                'reset_token' => $resetToken,
            ]);

        try {
            Mail::raw(
                "Use this TradeX OS password reset link: {$url}\n\n"
                ."The link expires soon. If you did not request this, ignore this email.",
                function ($message) use ($email): void {
                    $message
                        ->to($email)
                        ->subject('Reset your TradeX OS password');
                }
            );
        } catch (Throwable) {
            throw new AuthApiException(
                503,
                'PASSWORD_RECOVERY_RESET_LINK_DELIVERY_FAILED',
                'Password reset link could not be delivered.'
            );
        }
    }

    private function assertNullDriverAllowed(): void
    {
        if (! app()->environment('testing')) {
            throw new AuthApiException(
                503,
                'PASSWORD_RECOVERY_NULL_DRIVER_FORBIDDEN',
                'Password recovery null delivery is only available during automated tests.'
            );
        }
    }
}
