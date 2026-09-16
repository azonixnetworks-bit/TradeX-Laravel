<?php

namespace Tests\Unit\Trading;

use App\Services\Trading\MetaTradingRoutineWhatsAppGateway;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaTradingRoutineWhatsAppGatewayTest extends TestCase
{
    public function test_gateway_uploads_pdf_then_submits_approved_document_template(): void
    {
        config()->set('trading_routine.whatsapp.driver', 'meta');
        config()->set('trading_routine.whatsapp.template_name', 'tradex_daily_trading_plan');
        config()->set('trading_routine.whatsapp.template_language', 'en_US');
        config()->set('tradex_registration.delivery.meta.graph_version', 'v99.0');
        config()->set('tradex_registration.delivery.meta.phone_number_id', '123456');
        config()->set('tradex_registration.delivery.meta.access_token', 'test-token');
        config()->set('tradex_registration.delivery.meta.timeout_seconds', 10);
        config()->set('tradex_registration.delivery.meta.connect_timeout_seconds', 5);

        Http::fake([
            'https://graph.facebook.com/v99.0/123456/media' => Http::response(['id' => 'media-123'], 200),
            'https://graph.facebook.com/v99.0/123456/messages' => Http::response([
                'messages' => [['id' => 'wamid.123']],
            ], 200),
        ]);

        $result = app(MetaTradingRoutineWhatsAppGateway::class)->sendPdf(
            '+94771234567',
            '%PDF-1.4 test',
            'tradex-plan.pdf'
        );

        $this->assertSame('META_WHATSAPP_CLOUD_API', $result['provider']);
        $this->assertSame('wamid.123', $result['message_id']);
        $this->assertSame('media-123', $result['media_id']);

        Http::assertSentCount(2);
        Http::assertSent(function ($request): bool {
            if ($request->url() !== 'https://graph.facebook.com/v99.0/123456/messages') {
                return false;
            }

            return $request['messaging_product'] === 'whatsapp'
                && $request['to'] === '94771234567'
                && $request['type'] === 'template'
                && data_get($request->data(), 'template.name') === 'tradex_daily_trading_plan'
                && data_get($request->data(), 'template.components.0.parameters.0.document.id') === 'media-123';
        });
    }
}
