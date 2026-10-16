<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use App\Models\UserRegistrationSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class RegistrationRecoveryBootstrapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tradex_auth.recovery_codes.pepper' => 'registration-recovery-test-pepper',
            'tradex_auth.recovery_codes.count' => 10,
        ]);
    }

    public function test_completed_self_registration_can_receive_recovery_codes_once(): void
    {
        Mail::fake();

        [$session, $plainToken] = $this->createCompletedSelfRegistration();

        $response = $this->postJson(
            '/api/v1/auth/register/recovery-codes',
            [
                'registration_id' => $session->uuid,
                'registration_token' => $plainToken,
            ]
        )
            ->assertOk()
            ->assertJsonPath('data.recovery_codes_count', 10)
            ->assertJsonPath('data.displayed_once', true);

        $this->assertCount(10, $response->json('data.recovery_codes'));
        $this->assertDatabaseCount('user_mfa_recovery_codes', 10);

        $this->postJson(
            '/api/v1/auth/register/recovery-codes',
            [
                'registration_id' => $session->uuid,
                'registration_token' => $plainToken,
            ]
        )
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'MFA_RECOVERY_CODES_ALREADY_ISSUED');
    }

    private function createCompletedSelfRegistration(): array
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Recovery Code Test User',
            'email' => 'recovery-'.Str::lower(Str::random(8)).'@example.test',
            'email_verified_at' => now(),
            'whatsapp_number' => '+9477'.random_int(1000000, 9999999),
            'whatsapp_verified_at' => now(),
            'password' => Hash::make('StrongPassword123!'),
            'referral_code' => 'TX'.Str::upper(Str::random(10)),
            'country_code' => 'LK',
            'language_code' => 'en',
            'account_type' => 'FREE',
            'status' => 'ACTIVE',
        ]);

        $user->forceFill([
            'totp_secret' => 'JBSWY3DPEHPK3PXP',
            'totp_confirmed_at' => now(),
        ])->save();

        $plainToken = bin2hex(random_bytes(32));

        $session = UserRegistrationSession::query()->create([
            'uuid' => (string) Str::uuid(),
            'registration_token_hash' => hash('sha256', $plainToken),
            'name' => $user->name,
            'email' => $user->email,
            'whatsapp_number' => $user->whatsapp_number,
            'password_hash' => $user->password,
            'country_code' => 'LK',
            'language_code' => 'en',
            'source' => UserRegistrationSession::SOURCE_SELF_REGISTER,
            'email_verified_at' => now(),
            'whatsapp_verified_at' => now(),
            'password_set_at' => now(),
            'totp_secret' => 'JBSWY3DPEHPK3PXP',
            'totp_setup_at' => now(),
            'totp_confirmed_at' => now(),
            'status' => UserRegistrationSession::STATUS_COMPLETED,
            'expires_at' => now()->addMinutes(30),
            'completed_user_id' => $user->getKey(),
            'completed_at' => now(),
            'start_ip' => '127.0.0.1',
            'start_user_agent' => 'TradeX recovery test',
        ]);

        return [$session, $plainToken];
    }
}
