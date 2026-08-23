<?php

namespace Tests\Feature\Api\TradeIdeas;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\CreatesTradeIdeaApiContent;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class TradeIdeaRateLimitTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTradeXMobileSession;
    use CreatesTradeIdeaApiContent;

    public function test_share_action_has_dedicated_user_rate_limit(): void
    {
        config([
            'tradex_trade_ideas_api.rate_limits.share_per_minute' =>
                1,
        ]);

        RateLimiter::clear(
            'api-trade-ideas-share'
        );

        $tradeIdea =
            $this->createTradeIdeaApiContent();

        $user = $this->createApiUser([
            'account_type' =>
                'PREMIUM',
        ]);

        $token = $this->issueBoundToken(
            $user
        );

        $url =
            '/api/v1/trade-ideas/'
            .$tradeIdea->uuid
            .'/share-action';

        $this
            ->withToken(
                $token
            )
            ->postJson(
                $url,
                [
                    'channel' =>
                        'WHATSAPP',
                ]
            )
            ->assertOk();

        $this
            ->withToken(
                $token
            )
            ->postJson(
                $url,
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
