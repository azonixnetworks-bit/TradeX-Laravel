<?php

namespace Tests\Feature\Admin\MarketClock;

use Tests\TestCase;

class MarketClockContractTest extends TestCase
{
    public function test_market_clock_admin_module_is_wired_into_routes_navigation_and_assets(): void
    {
        $webRoutes = file_get_contents(base_path('routes/web.php'));
        $marketRoutes = file_get_contents(base_path('routes/admin_market_clock.php'));
        $controller = file_get_contents(base_path(
            'app/Http/Controllers/Admin/MarketClock/MarketClockController.php'
        ));
        $layout = file_get_contents(resource_path('views/admin/layouts/app.blade.php'));
        $view = file_get_contents(resource_path('views/admin/market_clock/index.blade.php'));
        $css = file_get_contents(public_path('admin/css/market-clock.css'));
        $navJs = file_get_contents(public_path('admin/js/market-clock-nav.js'));
        $clockJs = file_get_contents(public_path('admin/js/market-clock.js'));

        foreach ([$webRoutes, $marketRoutes, $controller, $layout, $view, $css, $navJs, $clockJs] as $file) {
            $this->assertIsString($file);
        }

        $this->assertStringContainsString('admin_market_clock.php', $webRoutes);
        $this->assertStringContainsString("name('market_clock.')", $marketRoutes);
        $this->assertStringContainsString("hasPermission('market_clock.view')", $controller);
        $this->assertStringContainsString("hasPermission('market_clock.view')", $layout);
        $this->assertStringContainsString('admin/js/market-clock-nav.js', $layout);
        $this->assertStringContainsString("route('admin.market_clock.index')", $layout);
        $this->assertStringContainsString('Market Clock', $navJs);
        $this->assertStringContainsString('marketClockDial', $view);
        $this->assertStringContainsString('admin/css/market-clock.css', $view);
        $this->assertStringContainsString('admin/js/market-clock.js', $view);
        $this->assertStringContainsString('.market-clock-dial', $css);
        $this->assertStringContainsString('tradex.marketClock.timezone.v1', $clockJs);
        $this->assertStringContainsString('resolvedOptions().timeZone', $clockJs);
        $this->assertStringContainsString('serverEpochAtSync', $clockJs);
    }
}
