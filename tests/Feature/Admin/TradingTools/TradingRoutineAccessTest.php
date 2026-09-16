<?php

namespace Tests\Feature\Admin\TradingTools;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TradingRoutineAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_without_permission_cannot_open_trading_routine_monitor(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.trading_routine.index'))
            ->assertForbidden();
    }

    public function test_authorized_admin_can_open_monitor_without_customer_edit_form(): void
    {
        $admin = $this->createAdmin();
        $role = AdminRole::query()->create([
            'code' => 'TRADING_ROUTINE_TEST_'.strtoupper(Str::random(8)),
            'name' => 'Trading Routine Test Role',
            'description' => 'Automated routine monitor role.',
            'is_system' => false,
            'is_active' => true,
        ]);
        $permission = AdminPermission::query()->create([
            'code' => 'trading_routine.view',
            'name' => 'View Trading Routine Planner',
            'module' => 'TRADING_TOOLS',
            'description' => null,
        ]);
        $role->permissions()->attach($permission->id);
        $admin->roles()->attach($role->id);

        $response = $this->actingAs($admin->fresh(), 'admin')
            ->get(route('admin.trading_routine.index'));

        $response->assertOk();
        $response->assertSee('Daily Trading');
        $response->assertSee('Customer-owned configuration');
        $response->assertDontSee('name="planning_balance"', false);
        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control')
        );
    }

    private function createAdmin(): AdminUser
    {
        return AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Trading Routine Admin',
            'email' => 'trading-routine-'.Str::lower(Str::random(12)).'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);
    }
}
