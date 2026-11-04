<?php

namespace Tests\Feature\Admin;

use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserActivityAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_admin_analytics_are_rejected(): void
    {
        $this->get(route('admin.activity.index'))->assertRedirect();
        $this->get(route('admin.activity.export'))->assertRedirect();
    }

    public function test_regular_admin_cannot_view_or_export_customer_activity(): void
    {
        $admin = $this->makeAdmin('ordinary');
        $this->actingAs($admin, 'admin')
            ->get(route('admin.activity.index'))
            ->assertForbidden();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.activity.export'))
            ->assertForbidden();
    }

    public function test_super_admin_sees_active_user_and_estimated_session_minutes(): void
    {
        $admin = $this->makeSuperAdmin();
        $user = User::factory()->create();
        $at = CarbonImmutable::today('UTC')->addHours(12);
        $session = (string) Str::uuid();

        $this->event($user, 'session_started', $at, 'app', $session);
        $this->event($user, 'session_heartbeat', $at->addMinutes(2), 'app', $session);
        $this->event($user, 'session_paused', $at->addMinutes(3), 'app', $session);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.activity.index'))
            ->assertOk()
            ->assertSee('User Activity Analytics')
            ->assertSee('Estimated Active Minutes')
            ->assertSee('3.0')
            ->assertSee($user->name)
            ->assertSee('Session Heartbeat');
    }

    public function test_timeline_filters_and_csv_do_not_leak_other_customer(): void
    {
        $admin = $this->makeSuperAdmin();
        $customerA = User::factory()->create(['name' => 'Report Alpha']);
        $customerB = User::factory()->create(['name' => 'Report Bravo']);
        $when = CarbonImmutable::today('UTC')->addHours(10);

        $this->event($customerA, 'lesson_viewed', $when, 'learning', 'session_a', 'lesson_one');
        $this->event($customerB, 'signal_viewed', $when, 'trade_ideas', 'session_b', 'idea_two');

        $filters = [
            'from' => $when->toDateString(),
            'to' => $when->toDateString(),
            'user_id' => $customerA->getKey(),
            'module' => 'learning',
            'event_name' => 'lesson_viewed',
        ];

        $this->actingAs($admin, 'admin')
            ->get(route('admin.activity.index', $filters))
            ->assertOk()
            ->assertSee('Report Alpha')
            ->assertDontSee('Report Bravo');

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.activity.export', $filters))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('lesson_one', $csv);
        $this->assertStringNotContainsString('idea_two', $csv);
        $this->assertStringNotContainsString('Report Bravo', $csv);
    }

    public function test_range_over_31_days_is_rejected(): void
    {
        $admin = $this->makeSuperAdmin();
        $today = CarbonImmutable::today('UTC');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.activity.index', [
                'from' => $today->subDays(32)->toDateString(),
                'to' => $today->toDateString(),
            ]))
            ->assertRedirect();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.activity.index', [
                'from' => $today->subDays(46)->toDateString(),
                'to' => $today->toDateString(),
            ]))
            ->assertRedirect();
    }

    private function makeSuperAdmin(): AdminUser
    {
        $admin = $this->makeAdmin('super');
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

    private function makeAdmin(string $label): AdminUser
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

    private function event(
        User $user,
        string $name,
        CarbonImmutable $when,
        string $module,
        string $session,
        ?string $subject = null
    ): void {
        DB::table('user_activity_events')->insert([
            'user_id' => $user->getKey(),
            'event_id' => (string) Str::uuid(),
            'module' => $module,
            'event_name' => $name,
            'session_id' => $session,
            'subject_id' => $subject,
            'occurred_at' => $when,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
