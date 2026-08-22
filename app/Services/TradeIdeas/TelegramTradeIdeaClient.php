<?php

namespace App\Services\TradeIdeas;

use App\Exceptions\TradeIdeaDistributionException;
use App\Models\TradeIdeaDistribution;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class TelegramTradeIdeaClient
{
    public function send(
        TradeIdeaDistribution $distribution
    ): array {
        $config = config(
            'tradex_trade_ideas.telegram',
            []
        );

        $token = trim(
            (string) (
                $config['bot_token']
                ?? ''
            )
        );

        if (
            ! ($config['enabled'] ?? false)
            || $token === ''
        ) {
            throw new TradeIdeaDistributionException(
                'TELEGRAM',
                'NOT_CONFIGURED',
                'Telegram distribution is not configured.'
            );
        }

        $destination = trim(
            (string) $distribution->destination
        );

        if ($destination === '') {
            throw new TradeIdeaDistributionException(
                'TELEGRAM',
                'DESTINATION_MISSING',
                'Telegram destination is missing.'
            );
        }

        $payload = (array) $distribution->payload_snapshot;
        $message = trim(
            (string) (
                $payload['message']
                ?? ''
            )
        );

        $base = rtrim(
            (string) (
                $config['api_base_url']
                ?? 'https://api.telegram.org'
            ),
            '/'
        );

        $timeout = max(
            5,
            (int) (
                $config['timeout_seconds']
                ?? 20
            )
        );

        $protectContent = (bool) (
            $config['protect_content']
            ?? true
        );

        $chart =
            $payload['trade_idea']['chart_image']
            ?? null;

        if (
            is_array(
                $chart
            )
            && $this->canSendAsPhoto(
                $chart
            )
        ) {
            return $this->sendPhoto(
                $base,
                $token,
                $destination,
                $message,
                $chart,
                $protectContent,
                $timeout
            );
        }

        $response = Http::timeout(
            $timeout
        )
            ->asForm()
            ->post(
                $base
                .'/bot'
                .$token
                .'/sendMessage',
                [
                    'chat_id' => $destination,
                    'text' => $message,
                    'protect_content' =>
                        $protectContent
                            ? 'true'
                            : 'false',
                    'disable_web_page_preview' =>
                        'false',
                ]
            );

        return $this->parseResponse(
            $response
        );
    }

    private function sendPhoto(
        string $base,
        string $token,
        string $destination,
        string $message,
        array $chart,
        bool $protectContent,
        int $timeout
    ): array {
        $disk = (string) (
            $chart['disk']
            ?? ''
        );

        $path = (string) (
            $chart['path']
            ?? ''
        );

        if (
            $disk === ''
            || $path === ''
            || ! Storage::disk(
                $disk
            )->exists(
                $path
            )
        ) {
            throw new TradeIdeaDistributionException(
                'TELEGRAM',
                'MEDIA_MISSING',
                'Trade Idea chart image is unavailable.'
            );
        }

        $stream = Storage::disk(
            $disk
        )->readStream(
            $path
        );

        if ($stream === false) {
            throw new TradeIdeaDistributionException(
                'TELEGRAM',
                'MEDIA_READ_FAILED',
                'Trade Idea chart image could not be read.'
            );
        }

        try {
            $response = Http::timeout(
                $timeout
            )
                ->attach(
                    'photo',
                    $stream,
                    basename(
                        $path
                    ),
                    [
                        'Content-Type' =>
                            (string) (
                                $chart['mime_type']
                                ?? 'image/jpeg'
                            ),
                    ]
                )
                ->post(
                    $base
                    .'/bot'
                    .$token
                    .'/sendPhoto',
                    [
                        'chat_id' => $destination,
                        'caption' => $message,
                        'protect_content' =>
                            $protectContent
                                ? 'true'
                                : 'false',
                    ]
                );
        } finally {
            fclose(
                $stream
            );
        }

        return $this->parseResponse(
            $response
        );
    }

    private function canSendAsPhoto(
        array $chart
    ): bool {
        $mimeAllowed = in_array(
            strtolower(
                (string) (
                    $chart['mime_type']
                    ?? ''
                )
            ),
            [
                'image/jpeg',
                'image/png',
            ],
            true
        );

        $size =
            (int) (
                $chart['size_bytes']
                ?? 0
            );

        return $mimeAllowed
            && (
                $size <= 0
                || $size <= 10485760
            );
    }

    private function parseResponse(
        Response $response
    ): array {
        $json = $response->json();

        if (
            ! $response->successful()
            || ! is_array(
                $json
            )
            || ($json['ok'] ?? false) !== true
        ) {
            throw new TradeIdeaDistributionException(
                'TELEGRAM',
                (string) (
                    is_array(
                        $json
                    )
                        ? (
                            $json['error_code']
                            ?? $response->status()
                        )
                        : $response->status()
                ),
                'Telegram provider rejected the Trade Idea distribution.'
            );
        }

        return [
            'provider_message_id' =>
                isset(
                    $json['result']['message_id']
                )
                    ? (string) $json['result']['message_id']
                    : null,

            'response_meta' => [
                'ok' => true,
                'message_id' =>
                    $json['result']['message_id']
                    ?? null,
                'chat_id' =>
                    $json['result']['chat']['id']
                    ?? null,
            ],
        ];
    }
}
