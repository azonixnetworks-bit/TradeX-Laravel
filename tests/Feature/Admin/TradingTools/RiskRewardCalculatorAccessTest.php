<?php

namespace Tests\Feature\Admin\TradingTools;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RiskRewardCalculatorAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_without_permission_cannot_open_calculator(): void
    {
        $admin = $this->createAdmin();

        $response = $this
            ->actingAs($admin, 'admin')
            ->get(route('admin.risk_reward.index'));

        $response->assertForbidden();
    }

    public function test_authorized_admin_can_open_calculator(): void
    {
        $admin = $this->createAdminWithRiskRewardPermission();

        $response = $this
            ->actingAs($admin, 'admin')
            ->get(route('admin.risk_reward.index'));

        $response->assertOk();
        $response->assertSee('Risk Reward Calculator');
        $response->assertSee('TRADING TOOLS');
        $response->assertSee('GBPJPY');
        $response->assertSee('USDTHB');

        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control')
        );
    }

    public function test_authorized_admin_can_calculate_position_size(): void
    {
        $admin = $this->createAdminWithRiskRewardPermission();

        $response = $this
            ->actingAs($admin, 'admin')
            ->postJson(
                route('admin.risk_reward.calculate'),
                [
                    'market' => 'forex',
                    'symbol' => 'EURUSD',
                    'side' => 'BUY',
                    'entry' => 1.10000,
                    'stop_loss' => 1.09500,
                    'take_profit' => 1.11000,
                    'risk_amount' => 100,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.symbol', 'EURUSD')
            ->assertJsonPath('data.side', 'BUY')
            ->assertJsonPath('data.recommended_lot', 0.2)
            ->assertJsonPath('data.specification.broker', 'IC Markets')
            ->assertJsonPath('data.specification.category', 'Major')
            ->assertJsonPath('data.specification.status', 'PARTIALLY VERIFIED');

        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control')
        );
    }

    public function test_authorized_admin_can_calculate_cross_pair_with_manual_conversion_rate(): void
    {
        $admin = $this->createAdminWithRiskRewardPermission();

        $response = $this
            ->actingAs($admin, 'admin')
            ->postJson(
                route('admin.risk_reward.calculate'),
                [
                    'market' => 'forex',
                    'symbol' => 'EURGBP',
                    'side' => 'BUY',
                    'entry' => 0.8500,
                    'stop_loss' => 0.8450,
                    'take_profit' => 0.8600,
                    'risk_amount' => 100,
                    'quote_to_usd_rate' => 1.30,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.symbol', 'EURGBP')
            ->assertJsonPath('data.recommended_lot', 0.15)
            ->assertJsonPath('data.quote_to_usd_conversion.source', 'MANUAL')
            ->assertJsonPath('data.specification.category', 'Minor');
    }

    public function test_invalid_symbol_is_rejected_server_side(): void
    {
        $admin = $this->createAdminWithRiskRewardPermission();

        $response = $this
            ->actingAs($admin, 'admin')
            ->postJson(
                route('admin.risk_reward.calculate'),
                [
                    'market' => 'forex',
                    'symbol' => 'NOT-A-REAL-SYMBOL',
                    'side' => 'BUY',
                    'entry' => 1.10000,
                    'stop_loss' => 1.09500,
                    'take_profit' => 1.11000,
                    'risk_amount' => 100,
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_admin_without_permission_cannot_call_calculation_endpoint(): void
    {
        $admin = $this->createAdmin();

        $response = $this
            ->actingAs($admin, 'admin')
            ->postJson(
                route('admin.risk_reward.calculate'),
                [
                    'market' => 'forex',
                    'symbol' => 'EURUSD',
                    'side' => 'BUY',
                    'entry' => 1.10000,
                    'stop_loss' => 1.09500,
                    'take_profit' => 1.11000,
                    'risk_amount' => 100,
                ]
            );

        $response->assertForbidden();
    }

    private function createAdminWithRiskRewardPermission(): AdminUser
    {
        $admin = $this->createAdmin();

        $role = AdminRole::query()->create([
            'code' => 'RISK_REWARD_TEST_'.strtoupper(Str::random(8)),
            'name' => 'Risk Reward Test Role',
            'description' => 'Automated calculator RBAC test role.',
            'is_system' => false,
            'is_active' => true,
        ]);

        $permission = AdminPermission::query()->create([
            'code' => 'risk_reward.view',
            'name' => 'View Risk Reward Calculator',
            'module' => 'TRADING_TOOLS',
            'description' => 'Automated calculator permission fixture.',
        ]);

        $role->permissions()->attach($permission->id);
        $admin->roles()->attach($role->id);

        return $admin->fresh();
    }

    private function createAdmin(): AdminUser
    {
        return AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Risk Reward Test Admin',
            'email' => 'risk-reward-'.Str::lower(Str::random(12)).'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);
    }
}
