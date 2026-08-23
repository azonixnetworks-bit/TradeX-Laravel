<?php

namespace App\Services\TradeIdeas;

use App\Exceptions\AuthApiException;
use App\Models\AuditLog;
use App\Models\TradeIdea;
use App\Models\TradeIdeaDistribution;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TradeIdeaApiService
{
    public function assertPremium(
        User $user
    ): void {
        if (! $user->canAccessPremium()) {
            throw new AuthApiException(
                403,
                'TRADE_IDEA_PREMIUM_REQUIRED',
                'Premium access is required to use Trade Ideas.'
            );
        }
    }

    public function index(
        User $user,
        array $filters
    ): LengthAwarePaginator {
        $this->assertPremium(
            $user
        );

        $query = $this->availableQuery()
            ->with([
                'targets',
            ]);

        foreach ([
            'category',
            'market_region',
            'direction',
            'timeframe',
            'status',
            'symbol',
        ] as $field) {
            if (
                isset($filters[$field])
                && $filters[$field] !== ''
            ) {
                $query->where(
                    $field,
                    $filters[$field]
                );
            }
        }

        $perPage = (int) (
            $filters['per_page']
            ?? config(
                'tradex_trade_ideas_api.per_page',
                20
            )
        );

        $perPage = max(
            1,
            min(
                $perPage,
                max(
                    1,
                    (int) config(
                        'tradex_trade_ideas_api.max_per_page',
                        50
                    )
                )
            )
        );

        return $query
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(
                $perPage
            )
            ->withQueryString();
    }

    public function resolve(
        User $user,
        string $uuid
    ): TradeIdea {
        $this->assertPremium(
            $user
        );

        $tradeIdea = $this->availableQuery()
            ->where(
                'uuid',
                $uuid
            )
            ->first();

        if (! $tradeIdea instanceof TradeIdea) {
            throw new AuthApiException(
                404,
                'TRADE_IDEA_NOT_AVAILABLE',
                'This Trade Idea is not available.'
            );
        }

        return $tradeIdea;
    }

    public function summary(
        TradeIdea $tradeIdea
    ): array {
        $tradeIdea->loadMissing(
            'targets'
        );

        return [
            'uuid' =>
                $tradeIdea->uuid,

            'title' =>
                $tradeIdea->title,

            'summary' =>
                $tradeIdea->summary,

            'category' =>
                $tradeIdea->category,

            'market_region' =>
                $tradeIdea->market_region,

            'symbol' =>
                $tradeIdea->symbol,

            'instrument_name' =>
                $tradeIdea->instrument_name,

            'direction' =>
                $tradeIdea->direction,

            'timeframe' =>
                $tradeIdea->timeframe,

            'status' =>
                $tradeIdea->status,

            'entry' => [
                'low' =>
                    $tradeIdea->entry_price_low,

                'high' =>
                    $tradeIdea->entry_price_high,

                'actual' =>
                    $tradeIdea->entry_price_actual,

                'triggered_at' =>
                    $tradeIdea->entry_triggered_at
                        ?->toISOString(),
            ],

            'stop_loss' => [
                'initial' =>
                    $tradeIdea->stop_loss,

                'current' =>
                    $tradeIdea->current_stop_loss
                    ?? $tradeIdea->stop_loss,
            ],

            'targets' => [
                'total' =>
                    $tradeIdea->targets->count(),

                'hit' =>
                    $tradeIdea->targets
                        ->filter(
                            fn ($target): bool =>
                                $target->isHit()
                        )
                        ->count(),
            ],

            'chart_available' =>
                $tradeIdea->hasChartImage(),

            'chart_url' =>
                $tradeIdea->hasChartImage()
                    ? route(
                        'api.v1.trade-ideas.chart',
                        [
                            'tradeIdea' =>
                                $tradeIdea->uuid,
                        ]
                    )
                    : null,

            'tradingview_url' =>
                $tradeIdea->tradingview_url,

            'published_at' =>
                $tradeIdea->published_at
                    ?->toISOString(),

            'closed_at' =>
                $tradeIdea->closed_at
                    ?->toISOString(),

            'close_outcome' =>
                $tradeIdea->close_outcome,
        ];
    }

    public function detail(
        TradeIdea $tradeIdea
    ): array {
        $tradeIdea->loadMissing([
            'targets',
            'updates.target',
        ]);

        $data = $this->summary(
            $tradeIdea
        );

        $data['analysis'] =
            $tradeIdea->analysis;

        $data['targets'] =
            $tradeIdea->targets
                ->values()
                ->map(
                    static fn ($target): array => [
                        'label' =>
                            $target->label,

                        'price' =>
                            $target->price,

                        'sort_order' =>
                            $target->sort_order,

                        'is_hit' =>
                            $target->isHit(),

                        'hit_at' =>
                            $target->hit_at
                                ?->toISOString(),

                        'hit_price' =>
                            $target->hit_price,
                    ]
                )
                ->all();

        $data['timeline'] =
            $tradeIdea->updates
                ->values()
                ->map(
                    static fn ($update): array => [
                        'type' =>
                            $update->type,

                        'message' =>
                            $update->message,

                        'price' =>
                            $update->price,

                        'target' =>
                            $update->target
                                ? [
                                    'label' =>
                                        $update->target->label,

                                    'price' =>
                                        $update->target->price,
                                ]
                                : null,

                        'created_at' =>
                            $update->created_at
                                ?->toISOString(),
                    ]
                )
                ->all();

        $data['close'] = [
            'outcome' =>
                $tradeIdea->close_outcome,

            'price' =>
                $tradeIdea->close_price,

            'note' =>
                $tradeIdea->close_note,

            'closed_at' =>
                $tradeIdea->closed_at
                    ?->toISOString(),
        ];

        $data['actions'] = [
            'share_action_url' =>
                route(
                    'api.v1.trade-ideas.share-action',
                    [
                        'tradeIdea' =>
                            $tradeIdea->uuid,
                    ]
                ),

            'share_card_url' =>
                route(
                    'api.v1.trade-ideas.share-card.uuid',
                    [
                        'tradeIdea' =>
                            $tradeIdea->uuid,
                    ]
                ),
        ];

        $data['risk_notice'] =
            'Trade Ideas are educational market analysis and do not guarantee profit. Apply your own risk management.';

        return $data;
    }

    public function chartAsset(
        User $user,
        TradeIdea $tradeIdea,
        Request $request
    ): array {
        $this->assertPremium(
            $user
        );

        if (! $tradeIdea->hasChartImage()) {
            throw new AuthApiException(
                404,
                'TRADE_IDEA_CHART_NOT_AVAILABLE',
                'This Trade Idea does not have a chart image.'
            );
        }

        if ($tradeIdea->chart_image_disk !== 'private') {
            throw new AuthApiException(
                403,
                'TRADE_IDEA_CHART_STORAGE_INVALID',
                'Trade Idea chart storage is not valid.'
            );
        }

        $path = str_replace(
            '\\',
            '/',
            trim(
                (string) $tradeIdea->chart_image_path
            )
        );

        $expectedPrefix =
            'trade-ideas/'
            .$tradeIdea->uuid
            .'/charts/';

        if (
            $path === ''
            || str_contains(
                $path,
                '..'
            )
            || ! str_starts_with(
                $path,
                $expectedPrefix
            )
        ) {
            throw new AuthApiException(
                403,
                'TRADE_IDEA_CHART_STORAGE_INVALID',
                'Trade Idea chart storage is not valid.'
            );
        }

        $mimeType = strtolower(
            trim(
                (string) $tradeIdea
                    ->chart_image_mime_type
            )
        );

        if (
            ! in_array(
                $mimeType,
                [
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                ],
                true
            )
        ) {
            throw new AuthApiException(
                404,
                'TRADE_IDEA_CHART_NOT_AVAILABLE',
                'This Trade Idea chart is not available.'
            );
        }

        $disk = Storage::disk(
            'private'
        );

        if (! $disk->exists($path)) {
            throw new AuthApiException(
                404,
                'TRADE_IDEA_CHART_FILE_MISSING',
                'This Trade Idea chart file is not available.'
            );
        }

        $stream = $disk->readStream(
            $path
        );

        if ($stream === false) {
            throw new AuthApiException(
                404,
                'TRADE_IDEA_CHART_FILE_MISSING',
                'This Trade Idea chart file is not available.'
            );
        }

        AuditLog::record(
            actorType: 'USER',
            actorId: $user->getKey(),
            action: 'TRADE_IDEA_CHART_ACCESSED',
            module: 'TRADE_IDEAS',
            targetType: 'TRADE_IDEA',
            targetId: $tradeIdea->getKey(),
            newValues: [
                'trade_idea_uuid' =>
                    $tradeIdea->uuid,
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return [
            'stream' => $stream,
            'mime_type' => $mimeType,
        ];
    }

    public function prepareShareAction(
        User $user,
        TradeIdea $tradeIdea,
        string $channel,
        string $variant,
        Request $request
    ): array {
        $this->assertPremium(
            $user
        );

        $channel = strtoupper(
            trim(
                $channel
            )
        );

        $variant = strtolower(
            trim(
                $variant
            )
        );

        $affiliateUrl = $this->affiliateUrl(
            $user
        );

        $message = $this->shareMessage(
            $tradeIdea,
            $affiliateUrl
        );

        $shareUrl = match ($channel) {
            TradeIdeaDistribution::CHANNEL_WHATSAPP =>
                'https://wa.me/?text='.
                rawurlencode(
                    $message
                ),

            TradeIdeaDistribution::CHANNEL_TELEGRAM =>
                'https://t.me/share/url?url='.
                rawurlencode(
                    $affiliateUrl ?? ''
                )
                .'&text='.
                rawurlencode(
                    $message
                ),

            default =>
                throw new AuthApiException(
                    422,
                    'TRADE_IDEA_SHARE_CHANNEL_INVALID',
                    'Unsupported Trade Idea share channel.'
                ),
        };

        AuditLog::record(
            actorType: 'USER',
            actorId: $user->getKey(),
            action: 'TRADE_IDEA_USER_SHARE_ACTION_PREPARED',
            module: 'TRADE_IDEAS',
            targetType: 'TRADE_IDEA',
            targetId: $tradeIdea->getKey(),
            newValues: [
                'trade_idea_uuid' =>
                    $tradeIdea->uuid,

                'channel' =>
                    $channel,

                'variant' =>
                    $variant,
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return [
            'channel' =>
                $channel,

            'external_share_url' =>
                $shareUrl,

            'message' =>
                $message,

            'referral_code' =>
                $user->referral_code,

            'affiliate_url' =>
                $affiliateUrl,

            'share_card_url' =>
                route(
                    'api.v1.trade-ideas.share-card.uuid',
                    [
                        'tradeIdea' =>
                            $tradeIdea->uuid,

                        'variant' =>
                            $variant,
                    ]
                ),

            'delivery' => [
                'mode' =>
                    'USER_EXTERNAL_SHARE',

                'server_provider_send_triggered' =>
                    false,

                'delivery_verified_by_tradex' =>
                    false,
            ],
        ];
    }

    private function availableQuery(): Builder
    {
        return TradeIdea::query()
            ->whereIn(
                'status',
                [
                    TradeIdea::STATUS_PUBLISHED,
                    TradeIdea::STATUS_CLOSED,
                ]
            )
            ->whereNotNull(
                'published_at'
            )
            ->where(
                'published_at',
                '<=',
                now()
            );
    }

    private function affiliateUrl(
        User $user
    ): ?string {
        $base = trim(
            (string) config(
                'tradex_trade_ideas.share.affiliate_base_url',
                config(
                    'app.url'
                )
            )
        );

        $referralCode = trim(
            (string) $user->referral_code
        );

        if (
            $base === ''
            || $referralCode === ''
        ) {
            return null;
        }

        $separator = str_contains(
            $base,
            '?'
        )
            ? '&'
            : '?';

        return $base
            .$separator
            .'ref='
            .rawurlencode(
                $referralCode
            );
    }

    private function shareMessage(
        TradeIdea $tradeIdea,
        ?string $affiliateUrl
    ): string {
        $tradeIdea->loadMissing(
            'targets'
        );

        $lines = [
            'TradeX Trade Idea',
            $tradeIdea->symbol
                .' · '
                .$tradeIdea->direction
                .' · '
                .$tradeIdea->timeframe,
        ];

        if (
            $tradeIdea->entry_price_low !== null
            || $tradeIdea->entry_price_high !== null
        ) {
            $lines[] =
                'Entry: '
                .$tradeIdea->displayEntryZone();
        }

        $stop =
            $tradeIdea->current_stop_loss
            ?? $tradeIdea->stop_loss;

        if ($stop !== null) {
            $lines[] =
                'SL: '
                .$stop;
        }

        $targets = $tradeIdea->targets
            ->take(5)
            ->map(
                static fn ($target): string =>
                    ($target->label ?: 'TP')
                    .': '
                    .$target->price
                    .(
                        $target->isHit()
                            ? ' ✓'
                            : ''
                    )
            )
            ->all();

        if ($targets !== []) {
            $lines[] = implode(
                ' | ',
                $targets
            );
        }

        if (
            $tradeIdea->status
            === TradeIdea::STATUS_CLOSED
            && $tradeIdea->close_outcome !== null
        ) {
            $lines[] =
                'Outcome: '
                .$tradeIdea->close_outcome;
        }

        if ($affiliateUrl !== null) {
            $lines[] =
                'Join TradeX: '
                .$affiliateUrl;
        }

        $lines[] =
            'Educational market analysis only. Manage risk.';

        return implode(
            "\n",
            $lines
        );
    }
}
