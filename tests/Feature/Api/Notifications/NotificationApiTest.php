<?php

namespace Tests\Feature\Api\Notifications;

use App\Models\NotificationMessage;
use App\Models\User;
use App\Models\UserNotificationReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_free_user_sees_only_current_all_and_free_notifications(): void
    {
        $user = $this->createApiUser([
            'account_type' => 'FREE',
        ]);

        $all = $this->assign(
            $user,
            [
                'title' =>
                    'All Users',

                'audience_access_level' =>
                    NotificationMessage::AUDIENCE_ALL,
            ]
        );

        $free = $this->assign(
            $user,
            [
                'title' =>
                    'Free Users',

                'audience_access_level' =>
                    NotificationMessage::AUDIENCE_FREE,
            ]
        );

        $premium = $this->assign(
            $user,
            [
                'title' =>
                    'Premium Secret',

                'audience_access_level' =>
                    NotificationMessage::AUDIENCE_PREMIUM,
            ]
        );

        $this->assign(
            $user,
            [
                'title' =>
                    'Future Hidden',

                'published_at' =>
                    now()->addHour(),
            ]
        );

        $this->assign(
            $user,
            [
                'title' =>
                    'Expired Hidden',

                'expires_at' =>
                    now()->subMinute(),
            ]
        );

        $token =
            $this->issueBoundToken(
                $user
            );

        $response = $this
            ->withToken($token)
            ->getJson(
                '/api/v1/notifications'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.pagination.total',
                2
            )
            ->assertJsonPath(
                'data.unread_count',
                2
            );

        $json = json_encode(
            $response->json('data'),
            JSON_THROW_ON_ERROR
        );

        $this->assertStringContainsString(
            $all->notification->title,
            $json
        );

        $this->assertStringContainsString(
            $free->notification->title,
            $json
        );

        $this->assertStringNotContainsString(
            $premium->notification->title,
            $json
        );

        $this->assertNoInternalIdKey(
            $response->json('data')
        );
    }

    public function test_premium_user_sees_all_and_premium_but_not_free_only(): void
    {
        $user = $this->createApiUser([
            'account_type' => 'PREMIUM',
        ]);

        $this->assign(
            $user,
            [
                'title' =>
                    'All Users',

                'audience_access_level' =>
                    NotificationMessage::AUDIENCE_ALL,
            ]
        );

        $this->assign(
            $user,
            [
                'title' =>
                    'Premium Users',

                'audience_access_level' =>
                    NotificationMessage::AUDIENCE_PREMIUM,
            ]
        );

        $this->assign(
            $user,
            [
                'title' =>
                    'Free Only Hidden',

                'audience_access_level' =>
                    NotificationMessage::AUDIENCE_FREE,
            ]
        );

        $token =
            $this->issueBoundToken(
                $user
            );

        $response = $this
            ->withToken($token)
            ->getJson(
                '/api/v1/notifications'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.pagination.total',
                2
            );

        $json = json_encode(
            $response->json('data'),
            JSON_THROW_ON_ERROR
        );

        $this->assertStringContainsString(
            'Premium Users',
            $json
        );

        $this->assertStringNotContainsString(
            'Free Only Hidden',
            $json
        );
    }

    public function test_list_filters_and_summary_use_recipient_read_state(): void
    {
        $user = $this->createApiUser();

        $read = $this->assign(
            $user,
            [
                'category' =>
                    NotificationMessage::CATEGORY_EDUCATION,

                'title' =>
                    'Read Education',
            ],
            now()->subMinute()
        );

        $unread = $this->assign(
            $user,
            [
                'category' =>
                    NotificationMessage::CATEGORY_SECURITY,

                'title' =>
                    'Unread Security',
            ]
        );

        $token =
            $this->issueBoundToken(
                $user
            );

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/notifications?status=unread'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.pagination.total',
                1
            )
            ->assertJsonPath(
                'data.items.0.uuid',
                $unread->uuid
            );

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/notifications?category=education'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.pagination.total',
                1
            )
            ->assertJsonPath(
                'data.items.0.uuid',
                $read->uuid
            );

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/notifications/summary'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.total_available',
                2
            )
            ->assertJsonPath(
                'data.unread_count',
                1
            )
            ->assertJsonPath(
                'data.has_unread',
                true
            );
    }

    public function test_detail_is_recipient_scoped_and_prevents_idor(): void
    {
        $owner = $this->createApiUser();
        $other = $this->createApiUser();

        $receipt = $this->assign(
            $owner,
            [
                'title' =>
                    'Owner Private Notification',
            ]
        );

        $otherToken =
            $this->issueBoundToken(
                $other
            );

        $this
            ->withToken($otherToken)
            ->getJson(
                '/api/v1/notifications/'
                .$receipt->uuid
            )
            ->assertNotFound()
            ->assertJsonPath(
                'error.code',
                'NOTIFICATION_NOT_AVAILABLE'
            );
    }

    public function test_mark_read_is_idempotent_and_returns_updated_unread_count(): void
    {
        $user = $this->createApiUser();

        $receipt = $this->assign(
            $user,
            [
                'title' =>
                    'Mark Me Read',
            ]
        );

        $this->assign(
            $user,
            [
                'title' =>
                    'Still Unread',
            ]
        );

        $token =
            $this->issueBoundToken(
                $user
            );

        $first = $this
            ->withToken($token)
            ->patchJson(
                '/api/v1/notifications/'
                .$receipt->uuid
                .'/read'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.notification.is_read',
                true
            )
            ->assertJsonPath(
                'data.unread_count',
                1
            );

        $readAt =
            $first->json(
                'data.notification.read_at'
            );

        $this
            ->withToken($token)
            ->patchJson(
                '/api/v1/notifications/'
                .$receipt->uuid
                .'/read'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.notification.read_at',
                $readAt
            )
            ->assertJsonPath(
                'data.unread_count',
                1
            );
    }

    public function test_mark_all_read_only_updates_currently_available_notifications(): void
    {
        $user = $this->createApiUser([
            'account_type' => 'FREE',
        ]);

        $visibleOne =
            $this->assign(
                $user,
                [
                    'title' =>
                        'Visible One',
                ]
            );

        $visibleTwo =
            $this->assign(
                $user,
                [
                    'title' =>
                        'Visible Two',

                    'audience_access_level' =>
                        NotificationMessage::AUDIENCE_FREE,
                ]
            );

        $hiddenPremium =
            $this->assign(
                $user,
                [
                    'title' =>
                        'Hidden Premium',

                    'audience_access_level' =>
                        NotificationMessage::AUDIENCE_PREMIUM,
                ]
            );

        $token =
            $this->issueBoundToken(
                $user
            );

        $this
            ->withToken($token)
            ->patchJson(
                '/api/v1/notifications/read-all'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.marked_read_count',
                2
            )
            ->assertJsonPath(
                'data.unread_count',
                0
            );

        $this->assertNotNull(
            $visibleOne->fresh()->read_at
        );

        $this->assertNotNull(
            $visibleTwo->fresh()->read_at
        );

        $this->assertNull(
            $hiddenPremium->fresh()->read_at
        );
    }

    public function test_action_payload_uses_only_approved_internal_navigation_contract(): void
    {
        $user = $this->createApiUser([
            'account_type' => 'PREMIUM',
        ]);

        $receipt = $this->assign(
            $user,
            [
                'title' =>
                    'Open Lesson',

                'action_type' =>
                    NotificationMessage::ACTION_EDUCATION_LESSON,

                'action_resource_uuid' =>
                    '11111111-1111-4111-8111-111111111111',
            ]
        );

        $token =
            $this->issueBoundToken(
                $user
            );

        $response = $this
            ->withToken($token)
            ->getJson(
                '/api/v1/notifications/'
                .$receipt->uuid
            )
            ->assertOk()
            ->assertJsonPath(
                'data.action.type',
                NotificationMessage::ACTION_EDUCATION_LESSON
            )
            ->assertJsonPath(
                'data.action.resource_uuid',
                '11111111-1111-4111-8111-111111111111'
            );

        $json = json_encode(
            $response->json('data'),
            JSON_THROW_ON_ERROR
        );

        $this->assertStringNotContainsString(
            'http://',
            $json
        );

        $this->assertStringNotContainsString(
            'https://',
            $json
        );
    }

    private function assign(
        User $user,
        array $overrides = [],
        mixed $readAt = null
    ): UserNotificationReceipt {
        $message =
            NotificationMessage::query()
                ->create(
                    array_merge(
                        [
                            'audience_access_level' =>
                                NotificationMessage::AUDIENCE_ALL,

                            'category' =>
                                NotificationMessage::CATEGORY_GENERAL,

                            'priority' =>
                                NotificationMessage::PRIORITY_NORMAL,

                            'title' =>
                                'Notification',

                            'body' =>
                                'Notification body.',

                            'action_type' =>
                                NotificationMessage::ACTION_NONE,

                            'is_active' =>
                                true,

                            'published_at' =>
                                now()->subMinute(),

                            'expires_at' =>
                                null,
                        ],
                        $overrides
                    )
                );

        return UserNotificationReceipt::query()
            ->create([
                'notification_message_id' =>
                    $message->getKey(),

                'user_id' =>
                    $user->getKey(),

                'read_at' =>
                    $readAt,
            ])
            ->fresh(
                'notification'
            );
    }

    private function assertNoInternalIdKey(
        mixed $value
    ): void {
        if (! is_array($value)) {
            return;
        }

        foreach (
            $value as $key => $nested
        ) {
            if (is_string($key)) {
                $this->assertNotSame(
                    'id',
                    $key,
                    'Notification API must not expose internal numeric id fields.'
                );
            }

            $this->assertNoInternalIdKey(
                $nested
            );
        }
    }
}
