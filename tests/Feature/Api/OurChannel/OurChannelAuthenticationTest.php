<?php

namespace Tests\Feature\Api\OurChannel;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOurChannelApiContent;
use Tests\TestCase;

class OurChannelAuthenticationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOurChannelApiContent;

    public function test_catalog_requires_authentication(): void
    {
        $this->createOurChannelVideo();

        $this
            ->getJson(
                '/api/v1/our-channel/videos'
            )
            ->assertUnauthorized();
    }
}
