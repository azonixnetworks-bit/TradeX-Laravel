<?php

namespace Tests\Feature\Api\MarketClock;

use Tests\TestCase;

class MarketClockApiContractTest extends TestCase
{
    public function test_authenticated_mobile_market_clock_route_is_wired_through_dashboard_provider_file(): void
    {
        $routes = file_get_contents(base_path('routes/api_dashboard.php'));
        $controller = file_get_contents(
            app_path('Http/Controllers/Api/MarketClock/MarketClockController.php')
        );

        $this->assertIsString($routes);
        $this->assertIsString($controller);
        $this->assertStringContainsString("'/market-clock'", $routes);
        $this->assertStringContainsString('MarketClockController::class', $routes);
        $this->assertStringContainsString("'auth:sanctum'", $routes);
        $this->assertStringContainsString("'tradex.mobile'", $routes);
        $this->assertStringContainsString('throttle:api-dashboard-read', $routes);
        $this->assertStringContainsString('MarketClockService', $controller);
        $this->assertStringContainsString('Invalid IANA timezone.', $controller);
    }
}
