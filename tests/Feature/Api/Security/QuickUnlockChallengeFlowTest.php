<?php

namespace Tests\Feature\Api\Security;

use App\Models\UserDeviceQuickUnlockChallenge;
use App\Models\UserDeviceQuickUnlockSetting;
use App\Services\Security\QuickUnlock\DeviceCredentialVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesQuickUnlockCredential;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class QuickUnlockChallengeFlowTest extends TestCase
{
    use CreatesQuickUnlockCredential;
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_enrolled_device_can_exchange_one_time_signature_for_session(): void
    {
        $fixture = $this->enrolledDevice();
        $challenge = $this->issueChallenge($fixture);
        $signature = $this->signature($fixture, $challenge);

        $response = $this->verify($fixture, $challenge, $signature)
            ->assertOk()
            ->assertJsonPath('data.quick_unlock_method', 'PIN');

        $this->assertNotEmpty($response->json('data.access_token'));
        $this->assertNotEmpty($response->json('data.refresh_token'));
        $this->withToken($response->json('data.access_token'))
            ->getJson('/api/v1/auth/me')
            ->assertOk();
    }

    public function test_consumed_challenge_cannot_be_replayed(): void
    {
        $fixture = $this->enrolledDevice();
        $challenge = $this->issueChallenge($fixture);
        $signature = $this->signature($fixture, $challenge);

        $this->verify($fixture, $challenge, $signature)->assertOk();
        $this->verify($fixture, $challenge, $signature)
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'QUICK_UNLOCK_CHALLENGE_INVALID');
    }

    public function test_invalid_signature_is_rejected_and_attempt_is_recorded(): void
    {
        $fixture = $this->enrolledDevice();
        $attacker = $this->createQuickUnlockCredential();
        $challenge = $this->issueChallenge($fixture);
        $message = $this->canonicalMessage($fixture, $challenge);
        $signature = $this->signQuickUnlockMessage($attacker['private_key'], $message);

        $this->verify($fixture, $challenge, $signature)
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'QUICK_UNLOCK_SIGNATURE_INVALID');

        $this->assertDatabaseHas('user_device_quick_unlock_challenges', [
            'id' => $challenge['challenge_id'],
            'failed_attempts' => 1,
        ]);
    }

    public function test_expired_challenge_is_consumed_and_rejected(): void
    {
        $fixture = $this->enrolledDevice();
        $challenge = $this->issueChallenge($fixture);
        UserDeviceQuickUnlockChallenge::query()
            ->whereKey($challenge['challenge_id'])
            ->update(['expires_at' => now()->subSecond()]);

        $this->verify($fixture, $challenge, $this->signature($fixture, $challenge))
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'QUICK_UNLOCK_CHALLENGE_EXPIRED');

        $this->assertNotNull(
            UserDeviceQuickUnlockChallenge::findOrFail($challenge['challenge_id'])->consumed_at
        );
    }

    public function test_revoked_device_cannot_obtain_a_challenge(): void
    {
        $fixture = $this->enrolledDevice();
        $fixture['device']->forceFill([
            'is_active' => false,
            'revoked_at' => now(),
        ])->save();

        $this->postJson('/api/v1/auth/quick-unlock/challenge', [
            'credential_id' => $fixture['credential']['id'],
            'installation_id' => $fixture['installation_id'],
        ])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'QUICK_UNLOCK_ENROLLMENT_UNAVAILABLE');
    }

    public function test_logout_all_revokes_quick_login_credential(): void
    {
        $fixture = $this->enrolledDevice();
        $challenge = $this->issueChallenge($fixture);
        $login = $this->verify(
            $fixture,
            $challenge,
            $this->signature($fixture, $challenge)
        )->assertOk();

        $this->withToken($login->json('data.access_token'))
            ->postJson('/api/v1/auth/logout-all')
            ->assertOk();

        $this->postJson('/api/v1/auth/quick-unlock/challenge', [
            'credential_id' => $fixture['credential']['id'],
            'installation_id' => $fixture['installation_id'],
        ])->assertUnauthorized();

        $this->assertDatabaseHas('user_device_quick_unlock_settings', [
            'id' => $fixture['setting']->getKey(),
            'enabled' => false,
            'credential_id' => null,
        ]);
    }

    private function enrolledDevice(): array
    {
        $user = $this->createApiUser();
        $installationId = (string) Str::uuid();
        $device = $user->devices()->create([
            'uuid' => (string) Str::uuid(),
            'device_slot' => 'MOBILE',
            'platform' => 'ANDROID',
            'installation_id_hash' => hash('sha256', strtolower($installationId)),
            'is_trusted' => true,
            'is_active' => true,
            'registered_at' => now(),
            'trusted_at' => now(),
        ]);
        $credential = $this->createQuickUnlockCredential();
        $setting = UserDeviceQuickUnlockSetting::query()->create([
            'user_device_id' => $device->getKey(),
            'enabled' => true,
            'method' => 'PIN',
            'trusted_at_snapshot' => $device->trusted_at,
            'enrolled_at' => now(),
            'credential_id' => $credential['id'],
            'credential_algorithm' => 'ES256',
            'credential_public_key' => $credential['public_key'],
            'credential_rotated_at' => now(),
        ]);

        return compact('user', 'device', 'setting', 'credential', 'installationId') + [
            'installation_id' => $installationId,
        ];
    }

    private function issueChallenge(array $fixture): array
    {
        return $this->postJson('/api/v1/auth/quick-unlock/challenge', [
            'credential_id' => $fixture['credential']['id'],
            'installation_id' => $fixture['installation_id'],
        ])->assertOk()->json('data');
    }

    private function signature(array $fixture, array $challenge): string
    {
        return $this->signQuickUnlockMessage(
            $fixture['credential']['private_key'],
            $this->canonicalMessage($fixture, $challenge)
        );
    }

    private function canonicalMessage(array $fixture, array $challenge): string
    {
        return app(DeviceCredentialVerifier::class)->canonicalMessage(
            $challenge['challenge_id'],
            $fixture['credential']['id'],
            $fixture['installation_id'],
            $challenge['nonce']
        );
    }

    private function verify(array $fixture, array $challenge, string $signature)
    {
        return $this->postJson('/api/v1/auth/quick-unlock/verify', [
            'challenge_id' => $challenge['challenge_id'],
            'credential_id' => $fixture['credential']['id'],
            'installation_id' => $fixture['installation_id'],
            'nonce' => $challenge['nonce'],
            'signature' => $signature,
        ]);
    }
}
