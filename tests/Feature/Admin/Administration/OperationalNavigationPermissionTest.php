<?php

namespace Tests\Feature\Admin\Administration;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Services\Screener\ScreenerEngine;
use App\Services\Screener\BinanceSpotCatalogService;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminRolePermissionSeeder;
use Database\Seeders\AdminRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class OperationalNavigationPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdminRoleSeeder::class);
        $this->seed(AdminPermissionSeeder::class);
        $this->seed(AdminRolePermissionSeeder::class);
    }

    public function test_market_clock_permission_loads_only_market_clock_navigation_asset_without_dashboard_permission(): void
    {
        $admin = $this->adminWithPermissions(['market_clock.view']);
        $this->actingAs($admin, 'admin');

        $layout = $this->layout();

        $this->assertStringContainsString('admin/js/market-clock-nav.js', $layout);
        $this->assertStringNotContainsString('admin/js/screener-monitor-nav.js', $layout);
        $this->assertStringNotContainsString('>Dashboard</span>', $layout);

        $this->get(route('admin.market_clock.index'))->assertOk();
        $this->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_screener_permission_loads_only_screener_navigation_asset_without_dashboard_permission(): void
    {
        $admin = $this->adminWithPermissions(['screener.monitor.view']);
        $this->actingAs($admin, 'admin');

        $layout = $this->layout();

        $this->assertStringContainsString('admin/js/screener-monitor-nav.js', $layout);
        $this->assertStringNotContainsString('admin/js/market-clock-nav.js', $layout);
        $this->assertStringNotContainsString('>Dashboard</span>', $layout);

        $this->get(route('admin.screener.index'))->assertOk();
        $this->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_operational_navigation_javascript_has_a_dashboard_independent_overview_fallback(): void
    {
        $market = file_get_contents(public_path('admin/js/market-clock-nav.js'));
        $screener = file_get_contents(public_path('admin/js/screener-monitor-nav.js'));

        $this->assertIsString($market);
        $this->assertIsString($screener);

        $this->assertStringContainsString("title.textContent.trim() === 'OVERVIEW'", $market);
        $this->assertStringContainsString('nav.prepend(overviewTitle)', $market);
        $this->assertStringContainsString("title.textContent.trim() === 'OVERVIEW'", $screener);
        $this->assertStringContainsString('marketClockLink || dashboardLink || overviewTitle', $screener);
    }

    public function test_screener_operator_can_view_market_results_without_customer_api_authentication(): void
    {
        $admin = $this->adminWithPermissions(['screener.monitor.view']);
        $this->actingAs($admin, 'admin');

        $engine = $this->mock(ScreenerEngine::class);
        $catalog = $this->mock(BinanceSpotCatalogService::class);
        $engine->shouldReceive('markets')->once()->andReturn([
            'CRYPTO' => [
                'label' => 'Crypto',
                'enabled' => true,
                'status' => 'ACTIVE',
            ],
        ]);
        $engine->shouldReceive('timeframes')->once()->andReturn([
            'H1' => ['label' => '1 Hour'],
        ]);
        $symbols = [[
            'symbol' => 'BTCUSDT',
            'provider_symbol' => 'BTCUSDT',
            'name' => 'Bitcoin / Tether',
            'base_currency' => 'BTC',
            'quote_currency' => 'USDT',
            'enabled' => true,
            'logo_url' => 'https://cdn.jsdelivr.net/btc.png',
        ]];
        $catalog->shouldReceive('catalog')->once()->andReturn([
            'symbols' => $symbols,
            'source' => 'BINANCE_LIVE',
            'fetched_at_utc' => '2026-10-05T08:00:00+00:00',
        ]);
        $engine->shouldReceive('screenWithSymbols')->once()->with([
            'market' => 'CRYPTO',
            'timeframe' => 'H1',
            'sort' => 'symbol',
            'order' => 'asc',
        ], $symbols)->andReturn([
            'market' => 'CRYPTO',
            'timeframe' => 'H1',
            'provider' => 'BINANCE',
            'generated_at_utc' => '2026-10-05T08:00:00+00:00',
            'count' => 1,
            'cache' => ['hit' => false, 'stale' => false],
            'results' => [[
                'symbol' => 'BTCUSDT',
                'name' => 'Bitcoin / Tether',
                'base_currency' => 'BTC',
                'logo_url' => 'https://cdn.jsdelivr.net/btc.png',
                'price' => 65000,
                'change_percent' => 1.25,
                'trend' => 'BULLISH',
                'rsi_14' => 61.5,
                'ema_alignment' => 'BULLISH',
                'atr_percent' => 2.1,
                'candle_time_utc' => '2026-10-05T07:00:00+00:00',
            ]],
        ]);

        $this->get(route('admin.screener.markets', [
            'market' => 'CRYPTO',
            'timeframe' => 'H1',
        ]))
            ->assertOk()
            ->assertSee('Market Results')
            ->assertSee('Search Binance symbols')
            ->assertSee('BTCUSDT')
            ->assertSee('BINANCE Screener Output');
    }

    public function test_binance_market_results_are_searchable_and_paginated(): void
    {
        $admin = $this->adminWithPermissions(['screener.monitor.view']);
        $this->actingAs($admin, 'admin');

        $symbols = collect(range(1, 21))->map(static fn (int $index): array => [
            'symbol' => sprintf('COIN%02dUSDT', $index),
            'provider_symbol' => sprintf('COIN%02dUSDT', $index),
            'name' => sprintf('COIN%02d / USDT', $index),
            'base_currency' => sprintf('COIN%02d', $index),
            'quote_currency' => 'USDT',
            'enabled' => true,
            'logo_url' => sprintf('https://cdn.jsdelivr.net/coin%02d.png', $index),
        ])->all();

        $engine = $this->mock(ScreenerEngine::class);
        $catalog = $this->mock(BinanceSpotCatalogService::class);
        $engine->shouldReceive('markets')->once()->andReturn([
            'CRYPTO' => ['label' => 'Crypto', 'enabled' => true],
        ]);
        $engine->shouldReceive('timeframes')->once()->andReturn([
            'H1' => ['label' => '1 Hour'],
        ]);
        $catalog->shouldReceive('catalog')->once()->andReturn([
            'symbols' => $symbols,
            'source' => 'BINANCE_CACHE',
            'fetched_at_utc' => '2026-10-05T08:00:00+00:00',
        ]);
        $engine->shouldReceive('screenWithSymbols')
            ->once()
            ->withArgs(static fn (array $filters, array $pageSymbols): bool =>
                $filters['market'] === 'CRYPTO'
                && count($pageSymbols) === 1
                && $pageSymbols[0]['symbol'] === 'COIN21USDT'
            )
            ->andReturn([
                'market' => 'CRYPTO',
                'timeframe' => 'H1',
                'provider' => 'BINANCE',
                'generated_at_utc' => '2026-10-05T08:00:00+00:00',
                'count' => 1,
                'cache' => ['hit' => true, 'stale' => false],
                'results' => [[
                    ...$symbols[20],
                    'price' => 1.0,
                    'trend' => 'SIDEWAYS',
                ]],
            ]);

        $this->get(route('admin.screener.markets', [
            'market' => 'CRYPTO',
            'timeframe' => 'H1',
            'page' => 2,
        ]))
            ->assertOk()
            ->assertSee('COIN21USDT')
            ->assertSee('21 Binance USDT Spot symbols')
            ->assertSee('Page')
            ->assertSee('of');
    }

    private function adminWithPermissions(array $permissionCodes): AdminUser
    {
        $role = AdminRole::query()->create([
            'code' => 'OPS_'.Str::upper(Str::random(12)),
            'name' => 'Ops '.Str::random(8),
            'description' => 'Operational navigation test role',
            'is_system' => false,
            'is_active' => true,
        ]);

        $permissionIds = AdminPermission::query()
            ->whereIn('code', $permissionCodes)
            ->pluck('id')
            ->all();

        $this->assertCount(count($permissionCodes), $permissionIds);
        $role->permissions()->sync($permissionIds);

        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Ops Admin',
            'email' => 'ops-'.Str::lower(Str::random(16)).'@example.test',
            'email_verified_at' => now(),
            'password' => 'Testing-Ops-Pass-123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
        ]);

        $admin->roles()->sync([$role->id]);

        return $admin->fresh('roles');
    }

    private function layout(): string
    {
        return view('admin.layouts.app', [
            'errors' => new ViewErrorBag(),
        ])->render();
    }
}
