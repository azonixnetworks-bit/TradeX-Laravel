<?php

namespace App\Services\TradeIdeas;

use App\Models\TradeIdea;
use App\Models\TradeIdeaTarget;
use App\Models\TradeIdeaUpdate;

class TradeIdeaDistributionPayloadBuilder
{
    public function build(
        TradeIdea $tradeIdea,
        string $eventType,
        ?TradeIdeaUpdate $update = null
    ): array {
        $tradeIdea->loadMissing(
            'targets'
        );

        $payload = [
            'version' => 1,
            'event_type' => $eventType,

            'trade_idea' => [
                'id' => $tradeIdea->id,
                'uuid' => $tradeIdea->uuid,
                'title' => $tradeIdea->title,
                'summary' => $tradeIdea->summary,
                'category' => $tradeIdea->category,
                'market_region' => $tradeIdea->market_region,
                'symbol' => $tradeIdea->symbol,
                'instrument_name' => $tradeIdea->instrument_name,
                'direction' => $tradeIdea->direction,
                'timeframe' => $tradeIdea->timeframe,

                'entry_price_low' => $tradeIdea->entry_price_low,
                'entry_price_high' => $tradeIdea->entry_price_high,
                'stop_loss' => $tradeIdea->stop_loss,
                'current_stop_loss' => $tradeIdea->current_stop_loss,
                'entry_price_actual' => $tradeIdea->entry_price_actual,

                'targets' => $tradeIdea->targets
                    ->map(
                        fn (
                            TradeIdeaTarget $target
                        ): array => [
                            'label' => $target->label,
                            'price' => $target->price,
                            'hit_at' => $target->hit_at?->toISOString(),
                            'hit_price' => $target->hit_price,
                        ]
                    )
                    ->values()
                    ->all(),

                'tradingview_url' => $tradeIdea->tradingview_url,

                'chart_image' => $tradeIdea->hasChartImage()
                    ? [
                        'disk' => $tradeIdea->chart_image_disk,
                        'path' => $tradeIdea->chart_image_path,
                        'mime_type' => $tradeIdea->chart_image_mime_type,
                        'size_bytes' => $tradeIdea->chart_image_size_bytes,
                    ]
                    : null,

                'status' => $tradeIdea->status,
                'published_at' => $tradeIdea->published_at?->toISOString(),

                'close_outcome' => $tradeIdea->close_outcome,
                'close_price' => $tradeIdea->close_price,
                'close_note' => $tradeIdea->close_note,
                'closed_at' => $tradeIdea->closed_at?->toISOString(),
            ],

            'update' => $update !== null
                ? [
                    'id' => $update->id,
                    'type' => $update->type,
                    'message' => $update->message,
                    'price' => $update->price,
                    'created_at' => $update->created_at?->toISOString(),
                ]
                : null,
        ];

        $payload['message'] = $this->message(
            $payload
        );

        return $payload;
    }

    public function hash(
        array $payload
    ): string {
        return hash(
            'sha256',
            json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
            )
        );
    }

    private function message(
        array $payload
    ): string {
        $idea = $payload['trade_idea'];
        $update = $payload['update'];

        $lines = [
            'TRADEX · TRADE IDEA',
            '',
            $idea['symbol']
                .' '
                .$idea['direction']
                .' · '
                .$idea['category']
                .' · '
                .$idea['timeframe'],
            $idea['title'],
        ];

        if (
            $idea['market_region'] !== null
        ) {
            $lines[] =
                'Market: '
                .str_replace(
                    '_',
                    ' ',
                    $idea['market_region']
                );
        }

        if (
            $idea['entry_price_low'] !== null
            || $idea['entry_price_high'] !== null
        ) {
            $lines[] =
                'Entry: '
                .$this->entryText(
                    $idea['entry_price_low'],
                    $idea['entry_price_high']
                );
        }

        if (
            $idea['current_stop_loss'] !== null
        ) {
            $lines[] =
                'SL: '
                .$idea['current_stop_loss'];
        } elseif (
            $idea['stop_loss'] !== null
        ) {
            $lines[] =
                'SL: '
                .$idea['stop_loss'];
        }

        if (
            is_array(
                $idea['targets']
            )
            && $idea['targets'] !== []
        ) {
            $targetParts = [];

            foreach (
                $idea['targets']
                as $target
            ) {
                $targetParts[] =
                    $target['label']
                    .': '
                    .$target['price']
                    .(
                        $target['hit_at'] !== null
                            ? ' ✓'
                            : ''
                    );
            }

            $lines[] =
                'Targets: '
                .implode(
                    ' · ',
                    $targetParts
                );
        }

        if (
            $update !== null
        ) {
            $lines[] = '';
            $lines[] =
                'UPDATE · '
                .str_replace(
                    '_',
                    ' ',
                    $update['type']
                );

            if (
                trim(
                    (string) $update['message']
                ) !== ''
            ) {
                $lines[] =
                    trim(
                        (string) $update['message']
                    );
            }
        }

        if (
            $idea['status']
            === TradeIdea::STATUS_CLOSED
        ) {
            $lines[] = '';
            $lines[] =
                'CLOSED · '
                .(
                    $idea['close_outcome']
                    ?? 'CLOSED'
                );

            if (
                $idea['close_price'] !== null
            ) {
                $lines[] =
                    'Close: '
                    .$idea['close_price'];
            }

            if (
                trim(
                    (string) $idea['close_note']
                ) !== ''
            ) {
                $lines[] =
                    trim(
                        (string) $idea['close_note']
                    );
            }
        }

        if (
            trim(
                (string) $idea['tradingview_url']
            ) !== ''
        ) {
            $lines[] = '';
            $lines[] =
                'TradingView: '
                .$idea['tradingview_url'];
        }

        $lines[] = '';
        $lines[] =
            'Educational trade idea. Manage risk responsibly.';

        return mb_substr(
            implode(
                "\n",
                $lines
            ),
            0,
            950
        );
    }

    private function entryText(
        mixed $low,
        mixed $high
    ): string {
        if (
            $low !== null
            && $high !== null
        ) {
            return $low === $high
                ? (string) $low
                : $low.' – '.$high;
        }

        if ($low !== null) {
            return '≥ '.$low;
        }

        return '≤ '.$high;
    }
}
