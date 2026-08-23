<?php

namespace Tests\Feature\Api\Profile\Settings;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AccountSettingsRouteSecurityTest extends TestCase
{
    public function test_api11_routes_use_mobile_security_and_dedicated_rate_limits(): void
    {
        $routes = [
            'api.v1.profile.settings.show' => ['GET', 'api-account-settings-read'],
            'api.v1.profile.settings.security' => ['GET', 'api-account-settings-read'],
            'api.v1.profile.settings.devices.index' => ['GET', 'api-account-settings-read'],
            'api.v1.profile.settings.devices.revoke' => ['PATCH', 'api-account-settings-sensitive'],
        ];

        foreach ($routes as $name => [$method, $limiter]) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, $name.' route is missing.');
            $this->assertContains($method, $route->methods());
            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth:sanctum', $middleware);
            $this->assertContains('tradex.mobile', $middleware);
            $this->assertContains('throttle:'.$limiter, $middleware);
        }
    }
}
