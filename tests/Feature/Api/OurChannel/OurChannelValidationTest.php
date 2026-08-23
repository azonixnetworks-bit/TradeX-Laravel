<?php

namespace Tests\Feature\Api\OurChannel;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class OurChannelValidationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTradeXMobileSession;

    public function test_invalid_catalog_filters_are_rejected(): void
    {
        $user = $this->createApiUser();

        $this
            ->withToken(
                $this->issueBoundToken($user)
            )
            ->getJson(
                '/api/v1/our-channel/videos?language_code=invalid_language_code&featured=maybe&per_page=999'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'language_code',
                'featured',
                'per_page',
            ]);
    }
}
