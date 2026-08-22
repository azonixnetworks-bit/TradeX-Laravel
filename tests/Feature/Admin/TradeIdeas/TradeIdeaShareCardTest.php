<?php

namespace Tests\Feature\Admin\TradeIdeas;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\TradeIdea;
use App\Models\TradeIdeaTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class TradeIdeaShareCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_admin_can_preview_responsive_share_card(): void
    {
        $admin = $this->createAdminWithViewPermission();
        $tradeIdea = $this->createTradeIdea();

        $response = $this
            ->actingAs(
                $admin,
                'admin'
            )
            ->get(
                route(
                    'admin.trade_ideas.share_card.preview',
                    [
                        'tradeIdea' => $tradeIdea,
                        'variant' => 'portrait',
                        'referral_code' => 'TX-ABC123',
                        'affiliate_url' => 'https://example.test/join?ref=TX-ABC123',
                    ]
                )
            );

        $response
            ->assertOk()
            ->assertViewIs(
                'admin.trade_ideas.share_card'
            )
            ->assertViewHas(
                'variant',
                'portrait'
            )
            ->assertViewHas(
                'referralCode',
                'TX-ABC123'
            );
    }

    public function test_svg_contains_uploaded_chart_without_distortion_and_personalized_referral(): void
    {
        Storage::fake(
            'private'
        );

        $admin = $this->createAdminWithViewPermission();

        $tradeIdea = $this->createTradeIdea(
            withChart: true
        );

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Zq1sAAAAASUVORK5CYII='
        );

        Storage::disk(
            'private'
        )->put(
            $tradeIdea->chart_image_path,
            $png
        );

        $response = $this
            ->actingAs(
                $admin,
                'admin'
            )
            ->get(
                route(
                    'admin.trade_ideas.share_card.svg',
                    [
                        'tradeIdea' => $tradeIdea,
                        'variant' => 'story',
                        'referral_code' => 'TX-ABC123',
                        'affiliate_url' => 'https://example.test/join?ref=TX-ABC123',
                    ]
                )
            );

        $response->assertOk();

        $this->assertStringContainsString(
            'image/svg+xml',
            (string) $response->headers->get(
                'Content-Type'
            )
        );

        $svg = $response->getContent();

        $this->assertStringContainsString(
            'viewBox="0 0 1080 1920"',
            $svg
        );

        $this->assertStringContainsString(
            'preserveAspectRatio="xMidYMid meet"',
            $svg
        );

        $this->assertStringContainsString(
            'data:image/png;base64,',
            $svg
        );

        $this->assertStringContainsString(
            'TX-ABC123',
            $svg
        );

        $this->assertStringContainsString(
            'example.test/join',
            $svg
        );
    }

    public function test_share_card_requires_trade_ideas_view_permission(): void
    {
        $admin = $this->createAdminWithoutPermission();
        $tradeIdea = $this->createTradeIdea();

        $response = $this
            ->actingAs(
                $admin,
                'admin'
            )
            ->get(
                route(
                    'admin.trade_ideas.share_card.preview',
                    [
                        'tradeIdea' => $tradeIdea,
                        'variant' => 'portrait',
                    ]
                )
            );

        $response->assertForbidden();
    }

    public function test_invalid_variant_and_non_https_affiliate_url_are_rejected(): void
    {
        $admin = $this->createAdminWithViewPermission();
        $tradeIdea = $this->createTradeIdea();

        $response = $this
            ->actingAs(
                $admin,
                'admin'
            )
            ->get(
                route(
                    'admin.trade_ideas.share_card.preview',
                    [
                        'tradeIdea' => $tradeIdea,
                        'variant' => 'unknown',
                        'affiliate_url' => 'http://example.test/join',
                    ]
                )
            );

        $response->assertSessionHasErrors([
            'variant',
            'affiliate_url',
        ]);
    }

    private function createAdminWithViewPermission(): AdminUser
    {
        $admin = $this->createAdminWithoutPermission();

        $role = AdminRole::query()
            ->create([
                'code' => 'TRADE_SHARE_'
                    .strtoupper(
                        Str::random(8)
                    ),
                'name' => 'Trade Share Test Role',
                'description' => 'Trade Idea Share Card test role.',
                'is_system' => false,
                'is_active' => true,
            ]);

        $permission = AdminPermission::query()
            ->create([
                'code' => 'trade_ideas.view',
                'name' => 'trade_ideas.view',
                'module' => 'TRADE_IDEAS',
                'description' => 'Trade Idea Share Card view permission.',
            ]);

        $role
            ->permissions()
            ->attach(
                $permission->id
            );

        $admin
            ->roles()
            ->attach(
                $role->id
            );

        return $admin->fresh();
    }

    private function createAdminWithoutPermission(): AdminUser
    {
        return AdminUser::query()
            ->create([
                'uuid' => Str::uuid()->toString(),
                'name' => 'Trade Share Admin',
                'email' => 'trade-share-'
                    .Str::lower(
                        Str::random(10)
                    )
                    .'@tradex.test',
                'email_verified_at' => now(),
                'password' => 'TestingPassword123!',
                'is_active' => true,
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ]);
    }

    private function createTradeIdea(
        bool $withChart = false
    ): TradeIdea {
        $tradeIdea = TradeIdea::query()
            ->create([
                'uuid' => Str::uuid()->toString(),
                'title' => 'Gold liquidity sweep and continuation setup',
                'summary' => 'Price reacted from the key liquidity zone with continuation confirmation.',
                'analysis' => 'Share Card test analysis.',
                'tradingview_url' => null,
                'chart_image_disk' => $withChart
                    ? 'private'
                    : null,
                'chart_image_path' => $withChart
                    ? 'trade-ideas/test/chart.png'
                    : null,
                'chart_image_mime_type' => $withChart
                    ? 'image/png'
                    : null,
                'chart_image_size_bytes' => $withChart
                    ? 100
                    : null,
                'category' => TradeIdea::CATEGORY_FOREX,
                'market_region' => null,
                'symbol' => 'XAUUSD',
                'instrument_name' => 'Gold',
                'direction' => TradeIdea::DIRECTION_BUY,
                'timeframe' => 'H1',
                'entry_price_low' => '3312.0000000000',
                'entry_price_high' => '3318.0000000000',
                'stop_loss' => '3298.0000000000',
                'status' => TradeIdea::STATUS_PUBLISHED,
                'published_at' => now(),
            ]);

        foreach (
            [
                '3345.0000000000',
                '3375.0000000000',
                '3410.0000000000',
            ]
            as $index => $price
        ) {
            TradeIdeaTarget::query()
                ->create([
                    'trade_idea_id' => $tradeIdea->id,
                    'label' => 'TP'.($index + 1),
                    'price' => $price,
                    'sort_order' => $index + 1,
                ]);
        }

        return $tradeIdea;
    }
}
