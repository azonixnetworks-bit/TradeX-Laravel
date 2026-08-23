<?php

namespace Tests\Feature\Api\Notifications;

use App\Models\NotificationMessage;
use App\Models\UserNotificationReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class NotificationRateLimitTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_notification_write_actions_have_dedicated_user_rate_limit(): void
    {
        config()->set(
            'tradex_notifications_api.rate_limits.write_per_minute',
            1
        );

        RateLimiter::clear(
            'api-notifications-write'
        );

        $user =
            $this->createApiUser();

        $message =
            NotificationMessage::query()
                ->create([
                    'title' =>
                        'Rate Limit Notification',

                    'body' =>
                        'Body',

                    'published_at' =>
                        now()->subMinute(),
                ]);

        UserNotificationReceipt::query()
            ->create([
                'notification_message_id' =>
                    $message->getKey(),

                'user_id' =>
                    $user->getKey(),
            ]);

        $token =
            $this->issueBoundToken(
                $user
            );

        $this
            ->withToken($token)
            ->patchJson(
                '/api/v1/notifications/read-all'
            )
            ->assertOk();

        $this
            ->withToken($token)
            ->patchJson(
                '/api/v1/notifications/read-all'
            )
            ->assertStatus(
                429
            );
    }
}
