<?php

namespace Tests\Feature\Admin\OurChannel;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\ChannelVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChannelVideoManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_our_channel_admin(): void
    {
        $this->get(
            route(
                'admin.our_channel.index'
            )
        )->assertRedirect(
            route(
                'admin.login'
            )
        );
    }

    public function test_view_permission_is_required_for_index(): void
    {
        $admin = $this->createAdminWithPermissions(
            []
        );

        $this->actingAs(
            $admin,
            'admin'
        )->get(
            route(
                'admin.our_channel.index'
            )
        )->assertForbidden();
    }

    public function test_authorized_admin_can_create_canonical_draft_youtube_video_with_audit_log(): void
    {
        $admin = $this->createAdminWithPermissions([
            'our_channel.view',
            'our_channel.manage',
        ]);

        $response = $this->actingAs(
            $admin,
            'admin'
        )->post(
            route(
                'admin.our_channel.store'
            ),
            [
                'title' => 'TradeX Free Learning Video',
                'short_description' => 'Free education.',
                'description' => 'Long description.',
                'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ?si=test',
                'intent' => 'DRAFT',
                'language_code' => 'TA',
                'is_featured' => '1',
                'sort_order' => '5',
            ]
        );

        $response->assertRedirect(
            route(
                'admin.our_channel.index'
            )
        );

        $video = ChannelVideo::query()
            ->firstOrFail();

        $this->assertSame(
            'dQw4w9WgXcQ',
            $video->youtube_video_id
        );

        $this->assertSame(
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            $video->youtube_url
        );

        $this->assertSame(
            'ta',
            $video->language_code
        );

        $this->assertSame(
            ChannelVideo::STATUS_DRAFT,
            $video->status
        );

        $this->assertNull(
            $video->published_at
        );

        $this->assertTrue(
            $video->is_featured
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' => 'CHANNEL_VIDEO_CREATED',
                'module' => 'OUR_CHANNEL',
                'target_type' => 'CHANNEL_VIDEO',
                'target_id' => $video->id,
            ]
        );
    }

    public function test_untrusted_youtube_lookalike_host_is_rejected(): void
    {
        $admin = $this->createAdminWithPermissions([
            'our_channel.view',
            'our_channel.manage',
        ]);

        $this->actingAs(
            $admin,
            'admin'
        )->from(
            route(
                'admin.our_channel.create'
            )
        )->post(
            route(
                'admin.our_channel.store'
            ),
            [
                'title' => 'Bad Video',
                'youtube_url' => 'https://youtube.com.evil.example/watch?v=dQw4w9WgXcQ',
                'language_code' => 'en',
                'sort_order' => 0,
            ]
        )
            ->assertRedirect(
                route(
                    'admin.our_channel.create'
                )
            )
            ->assertSessionHasErrors(
                'youtube_url'
            );

        $this->assertDatabaseCount(
            'channel_videos',
            0
        );
    }

    public function test_duplicate_youtube_video_is_rejected_even_with_different_url_shape(): void
    {
        $admin = $this->createAdminWithPermissions([
            'our_channel.view',
            'our_channel.manage',
        ]);

        $this->actingAs(
            $admin,
            'admin'
        )->post(
            route(
                'admin.our_channel.store'
            ),
            [
                'title' => 'First',
                'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'intent' => 'DRAFT',
                'language_code' => 'en',
                'sort_order' => 1,
            ]
        )->assertRedirect(
            route(
                'admin.our_channel.index'
            )
        );

        $this->actingAs(
            $admin,
            'admin'
        )->from(
            route(
                'admin.our_channel.create'
            )
        )->post(
            route(
                'admin.our_channel.store'
            ),
            [
                'title' => 'Duplicate',
                'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ',
                'intent' => 'DRAFT',
                'language_code' => 'en',
                'sort_order' => 2,
            ]
        )
            ->assertRedirect(
                route(
                    'admin.our_channel.create'
                )
            )
            ->assertSessionHasErrors(
                'youtube_url'
            );

        $this->assertDatabaseCount(
            'channel_videos',
            1
        );
    }

    public function test_manage_permission_is_required_for_create(): void
    {
        $admin = $this->createAdminWithPermissions([
            'our_channel.view',
        ]);

        $this->actingAs(
            $admin,
            'admin'
        )->post(
            route(
                'admin.our_channel.store'
            ),
            [
                'title' => 'No Manage Permission',
                'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ',
                'language_code' => 'en',
                'sort_order' => 0,
            ]
        )->assertForbidden();

        $this->assertDatabaseCount(
            'channel_videos',
            0
        );
    }

    public function test_manage_admin_can_publish_and_unpublish_video(): void
    {
        $admin = $this->createAdminWithPermissions([
            'our_channel.view',
            'our_channel.manage',
        ]);

        $video = $this->createDraftVideo(
            $admin
        );

        $this->actingAs(
            $admin,
            'admin'
        )->patch(
            route(
                'admin.our_channel.publication',
                [
                    'channelVideo' => $video,
                ]
            ),
            [
                'action' => 'PUBLISH',
                'reason' => 'Approved for free learners.',
            ]
        )->assertRedirect(
            route(
                'admin.our_channel.index'
            )
        );

        $video->refresh();

        $this->assertSame(
            ChannelVideo::STATUS_PUBLISHED,
            $video->status
        );

        $this->assertNotNull(
            $video->published_at
        );

        $this->actingAs(
            $admin,
            'admin'
        )->patch(
            route(
                'admin.our_channel.publication',
                [
                    'channelVideo' => $video,
                ]
            ),
            [
                'action' => 'UNPUBLISH',
            ]
        )->assertRedirect(
            route(
                'admin.our_channel.index'
            )
        );

        $video->refresh();

        $this->assertSame(
            ChannelVideo::STATUS_DRAFT,
            $video->status
        );

        $this->assertNull(
            $video->published_at
        );
    }

    public function test_published_video_must_be_unpublished_before_delete(): void
    {
        $admin = $this->createAdminWithPermissions([
            'our_channel.view',
            'our_channel.manage',
        ]);

        $video = $this->createDraftVideo(
            $admin
        );

        $video->forceFill([
            'status' => ChannelVideo::STATUS_PUBLISHED,
            'published_at' => now(),
        ])->save();

        $this->actingAs(
            $admin,
            'admin'
        )->delete(
            route(
                'admin.our_channel.destroy',
                [
                    'channelVideo' => $video,
                ]
            )
        )->assertStatus(
            422
        );

        $this->assertNull(
            $video->fresh()->deleted_at
        );
    }

    private function createAdminWithPermissions(
        array $permissionCodes
    ): AdminUser {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'ADMIN-04 Channel Admin',
            'email' => 'admin04-'.Str::lower(Str::random(12)).'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        $role = AdminRole::query()->create([
            'code' => 'ADMIN04_'.strtoupper(
                Str::random(8)
            ),
            'name' => 'ADMIN-04 Channel Role',
            'is_system' => false,
            'is_active' => true,
        ]);

        $admin->roles()->attach(
            $role->id
        );

        foreach ($permissionCodes as $code) {
            $permission = AdminPermission::query()
                ->firstOrCreate(
                    [
                        'code' => $code,
                    ],
                    [
                        'name' => $code,
                        'module' => 'OUR_CHANNEL',
                    ]
                );

            $role->permissions()->attach(
                $permission->id
            );
        }

        return $admin->fresh();
    }

    private function createDraftVideo(
        AdminUser $admin
    ): ChannelVideo {
        return ChannelVideo::query()->create([
            'title' => 'Existing Channel Video',
            'youtube_video_id' => 'dQw4w9WgXcQ',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'language_code' => 'en',
            'status' => ChannelVideo::STATUS_DRAFT,
            'published_at' => null,
            'is_featured' => false,
            'sort_order' => 1,
            'created_by_admin_id' => $admin->id,
            'updated_by_admin_id' => $admin->id,
        ]);
    }
}
