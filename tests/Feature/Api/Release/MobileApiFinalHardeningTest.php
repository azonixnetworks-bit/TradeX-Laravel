<?php

namespace Tests\Feature\Api\Release;

use App\Models\User;
use App\Models\UserDevice;
use App\Services\Api\MobileApiReleaseReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileApiFinalHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_api_response_receives_security_and_server_request_id_headers(): void
    {
        $response = $this
            ->getJson('/api/v1/bootstrap')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

        $requestId = (string) $response->headers->get('X-Request-Id');

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $requestId
        );
    }

    public function test_client_supplied_request_id_is_never_trusted_or_echoed(): void
    {
        $response = $this
            ->withHeader('X-Request-Id', 'attacker-controlled-id')
            ->getJson('/api/v1/bootstrap')
            ->assertOk();

        $this->assertNotSame(
            'attacker-controlled-id',
            $response->headers->get('X-Request-Id')
        );
    }

    public function test_authentication_failure_still_receives_api_security_headers(): void
    {
        $response = $this
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');

        $this->assertNotEmpty(
            $response->headers->get('X-Request-Id')
        );
    }

    public function test_invalid_configured_api_host_fails_closed(): void
    {
        Config::set(
            'tradex_mobile_api_hardening.transport.allowed_hosts',
            ['api.tradex.test']
        );

        $this
            ->withServerVariables([
                'HTTP_HOST' => 'evil.example.test',
            ])
            ->getJson('/api/v1/bootstrap')
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'INVALID_API_HOST')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_https_requirement_fails_closed_on_plain_http(): void
    {
        Config::set(
            'tradex_mobile_api_hardening.transport.require_https',
            true
        );

        $this
            ->getJson('/api/v1/bootstrap')
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'API_HTTPS_REQUIRED')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_public_bootstrap_etag_contract_is_preserved_and_not_forced_no_store(): void
    {
        $first = $this
            ->getJson('/api/v1/bootstrap')
            ->assertOk();

        $etag = $first->headers->get('ETag');

        $this->assertNotEmpty($etag);
        $this->assertStringNotContainsString(
            'no-store',
            strtolower((string) $first->headers->get('Cache-Control'))
        );

        $this
            ->withHeader('If-None-Match', $etag)
            ->get('/api/v1/bootstrap')
            ->assertStatus(304)
            ->assertHeader('ETag', $etag)
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_bearer_authenticated_api_response_is_not_cacheable(): void
    {
        $user = $this->activeUser('cache');
        $device = $this->trustedDevice($user);
        $token = $this->boundAccessToken($user, $device);

        $response = $this
            ->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk();

        $this->assertStringContainsString(
            'no-store',
            strtolower((string) $response->headers->get('Cache-Control'))
        );
    }

    public function test_bearer_principal_is_resolved_fresh_for_each_request(): void
    {
        $firstUser = $this->activeUser('first');
        $firstToken = $this->boundAccessToken(
            $firstUser,
            $this->trustedDevice($firstUser)
        );

        $secondUser = $this->activeUser('second');
        $secondToken = $this->boundAccessToken(
            $secondUser,
            $this->trustedDevice($secondUser)
        );

        $this
            ->withToken($firstToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.uuid', $firstUser->uuid);

        $this
            ->withToken($secondToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.uuid', $secondUser->uuid);
    }

    public function test_baseline_release_readiness_passes_with_migrated_security_schema(): void
    {
        Config::set(
            'app.key',
            'base64:'.base64_encode(str_repeat('k', 32))
        );

        $result = app(
            MobileApiReleaseReadinessService::class
        )->evaluate(false);

        $this->assertTrue($result['ready']);
        $this->assertSame(0, $result['failed_critical']);
        $this->assertSame('BASELINE', $result['mode']);

        $this
            ->artisan('tradex:mobile-api-release-check')
            ->assertSuccessful();
    }

    public function test_production_release_readiness_fails_closed_in_test_environment(): void
    {
        Config::set(
            'app.key',
            'base64:'.base64_encode(str_repeat('k', 32))
        );

        $result = app(
            MobileApiReleaseReadinessService::class
        )->evaluate(true);

        $this->assertFalse($result['ready']);
        $this->assertGreaterThan(0, $result['failed_critical']);
        $this->assertSame('PRODUCTION', $result['mode']);
    }

    private function activeUser(
        string $suffix
    ): User {
        return User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'API-23 '.$suffix.' User',
            'email' => 'api23-'.$suffix.'-'.Str::lower(Str::random(8)).'@example.test',
            'email_verified_at' => now(),
            'password' => Hash::make('API23-Password-123!'),
            'referral_code' => 'A23'.Str::upper(Str::random(9)),
            'country_code' => 'LK',
            'language_code' => 'en',
            'account_type' => 'PREMIUM',
            'status' => 'ACTIVE',
            'premium_activated_at' => now(),
        ]);
    }

    private function trustedDevice(
        User $user
    ): UserDevice {
        return UserDevice::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->getKey(),
            'device_slot' => UserDevice::SLOT_MOBILE,
            'platform' => UserDevice::PLATFORM_ANDROID,
            'device_name' => 'API-23 Test Phone',
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

    private function boundAccessToken(
        User $user,
        UserDevice $device
    ): string {
        $newAccessToken = $user->createToken(
            'api23-mobile-access',
            ['*'],
            now()->addMinutes(15)
        );

        $newAccessToken->accessToken
            ->forceFill([
                'user_device_id' => $device->getKey(),
                'session_uuid' => (string) Str::uuid(),
            ])
            ->save();

        return $newAccessToken->plainTextToken;
    }
}
