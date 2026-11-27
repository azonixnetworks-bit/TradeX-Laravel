<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class PublicMarketClockApiTest extends TestCase
{
    public function test_public_clock_exposes_only_the_four_website_sessions(): void
    {
        $this->getJson('/api/public/market-clock')
            ->assertOk()
            ->assertJsonCount(4, 'data.sessions')
            ->assertJsonPath('data.sessions.0.key', 'sydney')
            ->assertJsonPath('data.sessions.1.key', 'tokyo')
            ->assertJsonPath('data.sessions.2.key', 'london')
            ->assertJsonPath('data.sessions.3.key', 'new_york')
            ->assertJsonMissingPath('data.sessions.4');
    }
}
