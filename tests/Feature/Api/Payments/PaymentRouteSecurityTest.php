<?php

namespace Tests\Feature\Api\Payments;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PaymentRouteSecurityTest extends TestCase
{
    public function test_api12_user_routes_have_mobile_security_and_dedicated_throttles(): void
    {
        $routes = [
            'api.v1.payments.methods.index' =>
                ['GET', 'api-payments-read'],

            'api.v1.payments.activation-status' =>
                ['GET', 'api-payments-read'],

            'api.v1.payments.history' =>
                ['GET', 'api-payments-read'],

            'api.v1.payments.orders.show' =>
                ['GET', 'api-payments-read'],

            'api.v1.payments.paypal.orders.create' =>
                ['POST', 'api-payments-create'],

            'api.v1.payments.paypal.orders.capture' =>
                ['POST', 'api-payments-capture'],

            'api.v1.payments.bank-deposits.store' =>
                ['POST', 'api-payments-create'],

            'api.v1.payments.usdt-deposits.store' =>
                ['POST', 'api-payments-create'],
        ];

        foreach (
            $routes as
            $name => [$method, $limiter]
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

            $this->assertContains(
                $method,
                $route->methods()
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

    public function test_paypal_webhook_is_public_provider_route_but_has_api_and_webhook_throttle(): void
    {
        $route =
            Route::getRoutes()
                ->getByName(
                    'api.v1.payments.paypal.webhook'
                );

        $this->assertNotNull(
            $route
        );

        $this->assertContains(
            'POST',
            $route->methods()
        );

        $middleware =
            $route->gatherMiddleware();

        $this->assertContains(
            'api',
            $middleware
        );

        $this->assertContains(
            'throttle:api-payments-webhook',
            $middleware
        );

        $this->assertNotContains(
            'auth:sanctum',
            $middleware
        );

        $this->assertNotContains(
            'tradex.mobile',
            $middleware
        );
    }
}
