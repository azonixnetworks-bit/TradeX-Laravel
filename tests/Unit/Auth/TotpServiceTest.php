<?php

namespace Tests\Unit\Auth;

use App\Services\Auth\TotpService;
use Tests\TestCase;

class TotpServiceTest extends TestCase
{
    public function test_rfc_6238_style_code_can_be_generated_and_verified(): void
    {
        $service = app(
            TotpService::class
        );

        $secret =
            'JBSWY3DPEHPK3PXP';

        $timestamp = 1_700_000_000;

        $code = $service->codeAt(
            $secret,
            $timestamp
        );

        $this->assertMatchesRegularExpression(
            '/^\d{6}$/',
            $code
        );

        $this->assertNotNull(
            $service->matchingStep(
                $secret,
                $code,
                $timestamp
            )
        );
    }

    public function test_invalid_totp_code_is_rejected(): void
    {
        $service = app(
            TotpService::class
        );

        $this->assertNull(
            $service->matchingStep(
                'JBSWY3DPEHPK3PXP',
                '000000',
                1_700_000_000
            )
        );
    }
}
