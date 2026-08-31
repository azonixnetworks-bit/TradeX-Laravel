<?php

namespace App\Services\Payments;

use App\Exceptions\AuthApiException;

class ScaledDecimal
{
    public function normalize(
        string $value,
        int $scale
    ): string {
        $this->assertScale($scale);

        $value = trim($value);

        $pattern = $scale === 0
            ? '/^\d{1,18}$/'
            : '/^\d{1,18}(?:\.\d{1,'
                .$scale
                .'})?$/';

        if (
            preg_match(
                $pattern,
                $value
            ) !== 1
        ) {
            throw new AuthApiException(
                422,
                'PAYMENT_AMOUNT_INVALID',
                'The payment amount does not match the configured precision.'
            );
        }

        [$whole, $fraction] =
            array_pad(
                explode(
                    '.',
                    $value,
                    2
                ),
                2,
                ''
            );

        $whole =
            ltrim(
                $whole,
                '0'
            ) ?: '0';

        if ($scale === 0) {
            return $whole;
        }

        $fraction = str_pad(
            $fraction,
            $scale,
            '0'
        );

        return $whole
            .'.'
            .$fraction;
    }

    public function toMinor(
        string $value,
        int $scale
    ): int {
        if (
            $scale < 0
            || $scale > 8
        ) {
            throw new AuthApiException(
                422,
                'PAYMENT_MINOR_SCALE_UNSUPPORTED',
                'This payment amount cannot be represented safely in the minor-unit ledger.'
            );
        }

        $normalized =
            $this->normalize(
                $value,
                $scale
            );

        [$whole, $fraction] =
            array_pad(
                explode(
                    '.',
                    $normalized,
                    2
                ),
                2,
                ''
            );

        $factor =
            10 ** $scale;

        $fraction = str_pad(
            $fraction,
            $scale,
            '0'
        );

        $wholeInt =
            (int) $whole;

        $fractionInt =
            $fraction === ''
                ? 0
                : (int) $fraction;

        if (
            $wholeInt
            > intdiv(
                PHP_INT_MAX
                    - $fractionInt,
                $factor
            )
        ) {
            throw new AuthApiException(
                422,
                'PAYMENT_AMOUNT_TOO_LARGE',
                'The configured payment amount exceeds the supported numeric range.'
            );
        }

        return (
            $wholeInt
                * $factor
        )
            + $fractionInt;
    }

    public function formatMinor(
        int $minor,
        int $scale
    ): string {
        if (
            $scale < 0
            || $scale > 8
        ) {
            throw new AuthApiException(
                422,
                'PAYMENT_MINOR_SCALE_UNSUPPORTED',
                'This payment amount cannot be formatted from the minor-unit ledger.'
            );
        }

        $factor =
            10 ** $scale;

        $whole =
            intdiv(
                $minor,
                $factor
            );

        if ($scale === 0) {
            return (string) $whole;
        }

        $fraction =
            str_pad(
                (string) (
                    $minor
                        % $factor
                ),
                $scale,
                '0',
                STR_PAD_LEFT
            );

        return $whole
            .'.'
            .$fraction;
    }

    private function assertScale(
        int $scale
    ): void {
        if (
            $scale < 0
            || $scale > 18
        ) {
            throw new AuthApiException(
                422,
                'PAYMENT_AMOUNT_SCALE_INVALID',
                'Payment precision must be between 0 and 18 decimal places.'
            );
        }
    }
}
