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
            ->assertDontSee($secret, false);

        $cacheControl = (string) $response->headers->get(
            'Cache-Control'
        );

        $this->assertStringContainsString(
            'no-store',
            $cacheControl
        );
    }
}
