<?php

namespace Tests\Unit\TradeIdeas;

use App\Models\TradeIdea;
use App\Services\TradeIdeas\TradeIdeaStructureValidator;
use PHPUnit\Framework\TestCase;

class TradeIdeaStructureValidatorTest extends TestCase
{
    public function test_empty_optional_trade_structure_passes(): void
    {
        $errors = (new TradeIdeaStructureValidator())->errors(
            TradeIdea::DIRECTION_BUY,
            null,
            null,
            null,
            []
        );

        $this->assertSame(
            [],
            $errors
        );
    }

    public function test_partial_optional_trade_structure_can_pass(): void
    {
        $errors = (new TradeIdeaStructureValidator())->errors(
            TradeIdea::DIRECTION_BUY,
            100,
            null,
            null,
            []
        );

        $this->assertSame(
            [],
            $errors
        );
    }

    public function test_valid_buy_structure_passes_when_supplied(): void
    {
        $errors = (new TradeIdeaStructureValidator())->errors(
            TradeIdea::DIRECTION_BUY,
            100,
            101,
            95,
            [
                ['price' => 110],
                ['price' => 120],
                ['price' => 130],
            ]
        );

        $this->assertSame(
            [],
            $errors
        );
    }

    public function test_buy_relationship_is_validated_only_for_supplied_values(): void
    {
        $errors = (new TradeIdeaStructureValidator())->errors(
            TradeIdea::DIRECTION_BUY,
            100,
            101,
            100,
            [
                ['price' => 110],
                ['price' => 109],
            ]
        );

        $this->assertArrayHasKey(
            'stop_loss',
            $errors
        );

        $this->assertArrayHasKey(
            'targets.1.price',
            $errors
        );
    }

    public function test_valid_sell_structure_passes_when_supplied(): void
    {
        $errors = (new TradeIdeaStructureValidator())->errors(
            TradeIdea::DIRECTION_SELL,
            100,
            101,
            105,
            [
                ['price' => 90],
                ['price' => 80],
                ['price' => 70],
            ]
        );

        $this->assertSame(
            [],
            $errors
        );
    }

    public function test_sell_relationship_is_validated_only_for_supplied_values(): void
    {
        $errors = (new TradeIdeaStructureValidator())->errors(
            TradeIdea::DIRECTION_SELL,
            100,
            101,
            101,
            [
                ['price' => 90],
                ['price' => 95],
            ]
        );

        $this->assertArrayHasKey(
            'stop_loss',
            $errors
        );

        $this->assertArrayHasKey(
            'targets.1.price',
            $errors
        );
    }
}
