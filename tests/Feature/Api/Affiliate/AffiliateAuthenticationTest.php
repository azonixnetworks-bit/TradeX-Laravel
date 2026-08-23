<?php

namespace Tests\Feature\Api\Affiliate;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_affiliate_api_requires_authentication(): void
    {
        $this
            ->getJson(
                '/api/v1/affiliate/overview'
            )
            ->assertUnauthorized();

        $this
            ->postJson(
                '/api/v1/affiliate/share-action',
                [
                    'channel' =>
                        'WHATSAPP',
                ]
            )
            ->assertUnauthorized();
    }
}
