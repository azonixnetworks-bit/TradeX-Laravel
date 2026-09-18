<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppOtpCheckCommandTest extends TestCase
{
    public function test_configuration_check_is_offline_and_does_not_print_credentials(): void
    {
        Http::preventStrayRequests();
        config([
            'tradex_registration.delivery.whatsapp_driver' => 'meta',
            'tradex_registration.delivery.meta.graph_version' => 'v26.0',
            'tradex_registration.delivery.meta.phone_number_id' => '123456789012345',
            'tradex_registration.delivery.meta.access_token' => 'secret-test-token',
            'tradex_registration.delivery.meta.otp_template_name' => 'test_authentication',
            'tradex_registration.delivery.meta.otp_template_language' => 'en_US',
        ]);

        $this->artisan('tradex:whatsapp-otp-check')
            ->doesntExpectOutputToContain('secret-test-token')
            ->doesntExpectOutputToContain('123456789012345')
            ->expectsOutputToContain('No message was sent')
            ->assertSuccessful();
    }

    public function test_missing_credentials_or_log_driver_fail_readiness(): void
    {
        Http::preventStrayRequests();
        config([
            'tradex_registration.delivery.whatsapp_driver' => 'log',
            'tradex_registration.delivery.meta.access_token' => '',
        ]);

        $this->artisan('tradex:whatsapp-otp-check')
            ->expectsOutputToContain('WhatsApp OTP is not ready')
            ->assertFailed();
    }
}
