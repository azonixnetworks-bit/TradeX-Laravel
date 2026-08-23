<?php

namespace Tests\Concerns;

use App\Models\TradeIdea;
use App\Models\TradeIdeaTarget;
use App\Models\TradeIdeaUpdate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait CreatesTradeIdeaApiContent
{
    protected function createTradeIdeaApiContent(
        string $status = TradeIdea::STATUS_PUBLISHED,
        array $overrides = [],
        bool $withChart = false
    ): TradeIdea {
        $publishedAt =
            $status === TradeIdea::STATUS_DRAFT
                ? null
                : now()->subMinute();

        $closedAt =
            $status === TradeIdea::STATUS_CLOSED
                ? now()
                : null;

        $tradeIdea = TradeIdea::query()
            ->create(
                array_merge(
                    [
                        'title' =>
                            'EURUSD London continuation',

                        'summary' =>
                            'Liquidity sweep and continuation confirmation.',

                        'analysis' =>
                            'Price swept liquidity and reclaimed the institutional zone.',

                        'tradingview_url' =>
                            'https://www.tradingview.com/chart/',

                        'category' =>
                            TradeIdea::CATEGORY_FOREX,

                        'market_region' =>
                            null,

                        'symbol' =>
                            'EURUSD',

                        'instrument_name' =>
                            'Euro / US Dollar',

                        'direction' =>
                            TradeIdea::DIRECTION_BUY,

                        'timeframe' =>
                            'H1',

                        'entry_price_low' =>
                            '1.1700000000',

                        'entry_price_high' =>
                            '1.1720000000',

                        'stop_loss' =>
                            '1.1650000000',

                        'current_stop_loss' =>
                            null,

                        'entry_triggered_at' =>
                            now()->subSeconds(30),

                        'entry_price_actual' =>
                            '1.1710000000',

                        'status' =>
                            $status,

                        'published_at' =>
                            $publishedAt,

                        'closed_at' =>
                            $closedAt,

                        'close_outcome' =>
                            $status === TradeIdea::STATUS_CLOSED
                                ? TradeIdea::OUTCOME_WIN
                                : null,

                        'close_price' =>
                            $status === TradeIdea::STATUS_CLOSED
                                ? '1.1900000000'
                                : null,

                        'close_note' =>
                            $status === TradeIdea::STATUS_CLOSED
                                ? 'Trade closed after target completion.'
                                : null,
                    ],
                    $overrides
                )
            );

        $targetOne = TradeIdeaTarget::query()
            ->create([
                'trade_idea_id' =>
                    $tradeIdea->getKey(),

                'label' =>
                    'TP1',

                'price' =>
                    '1.1800000000',

                'sort_order' =>
                    1,

                'hit_at' =>
                    now()->subSeconds(10),

                'hit_price' =>
                    '1.1801000000',
            ]);

        TradeIdeaTarget::query()
            ->create([
                'trade_idea_id' =>
                    $tradeIdea->getKey(),

                'label' =>
                    'TP2',

                'price' =>
                    '1.1900000000',

                'sort_order' =>
                    2,
            ]);

        TradeIdeaUpdate::query()
            ->create([
                'trade_idea_id' =>
                    $tradeIdea->getKey(),

                'trade_idea_target_id' =>
                    $targetOne->getKey(),

                'type' =>
                    TradeIdeaUpdate::TYPE_TARGET_HIT,

                'message' =>
                    'TP1 hit. Protect remaining position.',

                'price' =>
                    '1.1801000000',
            ]);

        TradeIdeaUpdate::query()
            ->create([
                'trade_idea_id' =>
                    $tradeIdea->getKey(),

                'trade_idea_target_id' =>
                    null,

                'type' =>
                    TradeIdeaUpdate::TYPE_NOTE,

                'message' =>
                    'London continuation remains valid.',

                'price' =>
                    null,
            ]);

        if ($withChart) {
            Storage::fake(
                'private'
            );

            $path =
                'trade-ideas/'
                .$tradeIdea->uuid
                .'/charts/'
                .Str::uuid()->toString()
                .'.png';

            Storage::disk(
                'private'
            )->put(
                $path,
                'fake-png-content'
            );

            $tradeIdea->forceFill([
                'chart_image_disk' =>
                    'private',

                'chart_image_path' =>
                    $path,

                'chart_image_mime_type' =>
                    'image/png',

                'chart_image_size_bytes' =>
                    16,
            ])->save();
        }

        return $tradeIdea->fresh([
            'targets',
            'updates.target',
        ]);
    }
}
