<?php

namespace App\Services\TradeIdeas;

use App\Jobs\GenerateTradeIdeaShareImage;
use App\Jobs\SendTelegramTradeIdea;
use App\Jobs\SendWhatsAppTradeIdea;
use App\Models\AdminUser;
use App\Models\TradeIdea;
use App\Models\TradeIdeaDistribution;
use App\Models\TradeIdeaUpdate;
use Illuminate\Support\Facades\DB;

class TradeIdeaDistributionManager
{
    public function __construct(
        private readonly TradeIdeaDistributionPayloadBuilder $payloadBuilder
    ) {
    }

    public function health(): array
    {
        $telegram = config(
            'tradex_trade_ideas.telegram',
            []
        );

        $whatsapp = config(
            'tradex_trade_ideas.whatsapp',
            []
        );

        $telegramDestinations =
            $this->telegramDestinations(
                $telegram['chat_ids']
                ?? []
            );

        $whatsappDestinations =
            $this->whatsappDestinations(
                $whatsapp['recipients']
                ?? []
            );

        $telegramReady =
            (bool) (
                $telegram['enabled']
                ?? false
            )
            && trim(
                (string) (
                    $telegram['bot_token']
                    ?? ''
                )
            ) !== ''
            && $telegramDestinations !== [];

        $whatsappMode = strtolower(
            trim(
                (string) (
                    $whatsapp['mode']
                    ?? 'template'
                )
            )
        );

        $whatsappCoreReady =
            (bool) (
                $whatsapp['enabled']
                ?? false
            )
            && trim(
                (string) (
                    $whatsapp['graph_version']
                    ?? ''
                )
            ) !== ''
            && trim(
                (string) (
                    $whatsapp['phone_number_id']
                    ?? ''
                )
            ) !== ''
            && trim(
                (string) (
                    $whatsapp['access_token']
                    ?? ''
                )
            ) !== ''
            && $whatsappDestinations !== [];

        $whatsappModeValid =
            in_array(
                $whatsappMode,
                [
                    'template',
                    'text',
                ],
                true
            );

        /*
        | A text template is required in template mode so a Trade Idea without
        | chart media always has a valid production path. Image template is
        | optional and used only when compatible media exists.
        */
        $whatsappTemplateReady =
            $whatsappMode === 'text'
            || (
                $whatsappMode === 'template'
                && trim(
                    (string) (
                        $whatsapp['template_text_name']
                        ?? ''
                    )
                ) !== ''
            );

        return [
            'telegram' => [
                'enabled' =>
                    (bool) (
                        $telegram['enabled']
                        ?? false
                    ),

                'ready' =>
                    $telegramReady,

                'destinations' =>
                    count(
                        $telegramDestinations
                    ),
            ],

            'whatsapp' => [
                'enabled' =>
                    (bool) (
                        $whatsapp['enabled']
                        ?? false
                    ),

                'ready' =>
                    $whatsappCoreReady
                    && $whatsappModeValid
                    && $whatsappTemplateReady,

                'mode' =>
                    $whatsappMode,

                'destinations' =>
                    count(
                        $whatsappDestinations
                    ),
            ],

            'auto' => config(
                'tradex_trade_ideas.auto',
                []
            ),
        ];
    }

    public function queueInitial(
        TradeIdea $tradeIdea,
        ?AdminUser $admin = null
    ): array {
        return $this->queue(
            $tradeIdea,
            TradeIdeaDistribution::EVENT_INITIAL,
            null,
            $admin
        );
    }

    public function queueUpdate(
        TradeIdea $tradeIdea,
        TradeIdeaUpdate $update,
        ?AdminUser $admin = null
    ): array {
        return $this->queue(
            $tradeIdea,
            TradeIdeaDistribution::EVENT_UPDATE,
            $update,
            $admin
        );
    }

    public function queueClosed(
        TradeIdea $tradeIdea,
        ?TradeIdeaUpdate $update,
        ?AdminUser $admin = null
    ): array {
        return $this->queue(
            $tradeIdea,
            TradeIdeaDistribution::EVENT_CLOSED,
            $update,
            $admin
        );
    }

    public function queueManual(
        TradeIdea $tradeIdea,
        AdminUser $admin
    ): array {
        return $this->queue(
            $tradeIdea,
            TradeIdeaDistribution::EVENT_MANUAL,
            null,
            $admin
        );
    }

    public function retry(
        TradeIdeaDistribution $distribution
    ): bool {
        if (
            $distribution->isSent()
            || $distribution->isProcessing()
        ) {
            return false;
        }

        $distribution->forceFill([
            'status' =>
                TradeIdeaDistribution::STATUS_PENDING,

            'queued_at' =>
                now(),

            'started_at' =>
                null,

            'failed_at' =>
                null,

            'last_error_code' =>
                null,

            'last_error_message' =>
                null,
        ])->save();

        $this->dispatch(
            $distribution
        );

        return true;
    }

    private function queue(
        TradeIdea $tradeIdea,
        string $eventType,
        ?TradeIdeaUpdate $update,
        ?AdminUser $admin
    ): array {
        abort_unless(
            $tradeIdea->isPublished()
            || $tradeIdea->isClosed(),
            422,
            'Only published or closed Trade Ideas can be distributed.'
        );

        $payload =
            $this->payloadBuilder->build(
                $tradeIdea,
                $eventType,
                $update
            );

        $payloadHash =
            $this->payloadBuilder->hash(
                $payload
            );

        $destinations =
            $this->configuredDestinations();

        $created = [];
        $existing = [];

        DB::transaction(function () use (
            $tradeIdea,
            $eventType,
            $update,
            $admin,
            $payload,
            $payloadHash,
            $destinations,
            &$created,
            &$existing
        ): void {
            foreach (
                $destinations
                as $destination
            ) {
                $destinationHash = hash(
                    'sha256',
                    $destination['value']
                );

                $idempotencyKey = hash(
                    'sha256',
                    implode(
                        '|',
                        [
                            $tradeIdea->uuid,
                            $eventType,
                            (string) (
                                $update?->id
                                ?? 'none'
                            ),
                            $destination['channel'],
                            $destinationHash,
                            $payloadHash,
                        ]
                    )
                );

                $distribution =
                    TradeIdeaDistribution::query()
                        ->where(
                            'idempotency_key',
                            $idempotencyKey
                        )
                        ->first();

                if (
                    $distribution !== null
                ) {
                    $existing[] =
                        $distribution;

                    continue;
                }

                $distribution =
                    TradeIdeaDistribution::query()
                        ->create([
                            'trade_idea_id' =>
                                $tradeIdea->id,

                            'trade_idea_update_id' =>
                                $update?->id,

                            'event_type' =>
                                $eventType,

                            'channel' =>
                                $destination['channel'],

                            'destination' =>
                                $destination['value'],

                            'destination_hash' =>
                                $destinationHash,

                            'idempotency_key' =>
                                $idempotencyKey,

                            'status' =>
                                TradeIdeaDistribution::STATUS_PENDING,

                            'payload_sha256' =>
                                $payloadHash,

                            'payload_snapshot' =>
                                $payload,

                            'attempt_count' =>
                                0,

                            'queued_at' =>
                                now(),

                            'created_by_admin_id' =>
                                $admin?->id,
                        ]);

                $created[] =
                    $distribution;
            }
        });

        foreach (
            $created
            as $distribution
        ) {
            $this->dispatch(
                $distribution
            );
        }

        if (
            $created !== []
            || $existing !== []
        ) {
            GenerateTradeIdeaShareImage::dispatch(
                $tradeIdea->id
            )->onQueue(
                (string) config(
                    'tradex_trade_ideas.queue',
                    'trade-ideas'
                )
            );
        }

        return [
            'created' =>
                count(
                    $created
                ),

            'existing' =>
                count(
                    $existing
                ),

            'configured_destinations' =>
                count(
                    $destinations
                ),
        ];
    }

    private function configuredDestinations(): array
    {
        $destinations = [];

        $health =
            $this->health();

        if (
            $health['telegram']['ready']
        ) {
            foreach (
                $this->telegramDestinations(
                    config(
                        'tradex_trade_ideas.telegram.chat_ids',
                        []
                    )
                )
                as $chatId
            ) {
                $destinations[] = [
                    'channel' =>
                        TradeIdeaDistribution::CHANNEL_TELEGRAM,

                    'value' =>
                        $chatId,
                ];
            }
        }

        if (
            $health['whatsapp']['ready']
        ) {
            foreach (
                $this->whatsappDestinations(
                    config(
                        'tradex_trade_ideas.whatsapp.recipients',
                        []
                    )
                )
                as $recipient
            ) {
                $destinations[] = [
                    'channel' =>
                        TradeIdeaDistribution::CHANNEL_WHATSAPP,

                    'value' =>
                        $recipient,
                ];
            }
        }

        return $destinations;
    }

    private function telegramDestinations(
        array $values
    ): array {
        $normalized = [];

        foreach (
            $values
            as $value
        ) {
            $value = trim(
                (string) $value
            );

            $isUsername =
                preg_match(
                    '/^@[A-Za-z0-9_]{5,32}$/',
                    $value
                ) === 1;

            $isNumericChatId =
                preg_match(
                    '/^-?\d{5,20}$/',
                    $value
                ) === 1;

            if (
                ! $isUsername
                && ! $isNumericChatId
            ) {
                continue;
            }

            $normalized[] =
                $value;
        }

        return array_values(
            array_unique(
                $normalized
            )
        );
    }

    private function whatsappDestinations(
        array $values
    ): array {
        $normalized = [];

        foreach (
            $values
            as $value
        ) {
            $digits = preg_replace(
                '/\D+/',
                '',
                (string) $value
            );

            if (
                $digits === null
                || preg_match(
                    '/^\d{8,15}$/',
                    $digits
                ) !== 1
            ) {
                continue;
            }

            $normalized[] =
                $digits;
        }

        return array_values(
            array_unique(
                $normalized
            )
        );
    }

    private function dispatch(
        TradeIdeaDistribution $distribution
    ): void {
        $queue =
            (string) config(
                'tradex_trade_ideas.queue',
                'trade-ideas'
            );

        if (
            $distribution->channel
            === TradeIdeaDistribution::CHANNEL_TELEGRAM
        ) {
            SendTelegramTradeIdea::dispatch(
                $distribution->id
            )->onQueue(
                $queue
            );

            return;
        }

        if (
            $distribution->channel
            === TradeIdeaDistribution::CHANNEL_WHATSAPP
        ) {
            SendWhatsAppTradeIdea::dispatch(
                $distribution->id
            )->onQueue(
                $queue
            );
        }
    }
}
