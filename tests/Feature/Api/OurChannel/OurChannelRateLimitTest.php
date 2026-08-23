<?php

namespace Tests\Feature\Api\OurChannel;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOurChannelApiContent;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class OurChannelRateLimitTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTradeXMobileSession;
    use CreatesOurChannelApiContent;

    public function test_read_rate_limit_is_enforced_per_authenticated_user(): void
    {
        config([
            'tradex_our_channel_api.rate_limits.read_per_minute' => 1,
        ]);

        $this->createOurChannelVideo();

        $user = $this->createApiUser();
        $token = $this->issueBoundToken(
            $user
        );

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/our-channel/videos'
            )
            ->assertOk();

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/our-channel/videos'
            )
            ->assertStatus(429);
    }
}
