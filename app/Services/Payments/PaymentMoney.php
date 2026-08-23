<?php

namespace App\Services\Payments;

use App\Exceptions\AuthApiException;

class PaymentMoney
{
    public function scaleForCurrency(
        string $currency
    ): int {
        return strtoupper($currency) === 'USDT'
            ? 6
            : 2;
    }

    public function present(
        int $amountMinor,
        string $currency
    ): array {
        $currency = strtoupper($currency);
        $scale = $this->scaleForCurrency(
            $currency
        );

        return [
            'currency' => $currency,
            'amount_minor' => $amountMinor,
            'scale' => $scale,
            'amount' => $this->formatMinor(
                $amountMinor,
                $scale
            ),
        ];
    }

    public function decimalToMinor(
        string $value,
        string $currency
    ): int {
        $scale = $this->scaleForCurrency(
            $currency
        );

        $value = trim($value);

        if (! preg_match(
            '/^\d+(?:\.\d{1,'.$scale.'})?$/',
            $value
        )) {
            throw new AuthApiException(
                502,
                'PAYMENT_PROVIDER_AMOUNT_INVALID',
                'The payment provider returned an invalid amount.'
            );
        }

        [$whole, $fraction] = array_pad(
            explode('.', $value, 2),
            2,
            ''
        );

        $fraction = str_pad(
            $fraction,
            $scale,
            '0'
        );

        return ((int) $whole * (10 ** $scale))
            + (int) $fraction;
    }

    private function formatMinor(
        int $amountMinor,
        int $scale
    ): string {
        $negative = $amountMinor < 0;
        $absolute = abs($amountMinor);
        $factor = 10 ** $scale;

        $whole = intdiv(
            $absolute,
            $factor
        );

        $fraction = str_pad(
            (string) ($absolute % $factor),
            $scale,
            '0',
            STR_PAD_LEFT
        );

        return ($negative ? '-' : '')
            .$whole
            .'.'
            .$fraction;
    }
}
