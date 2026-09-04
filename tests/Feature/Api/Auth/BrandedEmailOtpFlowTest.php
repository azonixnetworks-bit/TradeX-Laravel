<?php

namespace Tests\Feature\Api\Auth;

use App\Models\PasswordRecoveryOtpChallenge;
use App\Support\Security\BrandedEmailOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPasswordRecoveryFlow;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class BrandedEmailOtpFlowTest extends TestCase
{
    use CreatesPasswordRecoveryFlow;
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_registration_email_accepts_trx_branded_eight_digit_code(): void
    {
        config([
            'tradex_registration.delivery.email_driver' =>
                'log',
            'tradex_registration.delivery.whatsapp_driver' =>
                'log',
            'tradex_registration.testing.fixed_email_otp' =>
                '12345678',
            'tradex_registration.testing.fixed_otp' =>
                '123456',
        ]);

        $start = $this->postJson(
            '/api/v1/auth/register/start',
            [
                'name' => 'Branded OTP User',
                'email' => 'branded-otp@example.test',
                'whatsapp_number' => '+94770008888',
                'password' => 'TradeX-Branded-OTP-123!',
                'password_confirmation' =>
                    'TradeX-Branded-OTP-123!',
                'referral_code' => null,
                'country_code' => 'LK',
                'language_code' => 'en',
            ]
        )->assertCreated();

        $base = [
            'registration_id' =>
                $start->json('data.registration_id'),
            'registration_token' =>
                $start->json('data.registration_token'),
        ];

        $this->postJson(
            '/api/v1/auth/register/email/verify',
            [
                ...$base,
                'otp_code' => 'TRX-12 34 56 78',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.email_verified',
                true
            );

        $this->postJson(
            '/api/v1/auth/register/whatsapp/verify',
            [
                ...$base,
                'otp_code' => '123456',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.whatsapp_verified',
                true
            );
    }

    public function test_password_recovery_email_accepts_branded_code_while_whatsapp_stays_six_digits(): void
    {
        $this->configurePasswordRecoveryTesting();

        config([
            'tradex_password_recovery.testing.fixed_email_otp' =>
                '87654321',
            'tradex_password_recovery.testing.fixed_otp' =>
                '123456',
        ]);

        $user = $this->createApiUser();

        $start = $this
            ->startPasswordRecovery($user)
            ->assertAccepted();

        $base = [
            'recovery_id' =>
                $start->json('data.recovery_id'),
            'recovery_token' =>
                $start->json('data.recovery_token'),
        ];

        $this->postJson(
            '/api/v1/auth/password-recovery/email/verify',
            [
                ...$base,
                'otp_code' =>
                    BrandedEmailOtp::format(
                        '87654321'
                    ),
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.next_action',
                'VERIFY_WHATSAPP_OTP'
            );

        $this->postJson(
            '/api/v1/auth/password-recovery/whatsapp/verify',
            [
                ...$base,
                'otp_code' => '123456',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.next_action',
                'VERIFY_TOTP'
            );

        $this->assertDatabaseHas(
            'password_recovery_otp_challenges',
            [
                'channel' =>
                    PasswordRecoveryOtpChallenge::CHANNEL_EMAIL,
            ]
        );

        $this->assertDatabaseHas(
            'password_recovery_otp_challenges',
            [
                'channel' =>
                    PasswordRecoveryOtpChallenge::CHANNEL_WHATSAPP,
            ]
        );
    }
}
