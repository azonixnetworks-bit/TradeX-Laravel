<?php

namespace App\Services\Auth\Registration;

use App\Exceptions\AuthApiException;
use App\Models\UserRegistrationSession;

class TotpProvisioningService
{
    public function __construct(
        private TotpQrCodeService $qrCodes
    ) {
    }

    public function setup(
        UserRegistrationSession $session
    ): array {
        if (
            $session->email_verified_at
            === null
            || $session->whatsapp_verified_at
            === null
        ) {
            throw new AuthApiException(
                409,
                'CONTACT_VERIFICATION_REQUIRED',
                'Email and WhatsApp verification must be completed first.'
            );
        }

        if (
            blank(
                $session->totp_secret
            )
        ) {
            $session->forceFill([
                'totp_secret' =>
                    $this->generateSecret(),

                'totp_setup_at' =>
                    now(),
            ])->save();

            $session->refresh();
        }

        $issuer = (string) config(
            'tradex_registration.totp_issuer',
            'TradeX OS'
        );

        $digits = (int) config(
            'tradex_auth.totp.digits',
            6
        );

        $period = (int) config(
            'tradex_auth.totp.period_seconds',
            30
        );

        $label =
            rawurlencode(
                $issuer
                .':'
                .$session->email
            );

        $uri =
            'otpauth://totp/'
            .$label
            .'?secret='
            .rawurlencode(
                (string)
                    $session->totp_secret
            )
            .'&issuer='
            .rawurlencode(
                $issuer
            )
            .'&algorithm=SHA1'
            .'&digits='
            .$digits
            .'&period='
            .$period;

        return [
            'secret' =>
                (string)
                    $session->totp_secret,

            'otpauth_uri' =>
                $uri,

            'qr_code_data_uri' =>
                $this->qrCodes->dataUri($uri),

            'qr_code_mime_type' =>
                'image/svg+xml',

            'issuer' =>
                $issuer,

            'account_name' =>
                $session->email,

            'digits' =>
                $digits,

            'period_seconds' =>
                $period,

            'next_action' =>
                'CONFIRM_TOTP',
        ];
    }

    private function generateSecret(): string
    {
        return $this->base32Encode(
            random_bytes(20)
        );
    }

    private function base32Encode(
        string $binary
    ): string {
        $alphabet =
            'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

        $bits = '';

        foreach (
            unpack('C*', $binary)
            as $byte
        ) {
            $bits .= str_pad(
                decbin($byte),
                8,
                '0',
                STR_PAD_LEFT
            );
        }

        $encoded = '';

        foreach (
            str_split($bits, 5)
            as $chunk
        ) {
            if (
                strlen($chunk) < 5
            ) {
                $chunk = str_pad(
                    $chunk,
                    5,
                    '0',
                    STR_PAD_RIGHT
                );
            }

            $encoded .=
                $alphabet[
                    bindec($chunk)
                ];
        }

        return $encoded;
    }
}
