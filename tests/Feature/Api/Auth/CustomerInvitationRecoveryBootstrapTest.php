<?php

namespace Tests\Feature\Api\Auth;

use App\Models\AdminUser;
use App\Models\CustomerOnboardingInvitation;
use App\Models\User;
use App\Models\UserRegistrationSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerInvitationRecoveryBootstrapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tradex_auth.recovery_codes.pepper' =>
                'admin-20c-bootstrap-test-pepper',
            'tradex_auth.recovery_codes.count' => 10,
        ]);
    }

    public function test_completed_admin_invite_can_bootstrap_recovery_codes_only_once(): void
    {
        [$session, $plainToken] =
            $this->createCompletedInviteSession();

        $response = $this->postJson(
            '/api/v1/auth/invite/recovery-codes',
            [
                'registration_id' => $session->uuid,
                'registration_token' => $plainToken,
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.recovery_codes_count',
                10
            )
            ->assertJsonPath(
                'data.displayed_once',
                true
            )
            ->assertJsonPath(
                'data.next_action',
                'LOGIN'
            );

        $codes = $response->json(
            'data.recovery_codes'
        );

        $this->assertCount(10, $codes);
        $this->assertDatabaseCount(
            'user_mfa_recovery_codes',
            10
        );

        foreach ($codes as $plainCode) {
            $this->assertDatabaseMissing(
                'user_mfa_recovery_codes',
                ['code_hash' => $plainCode]
            );
        }

        $this->postJson(
            '/api/v1/auth/invite/recovery-codes',
            [
                'registration_id' => $session->uuid,
                'registration_token' => $plainToken,
            ]
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'MFA_RECOVERY_CODES_ALREADY_ISSUED'
            );

        $this->assertDatabaseCount(
            'user_mfa_recovery_codes',
            10
        );
    }

    public function test_pending_invite_session_cannot_receive_recovery_codes(): void
    {
        [$session, $plainToken] =
            $this->createCompletedInviteSession();

        $invitation = $session
            ->customerOnboardingInvitation;

        $invitation->forceFill([
            'status' =>
                CustomerOnboardingInvitation::STATUS_PENDING,
            'onboarding_stage' =>
                CustomerOnboardingInvitation::STAGE_TOTP_CONFIRMED,
            'matched_user_id' => null,
            'matched_at' => null,
        ])->save();

        $session->forceFill([
            'status' => UserRegistrationSession::STATUS_PENDING,
            'completed_user_id' => null,
            'completed_at' => null,
        ])->save();

        $this->postJson(
            '/api/v1/auth/invite/recovery-codes',
            [
                'registration_id' => $session->uuid,
                'registration_token' => $plainToken,
            ]
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'INVITE_ONBOARDING_NOT_COMPLETED'
            );

        $this->assertDatabaseCount(
            'user_mfa_recovery_codes',
            0
        );
    }

    private function createCompletedInviteSession(): array
    {
        $admin = AdminUser::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'ADMIN-20C Test Admin',
            'email' => 'admin20c-'
                .Str::lower(Str::random(8))
                .'@example.test',
            'email_verified_at' => now(),
            'password' => 'Testing-Admin-Pass-123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
        ]);

        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'ADMIN-20C Customer',
            'email' => 'admin20c-customer-'
                .Str::lower(Str::random(8))
                .'@example.test',
            'email_verified_at' => now(),
            'whatsapp_number' => '+9477'
                .random_int(1000000, 9999999),
            'whatsapp_verified_at' => now(),
            'password' => Hash::make(
                'StrongPassword123!'
            ),
            'referral_code' =>
                'TX'.Str::upper(Str::random(10)),
            'country_code' => 'LK',
            'language_code' => 'en',
            'account_type' => 'FREE',
            'status' => 'ACTIVE',
        ]);

        $user->forceFill([
            'totp_secret' =>
                'JBSWY3DPEHPK3PXP',
            'totp_confirmed_at' => now(),
            'totp_last_used_step' => null,
        ])->save();

        $invitation = CustomerOnboardingInvitation::query()
            ->create([
                'uuid' => (string) Str::uuid(),
                'name' => $user->name,
                'email' => $user->email,
                'whatsapp_number' => $user->whatsapp_number,
                'country_code' => 'LK',
                'language_code' => 'en',
                'status' =>
                    CustomerOnboardingInvitation::STATUS_MATCHED,
                'onboarding_stage' =>
                    CustomerOnboardingInvitation::STAGE_COMPLETED,
                'activation_token_hash' => null,
                'activation_consumed_at' => now(),
                'invited_at' => now()->subHour(),
                'last_invited_at' => now()->subHour(),
                'invite_send_count' => 1,
                'matched_user_id' => $user->getKey(),
                'matched_at' => now(),
                'created_by_admin_id' => $admin->getKey(),
                'expires_at' => now()->addDays(6),
            ]);

        $plainToken = bin2hex(random_bytes(32));

        $session = UserRegistrationSession::query()
            ->create([
                'uuid' => (string) Str::uuid(),
                'registration_token_hash' => hash(
                    'sha256',
                    $plainToken
                ),
                'name' => $user->name,
                'email' => $user->email,
                'whatsapp_number' => $user->whatsapp_number,
                'password_hash' => $user->password,
                'country_code' => 'LK',
                'language_code' => 'en',
                'source' =>
                    UserRegistrationSession::SOURCE_ADMIN_INVITE,
                'customer_onboarding_invitation_id' =>
                    $invitation->getKey(),
                'email_verified_at' => now(),
                'whatsapp_verified_at' => now(),
                'password_set_at' => now(),
                'totp_secret' =>
                    'JBSWY3DPEHPK3PXP',
                'totp_setup_at' => now(),
                'totp_confirmed_at' => now(),
                'status' =>
                    UserRegistrationSession::STATUS_COMPLETED,
                'expires_at' => now()->addMinutes(30),
                'completed_user_id' => $user->getKey(),
                'completed_at' => now(),
                'start_ip' => '127.0.0.1',
                'start_user_agent' =>
                    'ADMIN-20C Test',
            ]);

        return [$session, $plainToken];
    }
}
