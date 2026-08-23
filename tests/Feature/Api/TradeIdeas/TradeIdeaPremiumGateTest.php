<?php

namespace Tests\Feature\Api\TradeIdeas;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTradeIdeaApiContent;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class TradeIdeaPremiumGateTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTradeXMobileSession;
    use CreatesTradeIdeaApiContent;

    public function test_free_user_is_blocked_from_trade_idea_feed_and_detail(): void
    {
        $tradeIdea =
            $this->createTradeIdeaApiContent();

        $user = $this->createApiUser([
            'account_type' =>
                'FREE',
        ]);

        $token = $this->issueBoundToken(
            $user
        );

        $this
            ->withToken(
                $token
            )
            ->getJson(
                '/api/v1/trade-ideas'
            )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'TRADE_IDEA_PREMIUM_REQUIRED'
            );

        $this
            ->withToken(
                $token
            )
            ->getJson(
                '/api/v1/trade-ideas/'
                .$tradeIdea->uuid
            )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'TRADE_IDEA_PREMIUM_REQUIRED'
            );
    }

    public function test_free_user_is_blocked_from_chart_share_card_and_share_action(): void
    {
        $tradeIdea =
            $this->createTradeIdeaApiContent(
                withChart: true
            );

        $user = $this->createApiUser([
            'account_type' =>
                'FREE',
        ]);

        $token = $this->issueBoundToken(
            $user
        );

        foreach ([
            '/api/v1/trade-ideas/'
                .$tradeIdea->uuid
                .'/chart',

            '/api/v1/trade-ideas/'
                .$tradeIdea->uuid
                .'/share-card.svg',
        ] as $url) {
            $this
                ->withToken(
                    $token
                )
                ->get(
                    $url,
                    [
                        'Accept' =>
                            'application/json',
                    ]
                )
                ->assertForbidden()
                ->assertJsonPath(
                    'error.code',
                    'TRADE_IDEA_PREMIUM_REQUIRED'
                );
        }

        $this
            ->withToken(
                $token
            )
            ->postJson(
                '/api/v1/trade-ideas/'
                .$tradeIdea->uuid
                .'/share-action',
                [
                    'channel' =>
                        'WHATSAPP',
                ]
            )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'TRADE_IDEA_PREMIUM_REQUIRED'
            );
    }
}
