<?php

namespace App\Services\TradeIdeas;

use App\Exceptions\TradeIdeaDistributionException;
use App\Models\TradeIdeaDistribution;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class WhatsAppTradeIdeaClient
{
    public function send(
        TradeIdeaDistribution $distribution
    ): array {
        $config = config(
            'tradex_trade_ideas.whatsapp',
            []
        );

        if (
            ! ($config['enabled'] ?? false)
        ) {
            throw new TradeIdeaDistributionException(
                'WHATSAPP',
                'NOT_CONFIGURED',
                'WhatsApp distribution is not enabled.'
            );
        }

        $version = trim(
            (string) (
                $config['graph_version']
                ?? ''
            )
        );

        $phoneNumberId = trim(
            (string) (
                $config['phone_number_id']
                ?? ''
            )
        );

        $accessToken = trim(
            (string) (
                $config['access_token']
                ?? ''
            )
        );

        if (
            $version === ''
            || $phoneNumberId === ''
            || $accessToken === ''
        ) {
            throw new TradeIdeaDistributionException(
                'WHATSAPP',
                'NOT_CONFIGURED',
                'WhatsApp Cloud API credentials are incomplete.'
            );
        }

        $destination = preg_replace(
            '/\D+/',
            '',
            (string) $distribution->destination
        );

        if (
            $destination === null
            || $destination === ''
        ) {
            throw new TradeIdeaDistributionException(
                'WHATSAPP',
                'DESTINATION_MISSING',
                'WhatsApp destination is missing.'
            );
        }

        $payload =
            (array) $distribution->payload_snapshot;

        $message = trim(
            (string) (
                $payload['message']
                ?? ''
            )
        );

        $base = rtrim(
            (string) (
                $config['api_base_url']
                ?? 'https://graph.facebook.com'
            ),
            '/'
        );

        $timeout = max(
            5,
            (int) (
                $config['timeout_seconds']
                ?? 25
            )
        );

        $endpoint =
            $base
            .'/'
            .$version
            .'/'
            .$phoneNumberId;

        $mode = strtolower(
            trim(
                (string) (
                    $config['mode']
                    ?? 'template'
                )
            )
        );

        return match ($mode) {
            'template' =>
                $this->sendTemplate(
                    $endpoint,
                    $accessToken,
                    $destination,
                    $message,
                    $payload,
                    $config,
                    $timeout
                ),

            'text' =>
                $this->sendFreeForm(
                    $endpoint,
                    $accessToken,
                    $destination,
                    $message,
                    $payload,
                    $timeout
                ),

            default =>
                throw new TradeIdeaDistributionException(
                    'WHATSAPP',
                    'MODE_INVALID',
                    'WhatsApp distribution mode is invalid.'
                ),
        };
    }

    private function sendTemplate(
        string $endpoint,
        string $accessToken,
        string $destination,
        string $message,
        array $payload,
        array $config,
        int $timeout
    ): array {
        $chart =
            $payload['trade_idea']['chart_image']
            ?? null;

        $canUseImage =
            is_array(
                $chart
            )
            && $this->canSendAsImage(
                $chart
            );

        $textTemplate = trim(
            (string) (
                $config['template_text_name']
                ?? ''
            )
        );

        $imageTemplate = trim(
            (string) (
                $config['template_image_name']
                ?? ''
            )
        );

        $templateName =
            $canUseImage
            && $imageTemplate !== ''
                ? $imageTemplate
                : $textTemplate;

        if ($templateName === '') {
            throw new TradeIdeaDistributionException(
                'WHATSAPP',
                'TEMPLATE_MISSING',
                'No approved WhatsApp template is configured for this Trade Idea.'
            );
        }

        $components = [];

        if (
            $canUseImage
            && $templateName === $imageTemplate
        ) {
            $mediaId = $this->uploadImage(
                $endpoint,
                $accessToken,
                $chart,
                $timeout
            );

            $components[] = [
                'type' => 'header',
                'parameters' => [
                    [
                        'type' => 'image',
                        'image' => [
                            'id' => $mediaId,
                        ],
                    ],
                ],
            ];
        }

        $components[] = [
            'type' => 'body',
            'parameters' => [
                [
                    'type' => 'text',
                    'text' => $message,
                ],
            ],
        ];

        $response = Http::timeout(
            $timeout
        )
            ->withToken(
                $accessToken
            )
            ->acceptJson()
            ->post(
                $endpoint
                .'/messages',
                [
                    'messaging_product' =>
                        'whatsapp',

                    'recipient_type' =>
                        'individual',

                    'to' =>
                        $destination,

                    'type' =>
                        'template',

                    'template' => [
                        'name' =>
                            $templateName,

                        'language' => [
                            'code' =>
                                (string) (
                                    $config['template_language']
                                    ?? 'en_US'
                                ),
                        ],

                        'components' =>
                            $components,
                    ],
                ]
            );

        return $this->parseMessageResponse(
            $response
        );
    }

    private function sendFreeForm(
        string $endpoint,
        string $accessToken,
        string $destination,
        string $message,
        array $payload,
        int $timeout
    ): array {
        $chart =
            $payload['trade_idea']['chart_image']
            ?? null;

        if (
            is_array(
                $chart
            )
            && $this->canSendAsImage(
                $chart
            )
        ) {
            $mediaId = $this->uploadImage(
                $endpoint,
                $accessToken,
                $chart,
                $timeout
            );

            $body = [
                'messaging_product' =>
                    'whatsapp',

                'recipient_type' =>
                    'individual',

                'to' =>
                    $destination,

                'type' =>
                    'image',

                'image' => [
                    'id' =>
                        $mediaId,

                    'caption' =>
                        $message,
                ],
            ];
        } else {
            $body = [
                'messaging_product' =>
                    'whatsapp',

                'recipient_type' =>
                    'individual',

                'to' =>
                    $destination,

                'type' =>
                    'text',

                'text' => [
                    'preview_url' =>
                        false,

                    'body' =>
                        $message,
                ],
            ];
        }

        $response = Http::timeout(
            $timeout
        )
            ->withToken(
                $accessToken
            )
            ->acceptJson()
            ->post(
                $endpoint
                .'/messages',
                $body
            );

        return $this->parseMessageResponse(
            $response
        );
    }

    private function uploadImage(
        string $endpoint,
        string $accessToken,
        array $chart,
        int $timeout
    ): string {
        $disk = trim(
            (string) (
                $chart['disk']
                ?? ''
            )
        );

        $path = trim(
            (string) (
                $chart['path']
                ?? ''
            )
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
                'WHATSAPP',
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
                'WHATSAPP',
                'MEDIA_READ_FAILED',
                'Trade Idea chart image could not be read.'
            );
        }

        try {
            $response = Http::timeout(
                $timeout
            )
                ->withToken(
                    $accessToken
                )
                ->attach(
                    'file',
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
                    $endpoint
                    .'/media',
                    [
                        'messaging_product' =>
                            'whatsapp',
                    ]
                );
        } finally {
            fclose(
                $stream
            );
        }

        $json =
            $response->json();

        if (
            ! $response->successful()
            || ! is_array(
                $json
            )
            || trim(
                (string) (
                    $json['id']
                    ?? ''
                )
            ) === ''
        ) {
            throw new TradeIdeaDistributionException(
                'WHATSAPP',
                $this->safeErrorCode(
                    $response,
                    $json
                ),
                'WhatsApp Cloud API rejected the chart image upload.'
            );
        }

        return (string) $json['id'];
    }

    private function parseMessageResponse(
        Response $response
    ): array {
        $json =
            $response->json();

        $messageId =
            is_array(
                $json
            )
                ? (
                    $json['messages'][0]['id']
                    ?? null
                )
                : null;

        if (
            ! $response->successful()
            || ! is_string(
                $messageId
            )
            || trim(
                $messageId
            ) === ''
        ) {
            throw new TradeIdeaDistributionException(
                'WHATSAPP',
                $this->safeErrorCode(
                    $response,
                    $json
                ),
                'WhatsApp Cloud API rejected the Trade Idea distribution.'
            );
        }

        return [
            'provider_message_id' =>
                $messageId,

            'response_meta' => [
                'message_id' =>
                    $messageId,

                'contact_wa_id' =>
                    is_array(
                        $json
                    )
                        ? (
                            $json['contacts'][0]['wa_id']
                            ?? null
                        )
                        : null,
            ],
        ];
    }

    private function safeErrorCode(
        Response $response,
        mixed $json
    ): string {
        if (
            is_array(
                $json
            )
            && isset(
                $json['error']['code']
            )
        ) {
            return (string) $json['error']['code'];
        }

        return (string) $response->status();
    }

    private function canSendAsImage(
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
                || $size <= 5242880
            );
    }
}
