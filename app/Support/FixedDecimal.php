<?php

namespace App\Support;

use DomainException;

final class FixedDecimal
{
    public static function normalize(string $value, int $scale = 8): string
    {
        return self::formatScaledInteger(
            self::toScaledInteger($value, $scale),
            $scale
        );
    }

    public static function add(string $left, string $right, int $scale = 8): string
    {
        return self::formatScaledInteger(
            self::addSigned(
                self::toScaledInteger($left, $scale),
                self::toScaledInteger($right, $scale)
            ),
            $scale
        );
    }

    public static function sub(string $left, string $right, int $scale = 8): string
    {
        return self::add($left, self::negate($right, $scale), $scale);
    }

    public static function compare(string $left, string $right, int $scale = 8): int
    {
        $a = self::toScaledInteger($left, $scale);
        $b = self::toScaledInteger($right, $scale);

        $aNeg = str_starts_with($a, '-');
        $bNeg = str_starts_with($b, '-');

        if ($aNeg !== $bNeg) {
            return $aNeg ? -1 : 1;
        }

        $cmp = self::compareAbs(self::absDigits($a), self::absDigits($b));

        return $aNeg ? -$cmp : $cmp;
    }

    public static function min(string $left, string $right, int $scale = 8): string
    {
        return self::compare($left, $right, $scale) <= 0
            ? self::normalize($left, $scale)
            : self::normalize($right, $scale);
    }

    public static function abs(string $value, int $scale = 8): string
    {
        return ltrim(self::normalize($value, $scale), '-');
    }

    public static function negate(string $value, int $scale = 8): string
    {
        $normalized = self::normalize($value, $scale);

        if (self::isZero($normalized, $scale)) {
            return $normalized;
        }

        return str_starts_with($normalized, '-')
            ? substr($normalized, 1)
            : '-'.$normalized;
    }

    public static function isZero(string $value, int $scale = 8): bool
    {
        return self::toScaledInteger($value, $scale) === '0';
    }

    public static function isPositive(string $value, int $scale = 8): bool
    {
        return self::compare($value, '0', $scale) > 0;
    }

    public static function isNegative(string $value, int $scale = 8): bool
    {
        return self::compare($value, '0', $scale) < 0;
    }

    public static function multiplyBasisPoints(
        string $value,
        int $basisPoints,
        int $scale = 8
    ): string {
        if ($basisPoints < 0) {
            throw new DomainException('Basis points cannot be negative.');
        }

        $scaled = self::toScaledInteger($value, $scale);
        $negative = str_starts_with($scaled, '-');
        $digits = self::absDigits($scaled);
        $product = self::multiplyAbsByInt($digits, $basisPoints);
        [$quotient, $remainder] = self::divideAbsByInt($product, 10000);

        if ($remainder >= 5000) {
            $quotient = self::addAbs($quotient, '1');
        }

        if ($quotient === '0') {
            $negative = false;
        }

        return self::formatScaledInteger(
            ($negative ? '-' : '').$quotient,
            $scale
        );
    }

    public static function sum(iterable $values, int $scale = 8): string
    {
        $total = self::normalize('0', $scale);

        foreach ($values as $value) {
            $total = self::add($total, (string) $value, $scale);
        }

        return $total;
    }

    private static function toScaledInteger(string $value, int $scale): string
    {
        $value = trim($value);

        if ($scale < 0 || ! preg_match('/^[+-]?\d+(?:\.\d+)?$/', $value)) {
            throw new DomainException('Invalid decimal value.');
        }

        $negative = str_starts_with($value, '-');
        $unsigned = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');

        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;

        $kept = substr($fraction, 0, $scale);
        $kept = str_pad($kept, $scale, '0');
        $digits = ltrim($whole.$kept, '0');
        $digits = $digits === '' ? '0' : $digits;

        if (strlen($fraction) > $scale) {
            $roundDigit = (int) $fraction[$scale];
            if ($roundDigit >= 5) {
                $digits = self::addAbs($digits, '1');
            }
        }

        if ($digits === '0') {
            $negative = false;
        }

        return ($negative ? '-' : '').$digits;
    }

    private static function formatScaledInteger(string $integer, int $scale): string
    {
        $negative = str_starts_with($integer, '-');
        $digits = self::absDigits($integer);

        if ($scale === 0) {
            return ($negative && $digits !== '0' ? '-' : '').$digits;
        }

        $digits = str_pad($digits, $scale + 1, '0', STR_PAD_LEFT);
        $whole = substr($digits, 0, -$scale);
        $fraction = substr($digits, -$scale);

        return ($negative && $digits !== str_repeat('0', strlen($digits)) ? '-' : '')
            .$whole.'.'.$fraction;
    }

    private static function addSigned(string $left, string $right): string
    {
        $leftNeg = str_starts_with($left, '-');
        $rightNeg = str_starts_with($right, '-');
        $a = self::absDigits($left);
        $b = self::absDigits($right);

        if ($leftNeg === $rightNeg) {
            $sum = self::addAbs($a, $b);
            return ($leftNeg && $sum !== '0' ? '-' : '').$sum;
        }

        $cmp = self::compareAbs($a, $b);
        if ($cmp === 0) {
            return '0';
        }

        if ($cmp > 0) {
            $difference = self::subAbs($a, $b);
            return ($leftNeg ? '-' : '').$difference;
        }

        $difference = self::subAbs($b, $a);
        return ($rightNeg ? '-' : '').$difference;
    }

    private static function absDigits(string $integer): string
    {
        $digits = ltrim($integer, '+-0');
        return $digits === '' ? '0' : $digits;
    }

    private static function compareAbs(string $left, string $right): int
    {
        $left = ltrim($left, '0') ?: '0';
        $right = ltrim($right, '0') ?: '0';

        if (strlen($left) !== strlen($right)) {
            return strlen($left) <=> strlen($right);
        }

        return $left <=> $right;
    }

    private static function addAbs(string $left, string $right): string
    {
        $i = strlen($left) - 1;
        $j = strlen($right) - 1;
        $carry = 0;
        $result = '';

        while ($i >= 0 || $j >= 0 || $carry > 0) {
            $sum = $carry;
            if ($i >= 0) {
                $sum += (int) $left[$i--];
            }
            if ($j >= 0) {
                $sum += (int) $right[$j--];
            }
            $result = (string) ($sum % 10).$result;
            $carry = intdiv($sum, 10);
        }

        return ltrim($result, '0') ?: '0';
    }

    private static function subAbs(string $left, string $right): string
    {
        $i = strlen($left) - 1;
        $j = strlen($right) - 1;
        $borrow = 0;
        $result = '';

        while ($i >= 0) {
            $digit = (int) $left[$i] - $borrow;
            $sub = $j >= 0 ? (int) $right[$j] : 0;

            if ($digit < $sub) {
                $digit += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }

            $result = (string) ($digit - $sub).$result;
            $i--;
            $j--;
        }

        return ltrim($result, '0') ?: '0';
    }

    private static function multiplyAbsByInt(string $digits, int $multiplier): string
    {
        if ($multiplier === 0 || $digits === '0') {
            return '0';
        }

        $carry = 0;
        $result = '';

        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $product = ((int) $digits[$i] * $multiplier) + $carry;
            $result = (string) ($product % 10).$result;
            $carry = intdiv($product, 10);
        }

        while ($carry > 0) {
            $result = (string) ($carry % 10).$result;
            $carry = intdiv($carry, 10);
        }

        return ltrim($result, '0') ?: '0';
    }

    private static function divideAbsByInt(string $digits, int $divisor): array
    {
        if ($divisor <= 0) {
            throw new DomainException('Divisor must be positive.');
        }

        $remainder = 0;
        $quotient = '';

        for ($i = 0, $length = strlen($digits); $i < $length; $i++) {
            $current = ($remainder * 10) + (int) $digits[$i];
            $quotient .= (string) intdiv($current, $divisor);
            $remainder = $current % $divisor;
        }

        return [ltrim($quotient, '0') ?: '0', $remainder];
    }
}
