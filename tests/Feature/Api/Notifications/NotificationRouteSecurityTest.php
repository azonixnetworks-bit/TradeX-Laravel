<?php

namespace Tests\Feature\Api\Notifications;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class NotificationRouteSecurityTest extends TestCase
{
    public function test_api10_routes_use_mobile_security_and_dedicated_throttles(): void
    {
        $routes = [
            'api.v1.notifications.index' =>
                'api-notifications-read',

            'api.v1.notifications.summary' =>
                'api-notifications-read',

            'api.v1.notifications.show' =>
                'api-notifications-read',

            'api.v1.notifications.read' =>
                'api-notifications-write',

            'api.v1.notifications.read-all' =>
                'api-notifications-write',
        ];

        foreach (
            $routes as $name => $limiter
        ) {
            $route =
                Route::getRoutes()
                    ->getByName(
                        $name
                    );

            $this->assertNotNull(
                $route,
                $name.' route is missing.'
            );

            $middleware =
                $route->gatherMiddleware();

            $this->assertContains(
                'auth:sanctum',
                $middleware
            );

            $this->assertContains(
                'tradex.mobile',
                $middleware
            );

            $this->assertContains(
                'throttle:'.$limiter,
                $middleware
            );
        }
    }
}
