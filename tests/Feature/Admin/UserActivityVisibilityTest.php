<?php

namespace Tests\Feature\Admin;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserActivityVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_only_the_selected_users_activity(): void
    {
        $admin = $this->superAdmin();
        $selectedUser = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->storeActivity($selectedUser, 'selected-user-activity');
        $this->storeActivity($otherUser, 'other-user-private-activity');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.users.show', $selectedUser))
            ->assertOk()
            ->assertSee('User Activity')
            ->assertSee('Selected User Activity')
            ->assertDontSee('Other User Private Activity');
    }

    public function test_non_super_admin_cannot_see_the_activity_panel(): void
    {
        $admin = $this->adminUser('activity-viewer');
        $user = User::factory()->create();

        $role = AdminRole::query()->create([
            'code' => 'ACTIVITY_TEST_VIEWER',
            'name' => 'Activity Test Viewer',
            'is_system' => false,
            'is_active' => true,
        ]);
        $permission = AdminPermission::query()->firstOrCreate(
            ['code' => 'users.view_details'],
            [
                'name' => 'View User Details',
                'module' => 'USERS',
            ]
        );
        $role->permissions()->sync([$permission->id]);
        $admin->roles()->sync([$role->id]);
        $this->storeActivity($user, 'hidden-admin-activity');

        $this->actingAs($admin->fresh('roles'), 'admin')
            ->get(route('admin.users.show', $user))
            ->assertOk()
            ->assertDontSee('SUPER ADMIN AUDIT')
            ->assertDontSee('Hidden Admin Activity');
    }

    public function test_activity_older_than_45_days_is_hidden_and_pruned(): void
    {
        $admin = $this->superAdmin();
        $user = User::factory()->create();

        $this->storeActivity($user, 'retained-activity', now()->subDays(44));
        $this->storeActivity($user, 'expired-activity', now()->subDays(46));

        $this->actingAs($admin, 'admin')
            ->get(route('admin.users.show', $user))
            ->assertOk()
            ->assertSee('Retained Activity')
            ->assertDontSee('Expired Activity');

        $this->artisan('tradex:user-activity:prune')
            ->expectsOutput('Pruned 1 user activity event(s).')
            ->assertSuccessful();

        $this->assertDatabaseCount('user_activity_events', 1);
    }

    private function superAdmin(): AdminUser
    {
        $admin = $this->adminUser('super-admin');

        $role = AdminRole::query()->firstOrCreate(
            ['code' => 'SUPER_ADMIN'],
            [
                'name' => 'Super Administrator',
                'is_system' => true,
                'is_active' => true,
            ]
        );
        $admin->roles()->sync([$role->id]);

        return $admin->fresh('roles');
    }

    private function adminUser(string $label): AdminUser
    {
        return AdminUser::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Activity '.$label,
            'email' => $label.'-'.Str::lower(Str::random(8)).'@example.test',
            'email_verified_at' => now(),
            'password' => 'Testing-Admin-Pass-123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
        ]);
    }

    private function storeActivity(
        User $user,
        string $eventName,
        mixed $occurredAt = null
    ): void {
        $occurredAt ??= now();

        DB::table('user_activity_events')->insert([
            'user_id' => $user->id,
            'event_id' => (string) Str::uuid(),
            'module' => 'learn',
            'event_name' => $eventName,
            'session_id' => 'session-'.$user->id,
            'subject_id' => 'subject-'.$user->id,
            'occurred_at' => $occurredAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
