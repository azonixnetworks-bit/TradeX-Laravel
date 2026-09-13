<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use App\Models\UserDevice;
use App\Services\Auth\CustomerLoginRiskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerLoginRiskServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_ever_device_is_not_flagged_as_risky(): void
    {
        $user = $this->user();
        $request = Request::create('/api/v1/auth/login', 'POST', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.10',
        ]);

        $risk = app(CustomerLoginRiskService::class)->analyze(
            $user,
            [
                'platform' => UserDevice::PLATFORM_ANDROID,
                'device_model' => 'First Phone',
            ],
            $request,
            false
        );

        $this->assertSame(CustomerLoginRiskService::NORMAL, $risk['level']);
        $this->assertSame(0, $risk['score']);
        $this->assertSame([], $risk['signals']);
    }

    public function test_new_device_with_new_ip_and_platform_is_suspicious(): void
    {
        $user = $this->user();

        $user->devices()->create([
            'uuid' => (string) Str::uuid(),
            'device_slot' => UserDevice::SLOT_MOBILE,
            'platform' => UserDevice::PLATFORM_ANDROID,
            'device_name' => 'Known Phone',
            'device_model' => 'Known Model',
            'installation_id_hash' => hash('sha256', (string) Str::uuid()),
            'is_trusted' => true,
            'is_active' => true,
            'registered_at' => now(),
            'trusted_at' => now(),
            'last_login_at' => now()->subMinute(),
            'last_seen_at' => now()->subMinute(),
            'last_login_ip' => '10.0.0.10',
        ]);

        $request = Request::create('/api/v1/auth/login', 'POST', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.99',
        ]);

        $risk = app(CustomerLoginRiskService::class)->analyze(
            $user->fresh(),
            [
                'platform' => UserDevice::PLATFORM_IOS,
                'device_model' => 'Different Model',
            ],
            $request,
            false
        );

        $this->assertSame(CustomerLoginRiskService::SUSPICIOUS, $risk['level']);
        $this->assertGreaterThanOrEqual(60, $risk['score']);
        $this->assertContains('NEW_DEVICE', $risk['signals']);
        $this->assertContains('NEW_IP', $risk['signals']);
        $this->assertContains('NEW_PLATFORM', $risk['signals']);
    }

    private function user(): User
    {
        return User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Risk Test User',
            'email' => 'risk-'.Str::lower(Str::random(8)).'@example.test',
            'email_verified_at' => now(),
            'password' => Hash::make('StrongPassword123!'),
            'referral_code' => 'REF'.Str::upper(Str::random(8)),
            'country_code' => 'LK',
            'language_code' => 'en',
            'account_type' => 'PREMIUM',
            'status' => 'ACTIVE',
            'premium_activated_at' => now(),
        ]);
    }
}
