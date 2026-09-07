<?php

namespace Tests\Feature\Admin\Screener;

use Tests\TestCase;

class ScreenerLiveContractTest extends TestCase
{
    public function test_admin_live_screener_is_permission_gated_and_wired(): void
    {
        $routes = file_get_contents(base_path('routes/admin_screener.php'));
        $controller = file_get_contents(base_path(
            'app/Http/Controllers/Admin/Screener/ScreenerLiveController.php'
        ));
        $layout = file_get_contents(resource_path(
            'views/admin/layouts/app.blade.php'
        ));
        $view = file_get_contents(resource_path(
            'views/admin/screener/live.blade.php'
        ));
        $css = file_get_contents(public_path(
            'admin/css/screener-live.css'
        ));
        $js = file_get_contents(public_path(
            'admin/js/screener-live.js'
        ));
        $nav = file_get_contents(public_path(
            'admin/js/screener-monitor-nav.js'
        ));

        foreach ([$routes, $controller, $layout, $view, $css, $js, $nav] as $file) {
            $this->assertIsString($file);
        }

        $this->assertStringContainsString("'/live'", $routes);
        $this->assertStringContainsString("'/live/data'", $routes);
        $this->assertStringContainsString("name('live')", $routes);
        $this->assertStringContainsString("name('live_data')", $routes);
        $this->assertStringContainsString(
            "hasPermission('dashboard.view')",
            $controller
        );
        $this->assertStringContainsString(
            'ScreenerProviderException',
            $controller
        );
        $this->assertStringContainsString(
            'private, no-store, no-cache',
            $controller
        );
        $this->assertStringContainsString(
            "route('admin.screener.live')",
            $layout
        );
        $this->assertStringContainsString(
            'data-live-screener-url',
            $layout
        );
        $this->assertStringContainsString('Live Screener', $view);
        $this->assertStringContainsString('EMA 200', $view);
        $this->assertStringContainsString('MACD Hist.', $view);
        $this->assertStringContainsString('ATR %', $view);
        $this->assertStringContainsString('.live-screener-table', $css);
        $this->assertStringContainsString("cache: 'no-store'", $js);
        $this->assertStringContainsString('setInterval(loadData, 30000)', $js);
        $this->assertStringContainsString('STALE FALLBACK', $js);
        $this->assertStringContainsString('Live Screener', $nav);

        $this->assertStringNotContainsString('api_key', strtolower($view));
        $this->assertStringNotContainsString('access_token', strtolower($view));
        $this->assertStringNotContainsString('TRADEX_TWELVE_DATA_API_KEY', $js);
    }
}
