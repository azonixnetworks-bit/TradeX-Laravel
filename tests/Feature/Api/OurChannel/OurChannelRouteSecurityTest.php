<?php

namespace Tests\Feature\Api\OurChannel;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OurChannelRouteSecurityTest extends TestCase
{
    public function test_our_channel_routes_are_registered_under_mobile_security_stack(): void
    {
        $index = Route::getRoutes()->getByName(
            'api.v1.our-channel.videos.index'
        );

        $show = Route::getRoutes()->getByName(
            'api.v1.our-channel.videos.show'
        );

        $this->assertNotNull($index);
        $this->assertNotNull($show);

        $this->assertSame(
            'api/v1/our-channel/videos',
            $index->uri()
        );

        $this->assertSame(
            'api/v1/our-channel/videos/{channelVideo}',
            $show->uri()
        );

        foreach ([$index, $show] as $route) {
            $middleware = $route->gatherMiddleware();

            $this->assertContains(
                'auth:sanctum',
                $middleware
            );
            $this->assertContains(
                'tradex.mobile',
                $middleware
            );
            $this->assertContains(
                'throttle:api-our-channel-read',
                $middleware
            );
        }

    }
}
