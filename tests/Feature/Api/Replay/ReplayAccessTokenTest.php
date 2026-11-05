<?php

namespace Tests\Feature\Api\Replay;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ReplayAccessTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_trusted_sanctum_device_receives_120_second_rs256_token(): void
    {
        $private = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);
        $this->assertNotFalse($private);
        openssl_pkey_export($private, $pem);
        $path = tempnam(sys_get_temp_dir(), 'replay-jwt-test-');
        file_put_contents($path, $pem);
        try {
            config()->set('replay.jwt_private_key_path', $path);
            config()->set('replay.token_ttl_seconds', 120);
            $user = $this->createUser();
            $device = $this->createDevice($user);
            $accessToken = $this->createSanctumToken($user, $device);

            $response = $this->withToken($accessToken)
                ->postJson('/api/v1/replay/access-token');
            $response->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.scope', 'replay:sessions')
                ->assertJsonPath('data.token_type', 'Bearer')
                ->assertJsonPath('data.expires_in', 120)
                ->assertJsonPath('data.execution_enabled', false);
            $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
            $jwt = (string) $response->json('data.access_token');
            $segments = explode('.', $jwt);
            $this->assertCount(3, $segments);
            $decode = static fn (string $input) => base64_decode(strtr($input, '-_', '+/'), true);
            $header = json_decode($decode($segments[0]), true, flags: JSON_THROW_ON_ERROR);
            $claims = json_decode($decode($segments[1]), true, flags: JSON_THROW_ON_ERROR);
            $signature = $decode($segments[2]);
            $this->assertSame('RS256', $header['alg']);
            $this->assertSame($user->uuid, $claims['sub']);
            $this->assertSame($device->uuid, $claims['device']);
            $this->assertSame('tradex-laravel', $claims['iss']);
            $this->assertSame('tradex-replay', $claims['aud']);
            $this->assertSame(120, $claims['exp'] - $claims['iat']);
            $this->assertSame('replay:sessions', $claims['scope']);
            $this->assertSame(1, openssl_verify($segments[0].'.'.$segments[1], $signature,
                openssl_pkey_get_details($private)['key'], OPENSSL_ALGO_SHA256));
            $this->assertArrayNotHasKey('broker_access_token', $claims);
        } finally {
            unlink($path);
        }
    }

    public function test_unconfigured_private_key_fails_closed(): void
    {
        config()->set('replay.jwt_private_key_path', '/does/not/exist/replay.pem');
        $user = $this->createUser();
        $device = $this->createDevice($user);
        $token = $this->createSanctumToken($user, $device);
        $this->withToken($token)
            ->postJson('/api/v1/replay/access-token')->assertStatus(503);
    }

    public function test_replay_token_issuer_requires_auth(): void
    {
        $this->postJson('/api/v1/replay/access-token')->assertUnauthorized();
    }

    private function createUser(): User
    {
        return User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Replay Test',
            'email' => 'replay-'.Str::lower(Str::random(10)).'@example.test',
            'email_verified_at' => now(),
            'whatsapp_number' => '+9477'.random_int(1000000, 9999999),
            'whatsapp_verified_at' => now(),
            'password' => Hash::make('Replay-Test-Only-123!'),
            'referral_code' => 'REP'.Str::upper(Str::random(8)),
            'country_code' => 'LK',
            'language_code' => 'en',
            'account_type' => 'PREMIUM',
            'status' => 'ACTIVE',
            'premium_activated_at' => now(),
        ]);
    }

    private function createDevice(User $user): UserDevice
    {
        return UserDevice::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->getKey(),
            'device_slot' => UserDevice::SLOT_MOBILE,
            'platform' => UserDevice::PLATFORM_ANDROID,
            'device_name' => 'Replay Test Phone',
            'device_model' => 'Test Model',
            'os_version' => 'Android Test',
            'app_version' => '1.0.0-test',
            'installation_id_hash' => hash('sha256', (string) Str::uuid()),
            'is_trusted' => true,
            'is_active' => true,
            'registered_at' => now(),
            'trusted_at' => now(),
        ]);
    }

    private function createSanctumToken(User $user, UserDevice $device): string
    {
        $accessToken = $user->createToken('replay-test', ['*'], now()->addMinutes(15));
        $accessToken->accessToken->forceFill([
            'user_device_id' => $device->getKey(),
            'session_uuid' => (string) Str::uuid(),
        ])->save();
        return $accessToken->plainTextToken;
    }
}
