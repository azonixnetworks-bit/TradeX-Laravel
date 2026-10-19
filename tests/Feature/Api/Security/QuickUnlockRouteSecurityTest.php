<?php

namespace Tests\Feature\Api\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class QuickUnlockRouteSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_security_routes_keep_expected_security_stack(): void
    {
        $routes = collect(
            Route::getRoutes()->getRoutes()
        )->filter(
            fn ($route): bool => str_starts_with(
                $route->uri(),
                'api/v1/security/quick-unlock'
            )
        );

        $this->assertCount(
            3,
            $routes
        );

        $show = $routes->first(
            fn ($route): bool => in_array(
                'GET',
                $route->methods(),
                true
            )
        );

        $update = $routes->first(
            fn ($route): bool => in_array(
                'PATCH',
                $route->methods(),
                true
            )
        );

        $forget = $routes->first(
            fn ($route): bool => in_array('DELETE', $route->methods(), true)
        );

        $this->assertNotNull($show);
        $this->assertNotNull($update);
        $this->assertNotNull($forget);

        foreach ([$show, $update, $forget] as $route) {
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
        }

        $this->assertContains(
            'throttle:api-mobile-security-read',
            $show->gatherMiddleware()
        );

        $this->assertContains(
            'throttle:api-mobile-security-change',
            $update->gatherMiddleware()
        );

        $this->assertContains(
            'throttle:api-mobile-security-change',
            $forget->gatherMiddleware()
        );
    }
}
