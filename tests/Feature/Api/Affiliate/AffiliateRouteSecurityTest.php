<?php

namespace Tests\Feature\Api\Affiliate;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AffiliateRouteSecurityTest extends TestCase
{
    public function test_all_api09_routes_use_mobile_security_and_dedicated_throttles(): void
    {
        $routes = [
            'api.v1.affiliate.overview' =>
                ['GET', 'api-affiliate-read'],

            'api.v1.affiliate.stages.index' =>
                ['GET', 'api-affiliate-read'],

            'api.v1.affiliate.referrals.index' =>
                ['GET', 'api-affiliate-read'],

            'api.v1.affiliate.sales.index' =>
                ['GET', 'api-affiliate-read'],

            'api.v1.affiliate.commissions.index' =>
                ['GET', 'api-affiliate-read'],

            'api.v1.affiliate.milestones.index' =>
                ['GET', 'api-affiliate-read'],

            'api.v1.affiliate.share-action' =>
                ['POST', 'api-affiliate-share'],
        ];

        foreach (
            $routes as $name => [$method, $limiter]
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
}
