<?php

namespace Tests\Feature\Admin\Notifications;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\NotificationMessage;
use App\Models\User;
use App\Services\Notifications\NotificationApiService;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationBroadcastManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            AdminRoleSeeder::class
        );

        $this->seed(
            AdminPermissionSeeder::class
        );
    }

    public function test_guest_is_redirected_from_notifications(): void
    {
        $this->get(
            route(
                'admin.notifications.index'
            )
        )->assertRedirect(
            route('admin.login')
        );
    }

    public function test_notifications_view_permission_is_required(): void
    {
        $admin =
            $this->adminWithPermissions(
                []
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.notifications.index'
                )
            )
            ->assertForbidden();
    }

    public function test_create_requires_view_and_create_permissions(): void
    {
        $admin =
            $this->adminWithPermissions([
                'notifications.view',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.notifications.create'
                )
            )
            ->assertForbidden();
    }

    public function test_authorized_admin_can_create_notification_draft(): void
    {
        $admin =
            $this->adminWithPermissions([
                'notifications.view',
                'notifications.create',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.notifications.store'
                ),
                $this->draftPayload([
                    'title' =>
                        'ADMIN-15 Draft',
                ])
            )
            ->assertRedirect();

        $message =
            NotificationMessage::query()
                ->where(
                    'title',
                    'ADMIN-15 Draft'
                )
                ->firstOrFail();

        $this->assertNull(
            $message->published_at
        );

        $this->assertSame(
            0,
            $message->receipts()
                ->count()
        );

        $this->assertSame(
            $admin->id,
            $message
                ->created_by_admin_id
        );
    }

    public function test_draft_creation_audit_does_not_copy_title_or_body(): void
    {
        $admin =
            $this->adminWithPermissions([
                'notifications.view',
                'notifications.create',
            ]);

        $titleCanary =
            'TITLE-CANARY-ADMIN15';

        $bodyCanary =
            'BODY-CANARY-ADMIN15';

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.notifications.store'
                ),
                $this->draftPayload([
                    'title' =>
                        $titleCanary,

                    'body' =>
                        $bodyCanary,
                ])
            )
            ->assertRedirect();

        $audit =
            AuditLog::query()
                ->where(
                    'action',
                    'ADMIN_NOTIFICATION_DRAFT_CREATED'
                )
                ->latest('id')
                ->firstOrFail();

        $json = json_encode(
            $audit->new_values,
            JSON_THROW_ON_ERROR
        );

        $this->assertStringNotContainsString(
            $titleCanary,
            $json
        );

        $this->assertStringNotContainsString(
            $bodyCanary,
            $json
        );
    }

    public function test_authorized_admin_can_update_an_undispatched_draft(): void
    {
        $admin =
            $this->adminWithPermissions([
                'notifications.view',
                'notifications.create',
            ]);

        $message =
            $this->draftMessage(
                $admin
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->patch(
                route(
                    'admin.notifications.update',
                    $message
                ),
                $this->draftPayload([
                    'title' =>
                        'Updated Draft Title',
                ])
            )
            ->assertRedirect();

        $this->assertSame(
            'Updated Draft Title',
            $message->fresh()->title
        );
    }

    public function test_published_notification_cannot_be_edited(): void
    {
        $admin =
            $this->adminWithPermissions([
                'notifications.view',
                'notifications.create',
            ]);

        $message =
            $this->draftMessage(
                $admin
            );

        $message->forceFill([
            'published_at' =>
                now(),
        ])->save();

        $this->actingAs(
            $admin,
            'admin'
        )
            ->patch(
                route(
                    'admin.notifications.update',
                    $message
                ),
                $this->draftPayload([
                    'title' =>
                        'Must Not Change',
                ])
            )
            ->assertStatus(409);

        $this->assertNotSame(
            'Must Not Change',
            $message->fresh()->title
        );
    }

    public function test_send_requires_view_and_send_permissions(): void
    {
        $admin =
            $this->adminWithPermissions([
                'notifications.view',
            ]);

        $message =
            $this->draftMessage(
                $admin
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.notifications.send',
                    $message
                ),
                []
            )
            ->assertForbidden();
    }

    public function test_all_audience_dispatch_creates_receipts_only_for_active_non_deleted_users(): void
    {
        $admin =
            $this->adminWithPermissions([
                'notifications.view',
                'notifications.send',
            ]);

        $activeFree =
            $this->user(
                'FREE',
                'ACTIVE'
            );

        $activePremium =
            $this->user(
                'PREMIUM',
                'ACTIVE'
            );

        $this->user(
            'FREE',
            'SUSPENDED'
        );

        $deleted =
            $this->user(
                'PREMIUM',
                'ACTIVE'
            );

        $deleted->delete();

        $message =
            $this->draftMessage(
                $admin,
                [
                    'audience_access_level' =>
                        NotificationMessage::AUDIENCE_ALL,
                ]
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.notifications.send',
                    $message
                ),
                []
            )
            ->assertRedirect();

        $message->refresh();

        $this->assertNotNull(
            $message->published_at
        );

        $this->assertEqualsCanonicalizing(
            [
                $activeFree->id,
                $activePremium->id,
            ],
            $message->receipts()
                ->pluck('user_id')
                ->all()
        );
    }

    public function test_free_audience_dispatch_targets_only_active_free_users(): void
    {
        $admin =
            $this->adminWithPermissions([
                'notifications.view',
                'notifications.send',
            ]);

        $free =
            $this->user(
                'FREE',
                'ACTIVE'
            );

        $this->user(
            'PREMIUM',
            'ACTIVE'
        );

        $message =
            $this->draftMessage(
                $admin,
                [
                    'audience_access_level' =>
                        NotificationMessage::AUDIENCE_FREE,
                ]
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.notifications.send',
                    $message
                ),
                []
            )
            ->assertRedirect();

        $this->assertSame(
            [$free->id],
            $message->receipts()
                ->pluck('user_id')
                ->all()
        );
    }

    public function test_premium_audience_dispatch_targets_only_active_premium_users(): void
    {
        $admin =
            $this->adminWithPermissions([
                'notifications.view',
                'notifications.send',
            ]);

        $this->user(
            'FREE',
            'ACTIVE'
        );

        $premium =
            $this->user(
                'PREMIUM',
                'ACTIVE'
            );

        $message =
            $this->draftMessage(
                $admin,
                [
                    'audience_access_level' =>
                        NotificationMessage::AUDIENCE_PREMIUM,
                ]
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.notifications.send',
                    $message
                ),
                []
            )
            ->assertRedirect();

        $this->assertSame(
            [$premium->id],
            $message->receipts()
                ->pluck('user_id')
                ->all()
        );
    }

    public function test_future_dispatch_creates_receipts_and_schedules_visibility(): void
    {
        $admin =
            $this->adminWithPermissions([
                'notifications.view',
                'notifications.send',
            ]);

        $this->user(
            'FREE',
            'ACTIVE'
        );

        $message =
            $this->draftMessage(
                $admin
            );

        $publishAt =
            now()
                ->addHour()
                ->format(
                    'Y-m-d H:i:s'
                );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.notifications.send',
                    $message
                ),
                [
                    'publish_at' =>
                        $publishAt,
                ]
            )
            ->assertRedirect();

        $message->refresh();

        $this->assertTrue(
            $message->published_at
                ->isFuture()
        );

        $this->assertSame(
            1,
            $message->receipts()
                ->count()
        );

        $audit =
            AuditLog::query()
                ->where(
                    'action',
                    'ADMIN_NOTIFICATION_BROADCAST_SCHEDULED'
                )
                ->first();

        $this->assertNotNull(
            $audit
        );
    }

    public function test_same_notification_cannot_be_dispatched_twice(): void
    {
        $admin =
            $this->adminWithPermissions([
                'notifications.view',
                'notifications.send',
            ]);

        $this->user(
            'FREE',
            'ACTIVE'
        );

        $message =
            $this->draftMessage(
                $admin
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.notifications.send',
                    $message
                ),
                []
            )
            ->assertRedirect();

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.notifications.send',
                    $message
                ),
                []
            )
            ->assertStatus(409);

        $this->assertSame(
            1,
            $message->receipts()
                ->count()
        );
    }

    public function test_dispatch_rejects_when_recipient_safety_ceiling_is_exceeded(): void
    {
        config()->set(
            'tradex_admin_notifications.max_recipients_per_broadcast',
            1
        );

        $admin =
            $this->adminWithPermissions([
                'notifications.view',
                'notifications.send',
            ]);

        $this->user(
            'FREE',
            'ACTIVE'
        );

        $this->user(
            'PREMIUM',
            'ACTIVE'
        );

        $message =
            $this->draftMessage(
                $admin
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.notifications.send',
                    $message
                ),
                []
            )
            ->assertStatus(409);

        $this->assertNull(
            $message->fresh()
                ->published_at
        );

        $this->assertSame(
            0,
            $message->receipts()
                ->count()
        );
    }

    public function test_resource_backed_action_requires_resource_uuid(): void
    {
        $admin =
            $this->adminWithPermissions([
                'notifications.view',
                'notifications.create',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.notifications.store'
                ),
                $this->draftPayload([
                    'action_type' =>
                        NotificationMessage::ACTION_TRADE_IDEA,

                    'action_resource_uuid' =>
                        null,
                ])
            )
            ->assertStatus(422);

        $this->assertDatabaseCount(
            'notification_messages',
            0
        );
    }

    public function test_non_resource_action_rejects_resource_uuid(): void
    {
        $admin =
            $this->adminWithPermissions([
                'notifications.view',
                'notifications.create',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.notifications.store'
                ),
                $this->draftPayload([
                    'action_type' =>
                        NotificationMessage::ACTION_DASHBOARD,

                    'action_resource_uuid' =>
                        (string) Str::uuid(),
                ])
            )
            ->assertStatus(422);

        $this->assertDatabaseCount(
            'notification_messages',
            0
        );
    }

    public function test_index_filters_by_metadata_and_status(): void
    {
        $admin =
            $this->adminWithPermissions([
                'notifications.view',
            ]);

        $this->draftMessage(
            $admin,
            [
                'title' =>
                    'FILTER-DRAFT-CANARY',

                'category' =>
                    NotificationMessage::CATEGORY_SECURITY,

                'priority' =>
                    NotificationMessage::PRIORITY_CRITICAL,
            ]
        );

        NotificationMessage::query()
            ->create([
                'audience_access_level' =>
                    NotificationMessage::AUDIENCE_ALL,

                'category' =>
                    NotificationMessage::CATEGORY_GENERAL,

                'priority' =>
                    NotificationMessage::PRIORITY_NORMAL,

                'title' =>
                    'FILTER-LIVE-CANARY',

                'body' =>
                    'Live notification',

                'action_type' =>
                    NotificationMessage::ACTION_NONE,

                'is_active' =>
                    true,

                'published_at' =>
                    now(),
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.notifications.index',
                    [
                        'status' =>
                            'DRAFT',

                        'category' =>
                            NotificationMessage::CATEGORY_SECURITY,

                        'priority' =>
                            NotificationMessage::PRIORITY_CRITICAL,
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                'FILTER-DRAFT-CANARY'
            )
            ->assertDontSee(
                'FILTER-LIVE-CANARY'
            );
    }

    public function test_detail_page_shows_aggregate_receipt_read_counts_without_user_list(): void
    {
        $admin =
            $this->adminWithPermissions([
                'notifications.view',
            ]);

        $user =
            $this->user(
                'FREE',
                'ACTIVE'
            );

        $message =
            $this->draftMessage(
                $admin
            );

        $receipt =
            $message->receipts()
                ->create([
                    'user_id' =>
                        $user->id,

                    'read_at' =>
                        now(),
                ]);

        $message->forceFill([
            'published_at' =>
                now(),
        ])->save();

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.notifications.show',
                    $message
                )
            )
            ->assertOk()
            ->assertSee(
                'Recipient Receipts'
            )
            ->assertSee(
                'Read'
            )
            ->assertDontSee(
                $user->email
            );
    }

    public function test_dispatched_message_is_immediately_available_through_frozen_api_service(): void
    {
        $admin =
            $this->adminWithPermissions([
                'notifications.view',
                'notifications.send',
            ]);

        $user =
            $this->user(
                'FREE',
                'ACTIVE'
            );

        $message =
            $this->draftMessage(
                $admin,
                [
                    'audience_access_level' =>
                        NotificationMessage::AUDIENCE_FREE,
                ]
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.notifications.send',
                    $message
                ),
                []
            )
            ->assertRedirect();

        $summary =
            app(
                NotificationApiService::class
            )->summary(
                $user->fresh()
            );

        $this->assertSame(
            1,
            $summary['total_available']
        );

        $this->assertSame(
            1,
            $summary['unread_count']
        );
    }

    public function test_notification_admin_pages_send_no_store_headers(): void
    {
        $admin =
            $this->adminWithPermissions([
                'notifications.view',
            ]);

        $response =
            $this->actingAs(
                $admin,
                'admin'
            )
                ->get(
                    route(
                        'admin.notifications.index'
                    )
                )
                ->assertOk()
                ->assertHeader(
                    'Referrer-Policy',
                    'no-referrer'
                )
                ->assertHeader(
                    'X-Content-Type-Options',
                    'nosniff'
                );

        $this->assertStringContainsString(
            'no-store',
            (string) $response
                ->headers
                ->get(
                    'Cache-Control'
                )
        );
    }

    public function test_admin_15_has_exactly_seven_routes_and_no_delete_route(): void
    {
        $routes = collect(
            app('router')
                ->getRoutes()
                ->getRoutes()
        )
            ->filter(
                fn ($route): bool =>
                    str_starts_with(
                        (string) $route->uri(),
                        'admin/notifications'
                    )
            );

        $this->assertCount(
            7,
            $routes
        );

        foreach ($routes as $route) {
            $this->assertNotContains(
                'DELETE',
                $route->methods()
            );
        }
    }

    private function draftPayload(
        array $overrides = []
    ): array {
        return array_merge(
            [
                'audience_access_level' =>
                    NotificationMessage::AUDIENCE_ALL,

                'category' =>
                    NotificationMessage::CATEGORY_GENERAL,

                'priority' =>
                    NotificationMessage::PRIORITY_NORMAL,

                'title' =>
                    'TradeX Notification Draft',

                'body' =>
                    'TradeX in-app notification body.',

                'action_type' =>
                    NotificationMessage::ACTION_NONE,

                'action_resource_uuid' =>
                    null,

                'expires_at' =>
                    null,
            ],
            $overrides
        );
    }

    private function draftMessage(
        AdminUser $admin,
        array $overrides = []
    ): NotificationMessage {
        return NotificationMessage::query()
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
                            'Draft Message '
                            .Str::random(8),

                        'body' =>
                            'Draft notification body.',

                        'action_type' =>
                            NotificationMessage::ACTION_NONE,

                        'action_resource_uuid' =>
                            null,

                        'is_active' =>
                            true,

                        'published_at' =>
                            null,

                        'expires_at' =>
                            null,

                        'created_by_admin_id' =>
                            $admin->id,
                    ],
                    $overrides
                )
            );
    }

    private function user(
        string $accountType,
        string $status
    ): User {
        return User::factory()
            ->create([
                'uuid' =>
                    (string) Str::uuid(),

                'referral_code' =>
                    Str::upper(
                        Str::random(10)
                    ),

                'country_code' =>
                    'LK',

                'language_code' =>
                    'en',

                'account_type' =>
                    $accountType,

                'status' =>
                    $status,

                'premium_activated_at' =>
                    $accountType === 'PREMIUM'
                        ? now()
                        : null,
            ]);
    }

    private function adminWithPermissions(
        array $codes
    ): AdminUser {
        $admin =
            AdminUser::query()
                ->create([
                    'uuid' =>
                        Str::uuid()
                            ->toString(),

                    'name' =>
                        'ADMIN-15 Test Actor',

                    'email' =>
                        'admin15-'
                        .Str::lower(
                            Str::random(10)
                        )
                        .'@example.test',

                    'email_verified_at' =>
                        now(),

                    'password' =>
                        'Testing-Admin-Pass-123!',

                    'is_active' =>
                        true,

                    'failed_login_attempts' =>
                        0,
                ]);

        $role =
            AdminRole::query()
                ->create([
                    'code' =>
                        'ADMIN15_TEST_'
                        .Str::upper(
                            Str::random(8)
                        ),

                    'name' =>
                        'ADMIN-15 Test Role',

                    'description' =>
                        null,

                    'is_system' =>
                        false,

                    'is_active' =>
                        true,
                ]);

        $permissionIds = [];

        foreach ($codes as $code) {
            $permission =
                AdminPermission::query()
                    ->where(
                        'code',
                        $code
                    )
                    ->firstOrFail();

            $permissionIds[] =
                $permission->id;
        }

        $role->permissions()->sync(
            $permissionIds
        );

        $admin->roles()->sync([
            $role->id,
        ]);

        return $admin->fresh(
            'roles'
        );
    }
}
