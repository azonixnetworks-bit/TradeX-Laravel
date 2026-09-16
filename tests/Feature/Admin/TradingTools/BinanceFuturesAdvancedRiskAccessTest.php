<?php

namespace Tests\Feature\Admin\TradingTools;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BinanceFuturesAdvancedRiskAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_admin_can_request_cost_adjusted_risk_and_isolated_liquidation(): void
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
                'margin_mode' => 'ISOLATED',
                'risk_amount' => 100,
                'leverage' => 10,
                'entry_fee_rate_percent' => 0.05,
                'exit_fee_rate_percent' => 0.05,
                'slippage_percent' => 0.10,
                'funding_rate_percent' => 0.01,
                'funding_intervals' => 2,
                'maintenance_margin_rate_percent' => 0.4,
                'maintenance_amount' => 0,
            ]
        );

        $response->assertOk()->assertJsonPath('success', true);

        $data = $response->json('data');

        $this->assertSame('ISOLATED', $data['futures']['margin_mode']);
        $this->assertEqualsWithDelta(100.00, $data['futures']['all_in_stop_risk'], 0.01);
        $this->assertEqualsWithDelta(145.8350, $data['futures']['net_target_profit'], 0.01);
        $this->assertEqualsWithDelta(0.05, $data['futures']['costs']['entry_fee_rate_percent'], 0.000001);
        $this->assertTrue($data['futures']['liquidation']['available']);
        $this->assertTrue($data['futures']['liquidation']['stop_before_liquidation']);
    }

    public function test_maintenance_margin_inputs_must_be_supplied_as_a_pair(): void
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
                'maintenance_margin_rate_percent' => 0.4,
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['maintenance_amount']);
    }

    private function createAdminWithPermission(): AdminUser
    {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Binance Advanced Risk Test Admin',
            'email' => 'binance-advanced-'.Str::lower(Str::random(12)).'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        $role = AdminRole::query()->create([
            'code' => 'BINANCE_ADVANCED_TEST_'.strtoupper(Str::random(8)),
            'name' => 'Binance Advanced Risk Test Role',
            'description' => 'Automated Binance advanced risk calculator role.',
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
