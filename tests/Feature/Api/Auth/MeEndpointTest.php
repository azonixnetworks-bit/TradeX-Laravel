<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class MeEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_device_bound_mobile_session_can_read_me(): void
    {
        $user = $this->createActiveUser();
        $device = $this->createTrustedMobileDevice($user);

        [$bearerToken, $sessionUuid] =
            $this->createBoundAccessToken(
                $user,
                $device
            );

        $response = $this
            ->withToken($bearerToken)
            ->getJson('/api/v1/auth/me');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.uuid', $user->uuid)
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonPath('data.user.referral_code', $user->referral_code)
            ->assertJsonPath('data.device.uuid', $device->uuid)
            ->assertJsonPath('data.device.device_slot', UserDevice::SLOT_MOBILE)
            ->assertJsonPath('data.device.is_trusted', true)
            ->assertJsonPath('data.session.uuid', $sessionUuid);

        $response
            ->assertJsonMissingPath('data.user.id')
            ->assertJsonMissingPath('data.device.id')
            ->assertJsonMissingPath('data.device.installation_id_hash')
            ->assertJsonMissingPath('data.device.device_public_key');
    }

    public function test_me_requires_authentication(): void
    {
        $this
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_unbound_sanctum_token_is_rejected_by_tradex_mobile_middleware(): void
    {
        $user = $this->createActiveUser();

        $newAccessToken = $user->createToken(
            'unbound-test-token',
            ['*'],
            now()->addMinutes(15)
        );

        $this
            ->withToken($newAccessToken->plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath(
                'error.code',
                'UNBOUND_ACCESS_TOKEN'
            );
    }

    private function createActiveUser(): User
    {
        return User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'API Test User',
            'email' => 'api-test-'.Str::lower(Str::random(8)).'@example.test',
            'email_verified_at' => now(),
            'whatsapp_number' => '+9477'.random_int(1000000, 9999999),
            'whatsapp_verified_at' => now(),
            'password' => Hash::make('API-Test-Password-123!'),
            'referral_code' => 'REF'.Str::upper(Str::random(8)),
            'country_code' => 'LK',
            'language_code' => 'en',
            'account_type' => 'PREMIUM',
            'status' => 'ACTIVE',
            'premium_activated_at' => now(),
        ]);
    }

    private function createTrustedMobileDevice(
        User $user
    ): UserDevice {
        return UserDevice::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->getKey(),
            'device_slot' => UserDevice::SLOT_MOBILE,
            'platform' => UserDevice::PLATFORM_ANDROID,
            'device_name' => 'TradeX Test Phone',
            'device_model' => 'Test Model',
            'os_version' => 'Android Test',
            'app_version' => '1.0.0-test',
            'installation_id_hash' => hash(
                'sha256',
                (string) Str::uuid()
            ),
            'is_trusted' => true,
            'is_active' => true,
            'registered_at' => now(),
            'trusted_at' => now(),
        ]);
    }

    private function createBoundAccessToken(
        User $user,
        UserDevice $device
    ): array {
        $sessionUuid = (string) Str::uuid();

        $newAccessToken = $user->createToken(
            'mobile-access',
            ['*'],
            now()->addMinutes(15)
        );

        $newAccessToken->accessToken
            ->forceFill([
                'user_device_id' => $device->getKey(),
                'session_uuid' => $sessionUuid,
            ])
            ->save();

        return [
            $newAccessToken->plainTextToken,
            $sessionUuid,
        ];
    }
}