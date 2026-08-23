<?php

namespace Tests\Feature\Api\Dashboard;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DashboardRouteSecurityTest extends TestCase
{
    public function test_dashboard_route_is_registered_under_mobile_security_stack(): void
    {
        $route = Route::getRoutes()->getByName(
            'api.v1.dashboard.show'
        );

        $this->assertNotNull(
            $route
        );

        $this->assertSame(
            'api/v1/dashboard',
            $route->uri()
        );

        $this->assertContains(
            'GET',
            $route->methods()
        );

        $middleware = $route
            ->gatherMiddleware();

        $this->assertContains(
            'api',
            $middleware
        );

        $this->assertContains(
            'auth:sanctum',
            $middleware
        );

        $this->assertContains(
            'tradex.mobile',
            $middleware
        );

        $this->assertContains(
            'throttle:api-dashboard-read',
            $middleware
        );
    }
}
