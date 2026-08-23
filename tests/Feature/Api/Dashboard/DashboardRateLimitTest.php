<?php

namespace Tests\Feature\Api\Dashboard;

use Database\Seeders\AffiliateStageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class DashboardRateLimitTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_dashboard_read_rate_limit_is_enforced_per_authenticated_user(): void
    {
        $this->seed(
            AffiliateStageSeeder::class
        );

        config()->set(
            'tradex_dashboard.rate_limits.read_per_minute',
            1
        );

        $user = $this->createApiUser();

        $token = $this->issueBoundToken(
            $user
        );

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/dashboard'
            )
            ->assertOk();

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/dashboard'
            )
            ->assertStatus(429);
    }
}
