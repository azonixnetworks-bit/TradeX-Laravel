<?php

namespace Tests\Feature\Api\TradeIdeas;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TradeIdeaRouteSecurityTest extends TestCase
{
    public function test_all_api06_trade_idea_routes_use_mobile_auth_and_dedicated_throttles(): void
    {
        $expectations = [
            'api.v1.trade-ideas.index' =>
                'throttle:api-trade-ideas-read',

            'api.v1.trade-ideas.show' =>
                'throttle:api-trade-ideas-read',

            'api.v1.trade-ideas.chart' =>
                'throttle:api-trade-ideas-media',

            'api.v1.trade-ideas.share-card.uuid' =>
                'throttle:api-trade-ideas-share',

            'api.v1.trade-ideas.share-action' =>
                'throttle:api-trade-ideas-share',
        ];

        foreach (
            $expectations
            as $name => $throttle
        ) {
            $route = Route::getRoutes()
                ->getByName(
                    $name
                );

            $this->assertNotNull(
                $route,
                $name
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
                $throttle,
                $middleware
            );
        }
    }

    public function test_existing_numeric_share_card_route_is_preserved_for_backward_compatibility(): void
    {
        $route = Route::getRoutes()
            ->getByName(
                'api.v1.trade-ideas.share-card'
            );

        $this->assertNotNull(
            $route
        );

        $this->assertSame(
            'api/v1/trade-ideas/{tradeIdea}/share-card',
            $route->uri()
        );
    }
}
