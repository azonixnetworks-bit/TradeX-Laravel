<?php

namespace App\Services\Auth;

use App\Exceptions\AuthApiException;
use App\Models\User;
use InvalidArgumentException;

class TotpService
{
    public function consume(
        User $user,
        string $code,
        ?int $timestamp = null
    ): int {
        if (
            $user->totp_confirmed_at === null
            || blank($user->totp_secret)
        ) {
            throw new AuthApiException(
                409,
                'TOTP_SETUP_REQUIRED',
                'TOTP setup must be completed before login.'
            );
        }

        $step = $this->matchingStep(
            (string) $user->totp_secret,
            $code,
            $timestamp
        );

        if ($step === null) {
            throw new AuthApiException(
                422,
                'INVALID_TOTP',
                'The TOTP code is invalid or expired.'
            );
        }

        if (
            $user->totp_last_used_step !== null
            && (int) $user->totp_last_used_step >= $step
        ) {
            throw new AuthApiException(
                409,
                'TOTP_REPLAYED',
                'This TOTP code has already been used.'
            );
        }

        $user->forceFill([
            'totp_last_used_step' => $step,
        ])->save();

        return $step;
    }

    public function matchingStep(
        string $secret,
        string $code,
        ?int $timestamp = null
    ): ?int {
        $digits = (int) config(
            'tradex_auth.totp.digits',
            6
        );

        if (! preg_match(
            '/^\d{'.$digits.'}$/',
            $code
        )) {
            return null;
        }

        $period = (int) config(
            'tradex_auth.totp.period_seconds',
            30
        );

        $window = (int) config(
            'tradex_auth.totp.window',
            1
        );

        $timestamp ??= time();

        $currentStep = intdiv(
            $timestamp,
            $period
        );

        for (
            $offset = -$window;
            $offset <= $window;
            $offset++
        ) {
            $step = $currentStep + $offset;

            if ($step < 0) {
                continue;
            }

            if (hash_equals(
                $this->codeForStep(
                    $secret,
                    $step,
                    $digits
                ),
                $code
            )) {
                return $step;
            }
        }

        return null;
    }

    public function codeAt(
        string $secret,
        int $timestamp
    ): string {
        $period = (int) config(
            'tradex_auth.totp.period_seconds',
            30
        );

        $digits = (int) config(
            'tradex_auth.totp.digits',
            6
        );

        return $this->codeForStep(
            $secret,
            intdiv($timestamp, $period),
            $digits
        );
    }

    private function codeForStep(
        string $secret,
        int $step,
        int $digits
    ): string {
        $key = $this->decodeBase32(
            $secret
        );

        $counter = pack(
            'N2',
            ($step >> 32) & 0xFFFFFFFF,
            $step & 0xFFFFFFFF
        );

        $hash = hash_hmac(
            'sha1',
            $counter,
            $key,
            true
        );

        $offset =
            ord($hash[strlen($hash) - 1])
            & 0x0F;

        $binary =
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        $modulo = 10 ** $digits;

        return str_pad(
            (string) ($binary % $modulo),
            $digits,
            '0',
            STR_PAD_LEFT
        );
    }

    private function decodeBase32(
        string $secret
    ): string {
        $alphabet =
            'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

        $normalized = strtoupper(
            preg_replace(
                '/[\s=\-]+/',
                '',
                $secret
            ) ?? ''
        );

        if ($normalized === '') {
            throw new InvalidArgumentException(
                'TOTP secret is empty.'
            );
        }

        $bits = '';

        foreach (
            str_split($normalized)
            as $character
        ) {
            $position = strpos(
                $alphabet,
                $character
            );

            if ($position === false) {
                throw new InvalidArgumentException(
                    'TOTP secret contains invalid Base32 characters.'
                );
            }

            $bits .= str_pad(
                decbin($position),
                5,
                '0',
                STR_PAD_LEFT
            );
        }

        $decoded = '';

        foreach (
            str_split($bits, 8)
            as $chunk
        ) {
            if (strlen($chunk) < 8) {
                break;
            }

            $decoded .= chr(
                bindec($chunk)
            );
        }

        return $decoded;
    }
}
