<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppSecurityAlertCheckCommandTest extends TestCase
{
    public function test_valid_configuration_is_checked_offline_without_disclosing_values(): void
    {
        Http::preventStrayRequests();
        config([
            'tradex_registration.delivery.meta.graph_version' => 'v26.0',
            'tradex_registration.delivery.meta.phone_number_id' => '123456789',
            'tradex_registration.delivery.meta.access_token' => 'private-test-token',
            'tradex_admin_devices.whatsapp_recipients' => ['+94771234567'],
            'tradex_admin_devices.whatsapp_template' => 'tradex_security_alert',
            'tradex_admin_devices.whatsapp_language' => 'en_US',
        ]);
        $this->artisan('tradex:whatsapp-security-alert-check')
            ->doesntExpectOutputToContain('private-test-token')
            ->doesntExpectOutputToContain('+94771234567')
            ->expectsOutputToContain('No message was sent')
            ->assertSuccessful();
    }

    public function test_missing_recipient_and_template_fail_readiness(): void
    {
        Http::preventStrayRequests();
        config(['tradex_admin_devices.whatsapp_recipients' => [], 'tradex_admin_devices.whatsapp_template' => '']);
        $this->artisan('tradex:whatsapp-security-alert-check')->expectsOutputToContain('not configured')->assertFailed();
    }
}
