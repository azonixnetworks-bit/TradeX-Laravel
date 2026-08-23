<?php

namespace Tests\Feature\Api\Notifications;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_api_requires_authentication(): void
    {
        $this
            ->getJson(
                '/api/v1/notifications'
            )
            ->assertUnauthorized();

        $this
            ->getJson(
                '/api/v1/notifications/summary'
            )
            ->assertUnauthorized();

        $this
            ->patchJson(
                '/api/v1/notifications/read-all'
            )
            ->assertUnauthorized();
    }
}
