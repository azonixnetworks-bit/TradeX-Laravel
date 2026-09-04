<?php

namespace Tests\Feature\Web;

use Tests\TestCase;

class CustomerActivationPageTest extends TestCase
{
    public function test_activation_page_is_public_no_store_and_does_not_render_query_token(): void
    {
        $secret = str_repeat('a', 64);

        $response = $this->get(
            '/onboarding/activate?invitation_id=11111111-1111-4111-8111-111111111111&token='.$secret
        );

        $response
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader(
                'X-Robots-Tag',
                'noindex, nofollow, noarchive'
            )
            ->assertSee(
                'data-tradex-customer-activation-ui="v1"',
                false
            )
            ->assertSee(
                'data-email-otp-prefix="TRX-"',
                false
            )
            ->assertSee(
                'Enter the eight-digit verification code sent to the email address on your TradeX invitation.',
                false
            )
            ->assertDontSee(
                'Enter the six-digit verification code sent to the email address on your TradeX invitation.',
                false
            )
            ->assertDontSee($secret, false);

        $content = (string) $response->getContent();
$this->assertSame(
    8,
    substr_count(
        $content,
        'class="email-otp-digit" data-email-otp-digit'
    )
);

        $this->assertStringContainsString(
            "replace(/\\D/g, '')",
            $content
        );

        $this->assertStringContainsString(
            "! /^\\d{8}$/.test(code)",
            $content
        );

        $cacheControl = (string) $response->headers->get(
            'Cache-Control'
        );

        $this->assertStringContainsString(
            'no-store',
            $cacheControl
        );
    }
}
