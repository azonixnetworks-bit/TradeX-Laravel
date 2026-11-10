<?php

namespace Tests\Feature\Api\Auth;

use App\Models\PasswordRecoverySession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPasswordRecoveryFlow;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class PasswordRecoveryEitherContactTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPasswordRecoveryFlow;
    use CreatesTradeXMobileSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configurePasswordRecoveryTesting();
    }

    public function test_email_only_starts_email_then_whatsapp_while_preserving_both_factors(): void
    {
        $user = $this->createApiUser();
        $start = $this->postJson('/api/v1/auth/password-recovery/start', [
            'email' => $user->email,
        ])->assertAccepted()->assertJsonPath('data.next_action', 'VERIFY_EMAIL_OTP');

        $session = PasswordRecoverySession::where('uuid', $start->json('data.recovery_id'))->firstOrFail();
        $this->assertSame($user->getKey(), $session->user_id);
        $this->assertSame('EMAIL', $session->start_channel);

        $base = [
            'recovery_id' => $start->json('data.recovery_id'),
            'recovery_token' => $start->json('data.recovery_token'),
        ];

        $this->postJson('/api/v1/auth/password-recovery/email/verify', [
            ...$base,
            'otp_code' => $this->passwordRecoveryOtp,
        ])->assertOk()->assertJsonPath('data.next_action', 'VERIFY_WHATSAPP_OTP');

        $this->postJson('/api/v1/auth/password-recovery/whatsapp/verify', [
            ...$base,
            'otp_code' => $this->passwordRecoveryOtp,
        ])->assertOk()->assertJsonPath('data.next_action', 'VERIFY_TOTP');

        $this->assertTrue($session->fresh()->hasVerifiedContacts());
    }

    public function test_whatsapp_only_starts_whatsapp_then_email_and_totp(): void
    {
        $user = $this->createApiUser();
        $start = $this->postJson('/api/v1/auth/password-recovery/start', [
            'whatsapp_number' => $user->whatsapp_number,
        ])->assertAccepted()->assertJsonPath('data.next_action', 'VERIFY_WHATSAPP_OTP');

        $session = PasswordRecoverySession::where('uuid', $start->json('data.recovery_id'))->firstOrFail();
        $this->assertSame($user->getKey(), $session->user_id);
        $this->assertSame('WHATSAPP', $session->start_channel);

        $base = [
            'recovery_id' => $start->json('data.recovery_id'),
            'recovery_token' => $start->json('data.recovery_token'),
        ];

        $this->postJson('/api/v1/auth/password-recovery/email/verify', [
            ...$base,
            'otp_code' => $this->passwordRecoveryOtp,
        ])->assertStatus(409)->assertJsonPath('error.code', 'PASSWORD_RECOVERY_WHATSAPP_REQUIRED');

        $this->postJson('/api/v1/auth/password-recovery/whatsapp/verify', [
            ...$base,
            'otp_code' => $this->passwordRecoveryOtp,
        ])->assertOk()->assertJsonPath('data.next_action', 'VERIFY_EMAIL_OTP');

        $this->postJson('/api/v1/auth/password-recovery/email/verify', [
            ...$base,
            'otp_code' => $this->passwordRecoveryOtp,
        ])->assertOk()->assertJsonPath('data.next_action', 'VERIFY_TOTP');

        $this->assertTrue($session->fresh()->hasVerifiedContacts());
    }

    public function test_missing_both_contacts_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/password-recovery/start', [])
            ->assertUnprocessable();
    }

    public function test_unknown_whatsapp_returns_a_decoy_with_same_response(): void
    {
        $user = $this->createApiUser();
        $real = $this->postJson('/api/v1/auth/password-recovery/start', [
            'whatsapp_number' => $user->whatsapp_number,
        ])->assertAccepted();
        $fake = $this->postJson('/api/v1/auth/password-recovery/start', [
            'whatsapp_number' => '+94779999999',
        ])->assertAccepted();

        $this->assertSame($real->json('data.next_action'), $fake->json('data.next_action'));
        $this->assertSame($real->json('data.message'), $fake->json('data.message'));
        $this->assertNull(
            PasswordRecoverySession::where('uuid', $fake->json('data.recovery_id'))->firstOrFail()->user_id
        );
    }

    public function test_mismatched_two_contacts_is_still_a_decoy(): void
    {
        $user = $this->createApiUser();
        $start = $this->postJson('/api/v1/auth/password-recovery/start', [
            'email' => $user->email,
            'whatsapp_number' => '+94778888888',
        ])->assertAccepted()->assertJsonPath('data.next_action', 'VERIFY_EMAIL_OTP');

        $this->assertNull(
            PasswordRecoverySession::where('uuid', $start->json('data.recovery_id'))->firstOrFail()->user_id
        );
    }
}
