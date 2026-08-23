<?php

namespace Tests\Feature\Api\Affiliate;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class AffiliateRateLimitTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_affiliate_share_action_has_dedicated_user_rate_limit(): void
    {
        config()->set(
            'tradex_affiliate_api.rate_limits.share_per_minute',
            1
        );

        RateLimiter::clear(
            'api-affiliate-share'
        );

        $user = $this->createApiUser([
            'account_type' => 'PREMIUM',
            'referral_code' => 'TXRATE09',
        ]);

        $token = $this->issueBoundToken(
            $user
        );

        $this
            ->withToken($token)
            ->postJson(
                '/api/v1/affiliate/share-action',
                [
                    'channel' =>
                        'WHATSAPP',
                ]
            )
            ->assertOk();

        $this
            ->withToken($token)
            ->postJson(
                '/api/v1/affiliate/share-action',
                [
                    'channel' =>
                        'WHATSAPP',
                ]
            )
            ->assertStatus(
                429
            );
    }
}
