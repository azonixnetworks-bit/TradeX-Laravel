<?php

namespace Tests\Feature\Api\Auth;

use App\Exceptions\AuthApiException;
use App\Services\Auth\Registration\RegistrationOtpDeliveryService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RegistrationWhatsAppMetaDeliveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tradex_registration.delivery.whatsapp_driver' =>
                'meta',

            'tradex_registration.delivery.meta.graph_version' =>
                'v26.0',

            'tradex_registration.delivery.meta.phone_number_id' =>
                '123456789012345',

            'tradex_registration.delivery.meta.access_token' =>
                'meta-test-access-token',

            'tradex_registration.delivery.meta.otp_template_name' =>
                'tradex_registration_otp',

            'tradex_registration.delivery.meta.otp_template_language' =>
                'en_US',

            'tradex_registration.delivery.meta.timeout_seconds' =>
                10,

            'tradex_registration.delivery.meta.connect_timeout_seconds' =>
                5,
        ]);
    }

    public function test_meta_delivery_sends_authentication_template_with_matching_otp_button(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' =>
                Http::response(
                    [
                        'messaging_product' =>
                            'whatsapp',

                        'messages' => [
                            [
                                'id' =>
                                    'wamid.test-message-id',
                            ],
                        ],
                    ],
                    200
                ),
        ]);

        app(
            RegistrationOtpDeliveryService::class
        )->sendWhatsApp(
            '+94770000000',
            '123456'
        );

        Http::assertSent(
            function (
                HttpRequest $request
            ): bool {
                $payload = json_decode(
                    $request->body(),
                    true
                );

                return $request->method() === 'POST'
                    && $request->url()
                        === 'https://graph.facebook.com/v26.0/123456789012345/messages'
                    && $request->hasHeader(
                        'Authorization',
                        'Bearer meta-test-access-token'
                    )
                    && is_array($payload)
                    && data_get(
                        $payload,
                        'messaging_product'
                    ) === 'whatsapp'
                    && data_get(
                        $payload,
                        'to'
                    ) === '94770000000'
                    && data_get(
                        $payload,
                        'type'
                    ) === 'template'
                    && data_get(
                        $payload,
                        'template.name'
                    ) === 'tradex_registration_otp'
                    && data_get(
                        $payload,
                        'template.language.code'
                    ) === 'en_US'
                    && data_get(
                        $payload,
                        'template.components.0.type'
                    ) === 'body'
                    && data_get(
                        $payload,
                        'template.components.0.parameters.0.type'
                    ) === 'text'
                    && data_get(
                        $payload,
                        'template.components.0.parameters.0.text'
                    ) === '123456'
                    && data_get(
                        $payload,
                        'template.components.1.type'
                    ) === 'button'
                    && data_get(
                        $payload,
                        'template.components.1.sub_type'
                    ) === 'url'
                    && (string) data_get(
                        $payload,
                        'template.components.1.index'
                    ) === '0'
                    && data_get(
                        $payload,
                        'template.components.1.parameters.0.type'
                    ) === 'text'
                    && data_get(
                        $payload,
                        'template.components.1.parameters.0.text'
                    ) === '123456';
            }
        );
    }

    public function test_missing_meta_credentials_fail_before_any_http_request(): void
    {
        config([
            'tradex_registration.delivery.meta.access_token' =>
                '',
        ]);

        Http::fake();

        try {
            app(
                RegistrationOtpDeliveryService::class
            )->sendWhatsApp(
                '+94770000000',
                '123456'
            );

            $this->fail(
                'Expected WhatsApp configuration failure.'
            );
        } catch (AuthApiException $exception) {
            $this->assertSame(
                503,
                $exception->status
            );

            $this->assertSame(
                'WHATSAPP_OTP_NOT_CONFIGURED',
                $exception->apiCode
            );
        }

        Http::assertNothingSent();
    }

    public function test_invalid_graph_version_fails_before_any_http_request(): void
    {
        config([
            'tradex_registration.delivery.meta.graph_version' =>
                'latest',
        ]);

        Http::fake();

        try {
            app(
                RegistrationOtpDeliveryService::class
            )->sendWhatsApp(
                '+94770000000',
                '123456'
            );

            $this->fail(
                'Expected WhatsApp configuration failure.'
            );
        } catch (AuthApiException $exception) {
            $this->assertSame(
                503,
                $exception->status
            );

            $this->assertSame(
                'WHATSAPP_OTP_NOT_CONFIGURED',
                $exception->apiCode
            );
        }

        Http::assertNothingSent();
    }

    public function test_meta_http_failure_is_mapped_to_safe_delivery_error(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' =>
                Http::response(
                    [
                        'error' => [
                            'message' =>
                                'Provider detail must not escape.',
                        ],
                    ],
                    400
                ),
        ]);

        try {
            app(
                RegistrationOtpDeliveryService::class
            )->sendWhatsApp(
                '+94770000000',
                '123456'
            );

            $this->fail(
                'Expected WhatsApp delivery failure.'
            );
        } catch (AuthApiException $exception) {
            $this->assertSame(
                503,
                $exception->status
            );

            $this->assertSame(
                'WHATSAPP_OTP_DELIVERY_FAILED',
                $exception->apiCode
            );

            $this->assertSame(
                'WhatsApp verification code could not be delivered.',
                $exception->getMessage()
            );

            $this->assertStringNotContainsString(
                'Provider detail',
                $exception->getMessage()
            );
        }
    }

    public function test_meta_success_response_requires_a_message_id(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' =>
                Http::response(
                    [
                        'messaging_product' =>
                            'whatsapp',

                        'messages' =>
                            [],
                    ],
                    200
                ),
        ]);

        try {
            app(
                RegistrationOtpDeliveryService::class
            )->sendWhatsApp(
                '+94770000000',
                '123456'
            );

            $this->fail(
                'Expected invalid Meta response failure.'
            );
        } catch (AuthApiException $exception) {
            $this->assertSame(
                503,
                $exception->status
            );

            $this->assertSame(
                'WHATSAPP_OTP_DELIVERY_FAILED',
                $exception->apiCode
            );
        }
    }

    public function test_invalid_timeout_configuration_fails_closed(): void
    {
        config([
            'tradex_registration.delivery.meta.connect_timeout_seconds' =>
                12,

            'tradex_registration.delivery.meta.timeout_seconds' =>
                10,
        ]);

        Http::fake();

        try {
            app(
                RegistrationOtpDeliveryService::class
            )->sendWhatsApp(
                '+94770000000',
                '123456'
            );

            $this->fail(
                'Expected WhatsApp configuration failure.'
            );
        } catch (AuthApiException $exception) {
            $this->assertSame(
                'WHATSAPP_OTP_NOT_CONFIGURED',
                $exception->apiCode
            );
        }

        Http::assertNothingSent();
    }
}
