<?php

namespace Tests\Feature\Api\TradeIdeas;

use App\Models\TradeIdea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTradeIdeaApiContent;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class TradeIdeaChartSecurityTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTradeXMobileSession;
    use CreatesTradeIdeaApiContent;

    public function test_premium_user_can_stream_valid_protected_chart(): void
    {
        $tradeIdea =
            $this->createTradeIdeaApiContent(
                withChart: true
            );

        $user = $this->createApiUser([
            'account_type' =>
                'PREMIUM',
        ]);

        $token = $this->issueBoundToken(
            $user
        );

        $this
            ->withToken(
                $token
            )
            ->get(
                '/api/v1/trade-ideas/'
                .$tradeIdea->uuid
                .'/chart'
            )
            ->assertOk()
            ->assertHeader(
                'Content-Type',
                'image/png'
            )
            ->assertHeader(
                'X-Content-Type-Options',
                'nosniff'
            )
            ->assertHeader(
                'X-TradeX-Content-Protection',
                'premium-private'
            );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_type' =>
                    'USER',

                'actor_id' =>
                    $user->getKey(),

                'action' =>
                    'TRADE_IDEA_CHART_ACCESSED',

                'target_id' =>
                    $tradeIdea->getKey(),
            ]
        );
    }

    public function test_chart_path_outside_owned_uuid_namespace_is_rejected(): void
    {
        Storage::fake(
            'private'
        );

        Storage::disk(
            'private'
        )->put(
            'other/private.png',
            'secret'
        );

        $tradeIdea =
            $this->createTradeIdeaApiContent();

        $tradeIdea->forceFill([
            'chart_image_disk' =>
                'private',

            'chart_image_path' =>
                'other/private.png',

            'chart_image_mime_type' =>
                'image/png',

            'chart_image_size_bytes' =>
                6,
        ])->save();

        $user = $this->createApiUser([
            'account_type' =>
                'PREMIUM',
        ]);

        $token = $this->issueBoundToken(
            $user
        );

        $this
            ->withToken(
                $token
            )
            ->getJson(
                '/api/v1/trade-ideas/'
                .$tradeIdea->uuid
                .'/chart'
            )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'TRADE_IDEA_CHART_STORAGE_INVALID'
            );
    }

    public function test_missing_chart_file_returns_controlled_not_found(): void
    {
        Storage::fake(
            'private'
        );

        $tradeIdea =
            $this->createTradeIdeaApiContent();

        $tradeIdea->forceFill([
            'chart_image_disk' =>
                'private',

            'chart_image_path' =>
                'trade-ideas/'
                .$tradeIdea->uuid
                .'/charts/missing.png',

            'chart_image_mime_type' =>
                'image/png',

            'chart_image_size_bytes' =>
                1,
        ])->save();

        $user = $this->createApiUser([
            'account_type' =>
                'PREMIUM',
        ]);

        $token = $this->issueBoundToken(
            $user
        );

        $this
            ->withToken(
                $token
            )
            ->getJson(
                '/api/v1/trade-ideas/'
                .$tradeIdea->uuid
                .'/chart'
            )
            ->assertNotFound()
            ->assertJsonPath(
                'error.code',
                'TRADE_IDEA_CHART_FILE_MISSING'
            );
    }
}
