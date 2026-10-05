<?php

namespace Tests\Feature\Admin\Screener;

use Tests\TestCase;

class ScreenerMonitorContractTest extends TestCase
{
    public function test_admin_screener_monitor_is_read_only_permission_gated_and_wired(): void
    {
        $webRoutes = file_get_contents(base_path('routes/web.php'));
        $routes = file_get_contents(base_path('routes/admin_screener.php'));
        $controller = file_get_contents(base_path(
            'app/Http/Controllers/Admin/Screener/ScreenerMonitorController.php'
        ));
        $layout = file_get_contents(resource_path(
            'views/admin/layouts/app.blade.php'
        ));
        $view = file_get_contents(resource_path(
            'views/admin/screener/index.blade.php'
        ));
        $marketView = file_get_contents(resource_path(
            'views/admin/screener/markets.blade.php'
        ));
        $css = file_get_contents(public_path(
            'admin/css/screener-monitor.css'
        ));
        $nav = file_get_contents(public_path(
            'admin/js/screener-monitor-nav.js'
        ));
        $monitor = file_get_contents(public_path(
            'admin/js/screener-monitor.js'
        ));

        foreach (
            [$webRoutes, $routes, $controller, $layout, $view, $marketView, $css, $nav, $monitor]
            as $file
        ) {
            $this->assertIsString($file);
        }

        $this->assertStringContainsString(
            'admin_screener.php',
            $webRoutes
        );
        $this->assertStringContainsString(
            "name('screener.')",
            $routes
        );
        $this->assertStringContainsString(
            "hasPermission('screener.monitor.view')",
            $controller
        );
        $this->assertStringContainsString(
            "hasPermission('screener.monitor.view')",
            $layout
        );
        $this->assertStringContainsString(
            'private, no-store, no-cache',
            $controller
        );
        $this->assertStringContainsString(
            'admin/js/screener-monitor-nav.js',
            $layout
        );
        $this->assertStringContainsString(
            "route('admin.screener.index')",
            $layout
        );
        $this->assertStringContainsString(
            'Screener Monitor',
            $view
        );
        $this->assertStringContainsString(
            'Secret-safe diagnostics',
            $view
        );
        $this->assertStringContainsString(
            "name('markets')",
            $routes
        );
        $this->assertStringContainsString(
            'Market Results',
            $marketView
        );
        $this->assertStringContainsString(
            'screener-market-buttons',
            $marketView
        );
        $this->assertStringContainsString(
            'Search Binance symbols',
            $marketView
        );
        $this->assertStringContainsString(
            'admin.components.pagination',
            $marketView
        );
        $this->assertStringContainsString(
            'ScreenerEngine $engine',
            $controller
        );
        $this->assertStringContainsString(
            'data-screener-markets-url',
            $layout
        );
        $this->assertStringContainsString(
            'Open Screener Market Results',
            $nav
        );
        $this->assertStringContainsString(
            '.screener-provider-table',
            $css
        );
        $this->assertStringContainsString(
            'Screener Monitor',
            $nav
        );
        $this->assertStringContainsString(
            "cache: 'no-store'",
            $monitor
        );

        $this->assertStringNotContainsString(
            'TRADEX_TWELVE_DATA_API_KEY',
            $view
        );
        $this->assertStringNotContainsString(
            'api_key',
            strtolower($view)
        );
    }
}
