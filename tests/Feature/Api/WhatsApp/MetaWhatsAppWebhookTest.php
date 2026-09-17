<?php

namespace Tests\Feature\Api\WhatsApp;

use Tests\TestCase;

class MetaWhatsAppWebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tradex_registration.delivery.meta.webhook_verify_token' =>
                'tradex-test-webhook-token',
            'tradex_registration.delivery.meta.app_secret' =>
                'tradex-test-app-secret',
        ]);
    }

    public function test_meta_can_verify_whatsapp_webhook(): void
    {
        $this
            ->get(
                '/api/webhooks/meta/whatsapp'
                .'?hub_mode=subscribe'
                .'&hub_verify_token=tradex-test-webhook-token'
                .'&hub_challenge=987654321'
            )
            ->assertOk()
            ->assertSeeText('987654321');
    }

    public function test_webhook_verification_rejects_wrong_token(): void
    {
        $this
            ->get(
                '/api/webhooks/meta/whatsapp'
                .'?hub_mode=subscribe'
                .'&hub_verify_token=wrong-token'
                .'&hub_challenge=987654321'
            )
            ->assertForbidden();
    }

    public function test_webhook_accepts_valid_meta_signature(): void
    {
        $body = json_encode(
            [
                'object' =>
                    'whatsapp_business_account',
                'entry' => [
                    [
                        'id' => 'waba-test',
                        'changes' => [
                            [
                                'field' => 'messages',
                                'value' => [
                                    'metadata' => [
                                        'display_phone_number' =>
                                            '94770000000',
                                        'phone_number_id' =>
                                            '1234567890',
                                    ],
                                    'statuses' => [
                                        [
                                            'id' =>
                                                'wamid.test',
                                            'status' =>
                                                'delivered',
                                            'recipient_id' =>
                                                '94771111111',
                                            'timestamp' =>
                                                '1789650000',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            JSON_THROW_ON_ERROR
        );

        $signature =
            'sha256='.
            hash_hmac(
                'sha256',
                $body,
                'tradex-test-app-secret'
            );

        $this
            ->call(
                'POST',
                '/api/webhooks/meta/whatsapp',
                [],
                [],
                [],
                [
                    'CONTENT_TYPE' =>
                        'application/json',
                    'HTTP_X_HUB_SIGNATURE_256' =>
                        $signature,
                ],
                $body
            )
            ->assertOk()
            ->assertSeeText('EVENT_RECEIVED');
    }

    public function test_webhook_rejects_invalid_meta_signature(): void
    {
        $body = json_encode(
            [
                'object' =>
                    'whatsapp_business_account',
                'entry' => [],
            ],
            JSON_THROW_ON_ERROR
        );

        $this
            ->call(
                'POST',
                '/api/webhooks/meta/whatsapp',
                [],
                [],
                [],
                [
                    'CONTENT_TYPE' =>
                        'application/json',
                    'HTTP_X_HUB_SIGNATURE_256' =>
                        'sha256=invalid',
                ],
                $body
            )
            ->assertForbidden();
    }
}
