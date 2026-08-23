<?php

namespace Tests\Unit\Auth\Registration;

use App\Models\UserRegistrationSession;
use App\Services\Auth\Registration\TotpProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TotpProvisioningServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_generates_base32_secret_and_otpauth_uri(): void
    {
        $session =
            UserRegistrationSession::query()
                ->create([
                    'uuid' =>
                        (string) Str::uuid(),

                    'registration_token_hash' =>
                        hash(
                            'sha256',
                            Str::random(64)
                        ),

                    'name' =>
                        'TOTP User',

                    'email' =>
                        'totp@example.test',

                    'whatsapp_number' =>
                        '+94770000003',

                    'password_hash' =>
                        password_hash(
                            'Secret-Password-123!',
                            PASSWORD_BCRYPT
                        ),

                    'country_code' =>
                        'LK',

                    'language_code' =>
                        'en',

                    'email_verified_at' =>
                        now(),

                    'whatsapp_verified_at' =>
                        now(),

                    'status' =>
                        UserRegistrationSession::STATUS_PENDING,

                    'expires_at' =>
                        now()->addHour(),
                ]);

        $result = app(
            TotpProvisioningService::class
        )->setup(
            $session
        );

        $this->assertMatchesRegularExpression(
            '/^[A-Z2-7]+$/',
            $result['secret']
        );

        $this->assertStringStartsWith(
            'otpauth://totp/',
            $result['otpauth_uri']
        );

        $this->assertStringContainsString(
            'secret=',
            $result['otpauth_uri']
        );
    }
}
