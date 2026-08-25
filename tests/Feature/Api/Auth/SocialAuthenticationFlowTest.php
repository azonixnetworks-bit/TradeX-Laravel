<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserRegistrationSession;
use App\Models\UserSocialAccount;
use App\Services\Auth\Social\SocialIdentity;
use App\Services\Auth\Social\SocialProviderVerifier;
use App\Services\Auth\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SocialAuthenticationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_linked_google_identity_requires_totp_and_receives_normal_session_tokens(): void
    {
        $user = $this->createTotpUser();
        $identity = new SocialIdentity('google', 'google-subject-1', $user->email, true, $user->name);
        $this->fakeProvider($identity);

        UserSocialAccount::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->getKey(),
            'provider' => 'google',
            'provider_user_id' => $identity->subject,
            'provider_email' => $identity->email,
            'linked_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/social/google/login', $this->socialLoginPayload($user));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.uuid', $user->uuid)
            ->assertJsonPath('data.device.device_slot', UserDevice::SLOT_MOBILE);

        $this->assertNotEmpty($response->json('data.access_token'));
        $this->assertNotEmpty($response->json('data.refresh_token'));
    }

    public function test_unlinked_social_identity_is_never_auto_linked_by_email(): void
    {
        $user = $this->createTotpUser();
        $this->fakeProvider(new SocialIdentity('google', 'different-subject', $user->email, true, $user->name));

        $this->postJson('/api/v1/auth/social/google/login', $this->socialLoginPayload($user))
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'SOCIAL_ACCOUNT_NOT_LINKED');

        $this->assertDatabaseCount('user_social_accounts', 0);
    }

    public function test_social_registration_cannot_be_completed_through_frozen_normal_completion_route(): void
    {
        $token = bin2hex(random_bytes(32));
        $session = UserRegistrationSession::query()->create([
            'uuid' => (string) Str::uuid(),
            'registration_token_hash' => hash('sha256', $token),
            'name' => 'Social User',
            'email' => 'social@example.test',
            'whatsapp_number' => '+94770000001',
            'password_hash' => Hash::make(bin2hex(random_bytes(32))),
            'country_code' => 'LK',
            'language_code' => 'en',
            'email_verified_at' => now(),
            'whatsapp_verified_at' => now(),
            'totp_secret' => 'JBSWY3DPEHPK3PXP',
            'status' => UserRegistrationSession::STATUS_PENDING,
            'expires_at' => now()->addHour(),
        ]);
        $session->forceFill([
            'social_provider' => 'google',
            'social_provider_user_id' => 'google-social-onboard-subject',
            'social_provider_email' => 'social@example.test',
        ])->save();

        $this->postJson('/api/v1/auth/register/totp/confirm', [
            'registration_id' => $session->uuid,
            'registration_token' => $token,
            'totp_code' => app(TotpService::class)->codeAt('JBSWY3DPEHPK3PXP', time()),
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'SOCIAL_ONBOARDING_CONFIRM_REQUIRED');

        $this->assertDatabaseCount('users', 0);
    }

    private function fakeProvider(SocialIdentity $identity): void
    {
        $this->app->instance(SocialProviderVerifier::class, new class($identity) extends SocialProviderVerifier {
            public function __construct(private readonly SocialIdentity $identity) {}
            public function verify(string $provider, string $credential): SocialIdentity { return $this->identity; }
        });
    }

    private function createTotpUser(): User
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'API Social User',
            'email' => 'social-auth-'.Str::lower(Str::random(8)).'@example.test',
            'email_verified_at' => now(),
            'password' => Hash::make('StrongPassword123!'),
            'referral_code' => 'REF'.Str::upper(Str::random(8)),
            'country_code' => 'LK',
            'language_code' => 'en',
            'account_type' => 'PREMIUM',
            'status' => 'ACTIVE',
            'premium_activated_at' => now(),
        ]);

        $user->forceFill([
            'totp_secret' => 'JBSWY3DPEHPK3PXP',
            'totp_confirmed_at' => now(),
            'totp_last_used_step' => null,
        ])->save();

        return $user->refresh();
    }

    private function socialLoginPayload(User $user): array
    {
        return [
            'credential' => 'provider-test-credential',
            'totp_code' => app(TotpService::class)->codeAt((string) $user->totp_secret, time()),
            'installation_id' => (string) Str::uuid(),
            'platform' => UserDevice::PLATFORM_ANDROID,
            'device_name' => 'TradeX Test Phone',
            'device_model' => 'Test Model',
            'os_version' => 'Android Test',
            'app_version' => '1.0.0-test',
        ];
    }
}
