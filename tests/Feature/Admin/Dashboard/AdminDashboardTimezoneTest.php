<?php

namespace Tests\Feature\Admin\Dashboard;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDashboardTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_persisted_utc_timestamps_in_the_configured_iana_timezone(): void
    {
        config()->set(
            'tradex_reporting.audit_timezone',
            'Asia/Colombo'
        );

        CarbonImmutable::setTestNow(
            CarbonImmutable::parse(
                '2026-09-06 18:40:55',
                'UTC'
            )
        );

        try {
            $admin = $this->adminWithPermissions([
                'dashboard.view',
                'audit.view',
            ]);

            $admin->forceFill([
                'last_login_at' => CarbonImmutable::parse(
                    '2026-09-06 18:40:55',
                    'UTC'
                ),
                'last_login_ip' => '127.0.0.1',
            ])->save();

            AuditLog::query()->create([
                'actor_type' => 'ADMIN',
                'actor_id' => $admin->id,
                'action' => 'ADMIN_LOGIN',
                'module' => 'AUTH',
                'ip_address' => '127.0.0.1',
                'created_at' => CarbonImmutable::parse(
                    '2026-09-06 18:40:55',
                    'UTC'
                ),
            ]);

            AuditLog::query()->create([
                'actor_type' => 'SYSTEM',
                'actor_id' => null,
                'action' => 'PREVIOUS_LOCAL_DAY_EVENT',
                'module' => 'SYSTEM',
                'created_at' => CarbonImmutable::parse(
                    '2026-09-06 18:29:59',
                    'UTC'
                ),
            ]);

            $response = $this->actingAs(
                $admin->fresh(),
                'admin'
            )->get(route('admin.dashboard', [
                'activity' => 'audit',
                'per_page' => 10,
            ]));

            $response
                ->assertOk()
                ->assertSeeText('Asia/Colombo')
                ->assertSeeText('07 Sep 2026 00:10')
                ->assertSeeText('07 Sep 2026 00:10:55')
                ->assertSeeText('06 Sep 2026 23:59:59')
                ->assertDontSeeText('06 Sep 2026 18:40:55');

            $this->assertSame(
                1,
                $response->viewData('stats')['audit_events_today']
            );
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_invalid_admin_timezone_falls_back_to_utc_without_breaking_dashboard(): void
    {
        config()->set(
            'tradex_reporting.audit_timezone',
            'UTC+05:30'
        );

        CarbonImmutable::setTestNow(
            CarbonImmutable::parse(
                '2026-09-06 18:40:55',
                'UTC'
            )
        );

        try {
            $admin = $this->adminWithPermissions([
                'dashboard.view',
            ]);

            $response = $this->actingAs(
                $admin,
                'admin'
            )->get(route('admin.dashboard'));

            $response
                ->assertOk()
                ->assertSeeText('UTC');

            $this->assertSame(
                'UTC',
                $response->viewData('systemTimezone')
            );
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    private function adminWithPermissions(array $codes): AdminUser
    {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Dashboard Timezone Admin',
            'email' => 'dashboard-timezone-'.Str::lower(Str::random(10)).'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        $role = AdminRole::query()->create([
            'code' => 'DASH_TZ_'.strtoupper(Str::random(8)),
            'name' => 'Dashboard Timezone Role',
            'is_system' => false,
            'is_active' => true,
        ]);

        $permissionIds = [];

        foreach ($codes as $code) {
            $permission = AdminPermission::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $code,
                    'module' => 'DASHBOARD',
                ]
            );

            $permissionIds[] = $permission->id;
        }

        $role->permissions()->sync($permissionIds);
        $admin->roles()->sync([$role->id]);

        return $admin->fresh();
    }
}
