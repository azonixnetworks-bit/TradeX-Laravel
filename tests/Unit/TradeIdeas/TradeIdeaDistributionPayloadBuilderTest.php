<?php

namespace Tests\Unit\TradeIdeas;

use App\Models\TradeIdea;
use App\Services\TradeIdeas\TradeIdeaDistributionPayloadBuilder;
use Illuminate\Support\Collection;
use Tests\TestCase;

class TradeIdeaDistributionPayloadBuilderTest extends TestCase
{
    public function test_minimal_global_trade_idea_builds_message_without_inventing_optional_structure(): void
    {
        $tradeIdea =
            new TradeIdea([
                'id' =>
                    10,

                'uuid' =>
                    '11111111-1111-1111-1111-111111111111',

                'title' =>
                    'Minimal Idea',

                'category' =>
                    TradeIdea::CATEGORY_FOREX,

                'symbol' =>
                    'EURUSD',

                'direction' =>
                    TradeIdea::DIRECTION_BUY,

                'timeframe' =>
                    'H1',

                'status' =>
                    TradeIdea::STATUS_PUBLISHED,
            ]);

        $tradeIdea->setRelation(
            'targets',
            new Collection()
        );

        $payload = app(
            TradeIdeaDistributionPayloadBuilder::class
        )->build(
            $tradeIdea,
            'MANUAL'
        );

        $this->assertStringContainsString(
            'EURUSD BUY',
            $payload['message']
        );

        $this->assertStringNotContainsString(
            'Entry:',
            $payload['message']
        );

        $this->assertStringNotContainsString(
            'SL:',
            $payload['message']
        );

        $this->assertStringNotContainsString(
            'Targets:',
            $payload['message']
        );
    }
}
