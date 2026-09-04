<?php

namespace Tests\Unit\Support;

use App\Support\Security\BrandedEmailOtp;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BrandedEmailOtpTest extends TestCase
{
    public function test_formats_eight_digits_with_trx_prefix(): void
    {
        $this->assertSame(
            'TRX-12 34 56 78',
            BrandedEmailOtp::format('12345678')
        );
    }

    public function test_normalizes_plain_and_branded_email_codes(): void
    {
        $this->assertSame(
            '12345678',
            BrandedEmailOtp::normalize('12345678')
        );

        $this->assertSame(
            '12345678',
            BrandedEmailOtp::normalize('TRX-12 34 56 78')
        );

        $this->assertSame(
            '12345678',
            BrandedEmailOtp::normalize('trx-12 34 56 78')
        );
    }

    public function test_rejects_non_eight_digit_formatting_input(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BrandedEmailOtp::format('123456');
    }
}