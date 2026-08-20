<?php

namespace Tests\Feature\Admin;

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminRouteHardeningTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Public Admin Authentication Routes
    |--------------------------------------------------------------------------
    */

    public function test_admin_login_routes_remain_public(): void
    {
        $loginPage =
            $this->namedRoute(
                'admin.login'
            );

        $loginSubmit =
            $this->namedRoute(
                'admin.login.submit'
            );


        $this->assertNotContains(
            'admin.auth',
            $loginPage->gatherMiddleware()
        );

        $this->assertNotContains(
            'admin.auth',
            $loginSubmit->gatherMiddleware()
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Protected User Management Routes
    |--------------------------------------------------------------------------
    */

    public function test_user_management_routes_require_admin_auth(): void
    {
        $protectedRoutes = [
            'admin.users.index',
            'admin.users.show',
            'admin.users.status.update',
            'admin.users.devices.revoke',
            'admin.users.devices.reset',
            'admin.users.sessions.revoke',
        ];


        foreach ($protectedRoutes as $routeName) {

            $route =
                $this->namedRoute(
                    $routeName
                );


            $this->assertContains(
                'admin.auth',
                $route->gatherMiddleware(),
                "Route [{$routeName}] must use admin.auth middleware."
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Frozen HTTP Method Contracts
    |--------------------------------------------------------------------------
    */

    public function test_admin_user_routes_keep_expected_http_methods(): void
    {
        $expected = [

            'admin.users.index' => [
                'GET',
                'HEAD',
            ],

            'admin.users.show' => [
                'GET',
                'HEAD',
            ],

            'admin.users.status.update' => [
                'PATCH',
            ],

            'admin.users.devices.revoke' => [
                'PATCH',
            ],

            'admin.users.devices.reset' => [
                'POST',
            ],

            'admin.users.sessions.revoke' => [
                'POST',
            ],
        ];


        foreach (
            $expected
            as $routeName => $methods
        ) {

            $route =
                $this->namedRoute(
                    $routeName
                );


            $this->assertSame(
                $methods,
                $route->methods(),
                "Unexpected HTTP method contract for route [{$routeName}]."
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Frozen URI Contracts
    |--------------------------------------------------------------------------
    */

    public function test_admin_user_routes_keep_expected_uris(): void
    {
        $expected = [

            'admin.users.index' =>
                'admin/users',

            'admin.users.show' =>
                'admin/users/{user}',

            'admin.users.status.update' =>
                'admin/users/{user}/status',

            'admin.users.devices.revoke' =>
                'admin/users/{user}/devices/{device}/revoke',

            'admin.users.devices.reset' =>
                'admin/users/{user}/devices/reset',

            'admin.users.sessions.revoke' =>
                'admin/users/{user}/sessions/revoke',
        ];


        foreach (
            $expected
            as $routeName => $uri
        ) {

            $route =
                $this->namedRoute(
                    $routeName
                );


            $this->assertSame(
                $uri,
                $route->uri(),
                "Unexpected URI contract for route [{$routeName}]."
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Nested Device Binding Hardening
    |--------------------------------------------------------------------------
    */

    public function test_device_revoke_route_enforces_scoped_bindings(): void
    {
        $route =
            $this->namedRoute(
                'admin.users.devices.revoke'
            );


        $this->assertTrue(
            $route->enforcesScopedBindings(),
            'Device revoke route must enforce scoped bindings.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Numeric User Route Constraints
    |--------------------------------------------------------------------------
    */

    public function test_user_route_parameters_are_numeric_only(): void
    {
        $routes = [

            'admin.users.show',

            'admin.users.status.update',

            'admin.users.devices.reset',

            'admin.users.sessions.revoke',
        ];


        foreach ($routes as $routeName) {

            $route =
                $this->namedRoute(
                    $routeName
                );


            $this->assertNumericConstraint(
                $route,
                'user',
                $routeName
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Numeric Nested Device Constraint
    |--------------------------------------------------------------------------
    */

    public function test_device_revoke_parameters_are_numeric_only(): void
    {
        $route =
            $this->namedRoute(
                'admin.users.devices.revoke'
            );


        $this->assertNumericConstraint(
            $route,
            'user',
            'admin.users.devices.revoke'
        );


        $this->assertNumericConstraint(
            $route,
            'device',
            'admin.users.devices.revoke'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Critical Route Names Exist
    |--------------------------------------------------------------------------
    */

    public function test_all_admin_02e_security_routes_exist(): void
    {
        $routeNames = [

            'admin.users.index',

            'admin.users.show',

            'admin.users.status.update',

            'admin.users.devices.revoke',

            'admin.users.devices.reset',

            'admin.users.sessions.revoke',
        ];


        foreach ($routeNames as $routeName) {

            $this->assertInstanceOf(
                LaravelRoute::class,
                Route::getRoutes()
                    ->getByName(
                        $routeName
                    ),
                "Required ADMIN-02E route [{$routeName}] is missing."
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Helper — Resolve Named Route
    |--------------------------------------------------------------------------
    */

    private function namedRoute(
        string $name
    ): LaravelRoute {

        $route =
            Route::getRoutes()
                ->getByName(
                    $name
                );


        if (! $route instanceof LaravelRoute) {

            $this->fail(
                "Route [{$name}] was not registered."
            );
        }


        return $route;
    }


    /*
    |--------------------------------------------------------------------------
    | Helper — Validate Numeric Constraint
    |--------------------------------------------------------------------------
    */

    private function assertNumericConstraint(
        LaravelRoute $route,
        string $parameter,
        string $routeName
    ): void {

        $pattern =
            $route->wheres[
                $parameter
            ] ?? null;


        $this->assertIsString(
            $pattern,
            "Route [{$routeName}] must constrain {{$parameter}}."
        );


        $this->assertSame(
            1,
            preg_match(
                '~^(?:'
                . $pattern
                . ')$~',
                '123'
            ),
            "Route [{$routeName}] {{$parameter}} must accept numeric IDs."
        );


        $this->assertSame(
            0,
            preg_match(
                '~^(?:'
                . $pattern
                . ')$~',
                'abc'
            ),
            "Route [{$routeName}] {{$parameter}} must reject alphabetic IDs."
        );


        $this->assertSame(
            0,
            preg_match(
                '~^(?:'
                . $pattern
                . ')$~',
                '12abc'
            ),
            "Route [{$routeName}] {{$parameter}} must reject mixed IDs."
        );
    }
}