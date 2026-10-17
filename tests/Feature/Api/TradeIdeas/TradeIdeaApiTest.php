<?php

namespace Tests\Feature\Api\TradeIdeas;

use App\Models\TradeIdea;
use App\Models\UserTradeIdeaReaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTradeIdeaApiContent;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class TradeIdeaApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTradeXMobileSession;
    use CreatesTradeIdeaApiContent;

    public function test_premium_user_receives_only_available_trade_ideas(): void
    {
        $published =
            $this->createTradeIdeaApiContent();

        $closed =
            $this->createTradeIdeaApiContent(
                TradeIdea::STATUS_CLOSED,
                [
                    'symbol' =>
                        'BTCUSD',

                    'category' =>
                        TradeIdea::CATEGORY_CRYPTO,
                ]
            );

        $draft =
            $this->createTradeIdeaApiContent(
                TradeIdea::STATUS_DRAFT,
                [
                    'symbol' =>
                        'XAUUSD',
                ]
            );

        $user = $this->createApiUser([
            'account_type' =>
                'PREMIUM',
        ]);

        $token = $this->issueBoundToken(
            $user
        );

        $response = $this
            ->withToken(
                $token
            )
            ->getJson(
                '/api/v1/trade-ideas'
            )
            ->assertOk()
            ->assertJsonPath(
                'meta.total',
                2
            );

        $uuids = collect(
            $response->json(
                'data.trade_ideas'
            )
        )->pluck(
            'uuid'
        );

        $this->assertTrue(
            $uuids->contains(
                $published->uuid
            )
        );

        $this->assertTrue(
            $uuids->contains(
                $closed->uuid
            )
        );

        $this->assertFalse(
            $uuids->contains(
                $draft->uuid
            )
        );
    }

    public function test_trade_idea_filters_are_applied(): void
    {
        $this->createTradeIdeaApiContent();

        $stock =
            $this->createTradeIdeaApiContent(
                TradeIdea::STATUS_PUBLISHED,
                [
                    'symbol' =>
                        'JKH',

                    'category' =>
                        TradeIdea::CATEGORY_STOCKS,

                    'market_region' =>
                        TradeIdea::REGION_SRI_LANKA,

                    'direction' =>
                        TradeIdea::DIRECTION_SELL,

                    'timeframe' =>
                        'D1',
                ]
            );

        $user = $this->createApiUser([
            'account_type' =>
                'PREMIUM',
        ]);

        $token = $this->issueBoundToken(
            $user
        );

        $this
            ->withToken(
                $token
            )
            ->getJson(
                '/api/v1/trade-ideas?category=stocks&market_region=sri_lanka&direction=sell&timeframe=d1&symbol=jkh'
            )
            ->assertOk()
            ->assertJsonPath(
                'meta.total',
                1
            )
            ->assertJsonPath(
                'data.trade_ideas.0.uuid',
                $stock->uuid
            );
    }

    public function test_detail_returns_targets_timeline_and_never_leaks_private_storage_fields(): void
    {
        $tradeIdea =
            $this->createTradeIdeaApiContent(
                TradeIdea::STATUS_PUBLISHED,
                [],
                true
            );

        $user = $this->createApiUser([
            'account_type' =>
                'PREMIUM',
        ]);

        $token = $this->issueBoundToken(
            $user
        );

        $response = $this
            ->withToken(
                $token
            )
            ->getJson(
                '/api/v1/trade-ideas/'
                .$tradeIdea->uuid
            )
            ->assertOk()
            ->assertJsonPath(
                'data.trade_idea.uuid',
                $tradeIdea->uuid
            )
            ->assertJsonPath(
                'data.trade_idea.targets.0.label',
                'TP1'
            )
            ->assertJsonPath(
                'data.trade_idea.targets.0.is_hit',
                true
            );

        $payload = $response->json(
            'data.trade_idea'
        );

        $this->assertArrayNotHasKey(
            'id',
            $payload
        );

        $this->assertArrayNotHasKey(
            'chart_image_disk',
            $payload
        );

        $this->assertArrayNotHasKey(
            'chart_image_path',
            $payload
        );

        $this->assertArrayNotHasKey(
            'chart_image_size_bytes',
            $payload
        );

        $this->assertNotEmpty(
            $payload['timeline']
        );
    }

    public function test_draft_uuid_is_not_resolvable_from_mobile_api(): void
    {
        $draft =
            $this->createTradeIdeaApiContent(
                TradeIdea::STATUS_DRAFT
            );

        $user = $this->createApiUser([
            'account_type' =>
                'PREMIUM',
        ]);

        $token = $this->issueBoundToken(
            $user
        );

        $this
            ->withToken(
                $token
            )
            ->getJson(
                '/api/v1/trade-ideas/'
                .$draft->uuid
            )
            ->assertNotFound()
            ->assertJsonPath(
                'error.code',
                'TRADE_IDEA_NOT_AVAILABLE'
            );
    }

    public function test_premium_user_can_toggle_signal_reactions(): void
    {
        $idea = $this->createTradeIdeaApiContent();
        $user = $this->createApiUser(['account_type' => 'PREMIUM']);
        $token = $this->issueBoundToken($user);
        $endpoint = '/api/v1/trade-ideas/'
            .$idea->uuid
            .'/reaction';

        $this->withToken($token)
            ->putJson($endpoint, ['reaction' => 'like'])
            ->assertOk()
            ->assertJsonPath('data.reaction.likes_count', 1)
            ->assertJsonPath('data.reaction.dislikes_count', 0)
            ->assertJsonPath(
                'data.reaction.viewer_reaction',
                UserTradeIdeaReaction::LIKE
            );

        $this->withToken($token)
            ->putJson($endpoint, ['reaction' => 'dislike'])
            ->assertOk()
            ->assertJsonPath('data.reaction.likes_count', 0)
            ->assertJsonPath('data.reaction.dislikes_count', 1)
            ->assertJsonPath(
                'data.reaction.viewer_reaction',
                UserTradeIdeaReaction::DISLIKE
            );
    }
}
