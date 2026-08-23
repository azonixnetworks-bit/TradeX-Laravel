<?php

namespace Tests\Feature\Api\TradeIdeas;

use App\Models\TradeIdeaDistribution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTradeIdeaApiContent;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class TradeIdeaShareActionTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTradeXMobileSession;
    use CreatesTradeIdeaApiContent;

    public function test_whatsapp_share_action_is_external_metadata_only_and_is_audited(): void
    {
        config([
            'tradex_trade_ideas.share.affiliate_base_url' =>
                'https://app.tradex.test/register',
        ]);

        $tradeIdea =
            $this->createTradeIdeaApiContent();

        $user = $this->createApiUser([
            'account_type' =>
                'PREMIUM',

            'referral_code' =>
                'TXUSER123',
        ]);

        $token = $this->issueBoundToken(
            $user
        );

        $response = $this
            ->withToken(
                $token
            )
            ->postJson(
                '/api/v1/trade-ideas/'
                .$tradeIdea->uuid
                .'/share-action',
                [
                    'channel' =>
                        TradeIdeaDistribution::CHANNEL_WHATSAPP,

                    'variant' =>
                        'story',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.share_action.channel',
                'WHATSAPP'
            )
            ->assertJsonPath(
                'data.share_action.referral_code',
                'TXUSER123'
            )
            ->assertJsonPath(
                'data.share_action.delivery.server_provider_send_triggered',
                false
            )
            ->assertJsonPath(
                'data.share_action.delivery.delivery_verified_by_tradex',
                false
            );

        $this->assertStringStartsWith(
            'https://wa.me/?text=',
            (string) $response->json(
                'data.share_action.external_share_url'
            )
        );

        $this->assertStringContainsString(
            'ref=TXUSER123',
            (string) $response->json(
                'data.share_action.affiliate_url'
            )
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_type' =>
                    'USER',

                'actor_id' =>
                    $user->getKey(),

                'action' =>
                    'TRADE_IDEA_USER_SHARE_ACTION_PREPARED',

                'target_id' =>
                    $tradeIdea->getKey(),
            ]
        );

        $this->assertDatabaseCount(
            'trade_idea_distributions',
            0
        );
    }

    public function test_telegram_share_action_is_supported(): void
    {
        $tradeIdea =
            $this->createTradeIdeaApiContent();

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
            ->postJson(
                '/api/v1/trade-ideas/'
                .$tradeIdea->uuid
                .'/share-action',
                [
                    'channel' =>
                        TradeIdeaDistribution::CHANNEL_TELEGRAM,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.share_action.channel',
                'TELEGRAM'
            );

        $this->assertStringStartsWith(
            'https://t.me/share/url?',
            (string) $response->json(
                'data.share_action.external_share_url'
            )
        );
    }

    public function test_uuid_share_card_reuses_existing_personalized_share_card_core(): void
    {
        config([
            'tradex_trade_ideas.share.affiliate_base_url' =>
                'https://app.tradex.test/register',
        ]);

        $tradeIdea =
            $this->createTradeIdeaApiContent();

        $user = $this->createApiUser([
            'account_type' =>
                'PREMIUM',

            'referral_code' =>
                'TXCARD123',
        ]);

        $token = $this->issueBoundToken(
            $user
        );

        $response = $this
            ->withToken(
                $token
            )
            ->get(
                '/api/v1/trade-ideas/'
                .$tradeIdea->uuid
                .'/share-card.svg?variant=square'
            )
            ->assertOk()
            ->assertHeader(
                'Content-Type',
                'image/svg+xml; charset=UTF-8'
            );

        $this->assertStringContainsString(
            'TXCARD123',
            $response->getContent()
        );

        $this->assertStringContainsString(
            'viewBox="0 0 1080 1080"',
            $response->getContent()
        );
    }
}
