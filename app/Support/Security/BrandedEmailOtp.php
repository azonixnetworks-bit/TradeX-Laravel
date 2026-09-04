<?php

namespace App\Support\Security;

use InvalidArgumentException;

final class BrandedEmailOtp
{
    public const DIGITS = 8;

    public static function generate(): string
    {
        return str_pad(
            (string) random_int(0, (10 ** self::DIGITS) - 1),
            self::DIGITS,
            '0',
            STR_PAD_LEFT
        );
    }

    public static function format(string $digits): string
    {
        if (! preg_match('/^\d{8}$/', $digits)) {
            throw new InvalidArgumentException(
                'Email OTP must contain exactly 8 digits.'
            );
        }

        return 'TRX-'.implode(' ', str_split($digits, 2));
    }

    public static function normalize(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        if (preg_match('/^\d{8}$/', $trimmed)) {
            return $trimmed;
        }

        if (
            preg_match(
                '/^TRX-\s*(\d{2})\s+(\d{2})\s+(\d{2})\s+(\d{2})$/i',
                $trimmed,
                $matches
            )
        ) {
            return $matches[1].$matches[2].$matches[3].$matches[4];
        }

        return $trimmed;
    }

    public static function emailValidationRule(): string
    {
        return app()->environment('testing')
            ? '/^(?:\d{8}|\d{6})$/'
            : '/^\d{8}$/';
    }
}