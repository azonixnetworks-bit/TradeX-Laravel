<?php

namespace Tests\Feature\Api\TradeIdeas;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class UserTradeIdeaShareCardApiTest extends TestCase
{
    public function test_personalized_share_card_api_route_is_registered_and_protected(): void
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
            'throttle:api-share-card',
            $middleware
        );
    }
}
