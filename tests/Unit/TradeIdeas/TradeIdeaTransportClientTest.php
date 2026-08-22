<?php

namespace Tests\Unit\TradeIdeas;

use App\Models\TradeIdeaDistribution;
use App\Services\TradeIdeas\TelegramTradeIdeaClient;
use App\Services\TradeIdeas\WhatsAppTradeIdeaClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TradeIdeaTransportClientTest extends TestCase
{
    public function test_telegram_text_distribution_uses_bot_api_without_exposing_secret_in_result(): void
    {
        config([
            'tradex_trade_ideas.telegram.enabled' =>
                true,

            'tradex_trade_ideas.telegram.api_base_url' =>
                'https://telegram.test',

            'tradex_trade_ideas.telegram.bot_token' =>
                'secret-bot-token',

            'tradex_trade_ideas.telegram.protect_content' =>
                true,

            'tradex_trade_ideas.telegram.timeout_seconds' =>
                10,
        ]);

        Http::fake([
            'https://telegram.test/*' =>
                Http::response(
                    [
                        'ok' =>
                            true,

                        'result' => [
                            'message_id' =>
                                123,

                            'chat' => [
                                'id' =>
                                    -100100,
                            ],
                        ],
                    ],
                    200
                ),
        ]);

        $distribution =
            new TradeIdeaDistribution();

        $distribution->destination =
            '@tradex_test';

        $distribution->payload_snapshot = [
            'message' =>
                'TRADEX TEST',

            'trade_idea' => [
                'chart_image' =>
                    null,
            ],
        ];

        $result = app(
            TelegramTradeIdeaClient::class
        )->send(
            $distribution
        );

        $this->assertSame(
            '123',
            $result['provider_message_id']
        );

        $this->assertArrayNotHasKey(
            'token',
            $result['response_meta']
        );

        Http::assertSent(
            function ($request): bool {
                return str_ends_with(
                    $request->url(),
                    '/botsecret-bot-token/sendMessage'
                )
                    && $request['chat_id']
                        === '@tradex_test'
                    && $request['text']
                        === 'TRADEX TEST'
                    && $request['protect_content']
                        === 'true';
            }
        );
    }

    public function test_whatsapp_template_distribution_uses_configured_graph_version_and_approved_template(): void
    {
        config([
            'tradex_trade_ideas.whatsapp.enabled' =>
                true,

            'tradex_trade_ideas.whatsapp.api_base_url' =>
                'https://graph.test',

            'tradex_trade_ideas.whatsapp.graph_version' =>
                'v99.0',

            'tradex_trade_ideas.whatsapp.phone_number_id' =>
                '123456',

            'tradex_trade_ideas.whatsapp.access_token' =>
                'secret-wa-token',

            'tradex_trade_ideas.whatsapp.mode' =>
                'template',

            'tradex_trade_ideas.whatsapp.template_text_name' =>
                'trade_idea_alert',

            'tradex_trade_ideas.whatsapp.template_image_name' =>
                null,

            'tradex_trade_ideas.whatsapp.template_language' =>
                'en_US',

            'tradex_trade_ideas.whatsapp.timeout_seconds' =>
                10,
        ]);

        Http::fake([
            'https://graph.test/*' =>
                Http::response(
                    [
                        'contacts' => [
                            [
                                'wa_id' =>
                                    '94770000000',
                            ],
                        ],

                        'messages' => [
                            [
                                'id' =>
                                    'wamid.TEST123',
                            ],
                        ],
                    ],
                    200
                ),
        ]);

        $distribution =
            new TradeIdeaDistribution();

        $distribution->destination =
            '94770000000';

        $distribution->payload_snapshot = [
            'message' =>
                'TRADEX TEST',

            'trade_idea' => [
                'chart_image' =>
                    null,
            ],
        ];

        $result = app(
            WhatsAppTradeIdeaClient::class
        )->send(
            $distribution
        );

        $this->assertSame(
            'wamid.TEST123',
            $result['provider_message_id']
        );

        Http::assertSent(
            function ($request): bool {
                return $request->url()
                    === 'https://graph.test/v99.0/123456/messages'
                    && $request['to']
                        === '94770000000'
                    && $request['type']
                        === 'template'
                    && $request['template']['name']
                        === 'trade_idea_alert'
                    && $request['template']['language']['code']
                        === 'en_US'
                    && $request['template']['components'][0]['parameters'][0]['text']
                        === 'TRADEX TEST';
            }
        );
    }
}
