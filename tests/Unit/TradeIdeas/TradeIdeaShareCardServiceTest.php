<?php

namespace Tests\Unit\TradeIdeas;

use App\Models\TradeIdea;
use App\Models\User;
use App\Services\TradeIdeas\TradeIdeaShareCardService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class TradeIdeaShareCardServiceTest extends TestCase
{
    public function test_personalized_share_card_contains_referral_code_and_affiliate_link(): void
    {
        config([
            'tradex_trade_ideas.share.affiliate_base_url' =>
                'https://app.tradex.test/register',
        ]);

        $tradeIdea =
            new TradeIdea([
                'title' =>
                    'EURUSD Market Idea',

                'category' =>
                    TradeIdea::CATEGORY_FOREX,

                'symbol' =>
                    'EURUSD',

                'direction' =>
                    TradeIdea::DIRECTION_BUY,

                'timeframe' =>
                    'H1',

                'entry_price_low' =>
                    null,

                'entry_price_high' =>
                    null,

                'stop_loss' =>
                    null,
            ]);

        $tradeIdea->setRelation(
            'targets',
            new Collection()
        );

        $user =
            new User([
                'uuid' =>
                    '11111111-1111-1111-1111-111111111111',

                'referral_code' =>
                    'ABC123',
            ]);

        $svg = app(
            TradeIdeaShareCardService::class
        )->renderSvg(
            $tradeIdea,
            $user
        );

        $this->assertStringContainsString(
            'ABC123',
            $svg
        );

        $this->assertStringContainsString(
            'https://app.tradex.test/register?ref=ABC123',
            $svg
        );

        $this->assertStringContainsString(
            'EURUSD',
            $svg
        );
    }

    public function test_generic_share_card_does_not_invent_referral_code(): void
    {
        $tradeIdea =
            new TradeIdea([
                'title' =>
                    'BTCUSD Market Idea',

                'category' =>
                    TradeIdea::CATEGORY_CRYPTO,

                'symbol' =>
                    'BTCUSD',

                'direction' =>
                    TradeIdea::DIRECTION_SELL,

                'timeframe' =>
                    'H4',
            ]);

        $tradeIdea->setRelation(
            'targets',
            new Collection()
        );

        $svg = app(
            TradeIdeaShareCardService::class
        )->renderSvg(
            $tradeIdea
        );

        $this->assertStringContainsString(
            'BTCUSD',
            $svg
        );

        $this->assertStringNotContainsString(
            'REFERRAL',
            $svg
        );
    }
}
