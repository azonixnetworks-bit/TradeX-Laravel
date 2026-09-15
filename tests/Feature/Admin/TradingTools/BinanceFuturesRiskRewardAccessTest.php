<?php

namespace Tests\Feature\Admin\TradingTools;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BinanceFuturesRiskRewardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_admin_can_calculate_binance_risk_based_position(): void
    {
        $admin = $this->createAdminWithPermission();

        $response = $this->actingAs($admin, 'admin')->postJson(
            route('admin.risk_reward.calculate'),
            [
                'market' => 'binance_futures',
                'symbol' => 'BTCUSDT',
                'side' => 'BUY',
                'entry' => 100000,
                'stop_loss' => 99000,
                'take_profit' => 102000,
                'calculation_mode' => 'RISK_BASED',
                'risk_amount' => 100,
                'leverage' => 10,
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.market', 'binance_futures')
            ->assertJsonPath('data.symbol', 'BTCUSDT')
            ->assertJsonPath('data.futures.calculation_mode', 'RISK_BASED')
            ->assertJsonPath('data.futures.leverage', 10)
            ->assertJsonPath('data.futures.position_quantity', 0.1)
            ->assertJsonPath('data.futures.position_notional', 10000)
            ->assertJsonPath('data.futures.required_initial_margin', 1000)
            ->assertJsonPath('data.actual_risk', 100)
            ->assertJsonPath('data.potential_profit', 200)
            ->assertJsonPath('data.specification.broker', 'Binance');
    }

    public function test_authorized_admin_can_calculate_binance_margin_based_position(): void
    {
        $admin = $this->createAdminWithPermission();

        $response = $this->actingAs($admin, 'admin')->postJson(
            route('admin.risk_reward.calculate'),
            [
                'market' => 'binance_futures',
                'symbol' => 'BTCUSDT',
                'side' => 'BUY',
                'entry' => 100000,
                'stop_loss' => 99000,
                'take_profit' => 102000,
                'calculation_mode' => 'MARGIN_BASED',
                'margin_amount' => 100,
                'leverage' => 20,
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.futures.calculation_mode', 'MARGIN_BASED')
            ->assertJsonPath('data.futures.leverage', 20)
            ->assertJsonPath('data.futures.position_quantity', 0.02)
            ->assertJsonPath('data.futures.position_notional', 2000)
            ->assertJsonPath('data.futures.required_initial_margin', 100)
            ->assertJsonPath('data.actual_risk', 20)
            ->assertJsonPath('data.potential_profit', 40);
    }

    private function createAdminWithPermission(): AdminUser
    {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Binance Futures Test Admin',
            'email' => 'binance-futures-'.Str::lower(Str::random(12)).'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        $role = AdminRole::query()->create([
            'code' => 'BINANCE_FUTURES_TEST_'.strtoupper(Str::random(8)),
            'name' => 'Binance Futures Test Role',
            'description' => 'Automated Binance Futures calculator role.',
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
}
