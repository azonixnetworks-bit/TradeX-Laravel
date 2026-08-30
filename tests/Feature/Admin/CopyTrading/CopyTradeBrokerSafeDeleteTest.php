<?php

namespace Tests\Feature\Admin\CopyTrading;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\CopyTradeBroker;
use App\Models\CopyTradeBrokerAccount;
use App\Models\CopyTradeBrokerMarket;
use App\Models\CopyTradeOnboardingIntent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class CopyTradeBrokerSafeDeleteTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_unused_broker_can_be_deleted_with_markets_logo_and_audit_event(): void
    {
        Storage::fake('private');

        $admin = $this->adminWithPermissions([
            'copy_trading.view',
            'copy_trading.brokers.manage',
        ]);

        [$broker, $market] = $this->brokerWithMarket();
        $brokerId = $broker->id;
        $marketId = $market->id;
        $logoPath = 'copy-trading/brokers/'.$broker->uuid.'/logo-delete-test.png';

        Storage::disk('private')->put($logoPath, 'broker-logo');

        $broker->update([
            'logo_disk' => 'private',
            'logo_path' => $logoPath,
            'logo_mime_type' => 'image/png',
            'logo_size_bytes' => 11,
        ]);

        $this->actingAs($admin, 'admin')
            ->delete(
                route(
                    'admin.copy_trading.brokers.destroy',
                    ['copyTradeBroker' => $broker]
                )
            )
            ->assertRedirect(
                route('admin.copy_trading.brokers.index')
            )
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('copy_trade_brokers', [
            'id' => $brokerId,
        ]);

        $this->assertDatabaseMissing('copy_trade_broker_markets', [
            'id' => $marketId,
        ]);

        Storage::disk('private')->assertMissing($logoPath);

        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'ADMIN',
            'actor_id' => $admin->id,
            'action' => 'COPY_TRADE_BROKER_DELETED',
            'module' => 'COPY_TRADING',
            'target_type' => 'COPY_TRADE_BROKER',
            'target_id' => $brokerId,
        ]);
    }

    public function test_broker_with_onboarding_intent_history_cannot_be_deleted(): void
    {
        $admin = $this->adminWithPermissions([
            'copy_trading.view',
            'copy_trading.brokers.manage',
        ]);

        [$broker, $market] = $this->brokerWithMarket();
        $user = $this->createApiUser();

        CopyTradeOnboardingIntent::query()->create([
            'user_id' => $user->id,
            'broker_market_id' => $market->id,
            'referral_url_snapshot' => $market->referral_url,
            'opened_at' => now(),
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.copy_trading.brokers.index'))
            ->delete(
                route(
                    'admin.copy_trading.brokers.destroy',
                    ['copyTradeBroker' => $broker]
                )
            )
            ->assertRedirect(
                route('admin.copy_trading.brokers.index')
            )
            ->assertSessionHas(
                'warning',
                fn (string $message): bool => str_contains(
                    $message,
                    'cannot be deleted'
                )
            );

        $this->assertDatabaseHas('copy_trade_brokers', [
            'id' => $broker->id,
        ]);

        $this->assertDatabaseHas('copy_trade_broker_markets', [
            'id' => $market->id,
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'COPY_TRADE_BROKER_DELETED',
            'target_id' => $broker->id,
        ]);
    }

    public function test_broker_with_account_history_cannot_be_deleted(): void
    {
        $admin = $this->adminWithPermissions([
            'copy_trading.view',
            'copy_trading.brokers.manage',
        ]);

        [$broker, $market] = $this->brokerWithMarket();
        $user = $this->createApiUser();

        CopyTradeBrokerAccount::query()->create([
            'user_id' => $user->id,
            'broker_id' => $broker->id,
            'broker_market_id' => $market->id,
            'account_number' => '123456789',
            'account_number_fingerprint' => hash(
                'sha256',
                'broker-delete-account-'.Str::uuid()
            ),
            'account_number_last4' => '6789',
            'status' => CopyTradeBrokerAccount::STATUS_PENDING,
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.copy_trading.brokers.index'))
            ->delete(
                route(
                    'admin.copy_trading.brokers.destroy',
                    ['copyTradeBroker' => $broker]
                )
            )
            ->assertRedirect(
                route('admin.copy_trading.brokers.index')
            )
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('copy_trade_brokers', [
            'id' => $broker->id,
        ]);

        $this->assertDatabaseHas('copy_trade_broker_accounts', [
            'broker_id' => $broker->id,
            'broker_market_id' => $market->id,
        ]);
    }

    public function test_manage_permission_is_required_to_delete_broker(): void
    {
        $admin = $this->adminWithPermissions([
            'copy_trading.view',
        ]);

        [$broker] = $this->brokerWithMarket();

        $this->actingAs($admin, 'admin')
            ->delete(
                route(
                    'admin.copy_trading.brokers.destroy',
                    ['copyTradeBroker' => $broker]
                )
            )
            ->assertForbidden();

        $this->assertDatabaseHas('copy_trade_brokers', [
            'id' => $broker->id,
        ]);
    }

    public function test_broker_index_shows_delete_only_when_history_is_clear(): void
    {
        $admin = $this->adminWithPermissions([
            'copy_trading.view',
            'copy_trading.brokers.manage',
        ]);

        [$unusedBroker] = $this->brokerWithMarket([
            'name' => 'Unused Delete Broker',
        ]);

        [$historyBroker, $historyMarket] = $this->brokerWithMarket([
            'name' => 'History Locked Broker',
        ]);

        $user = $this->createApiUser();

        CopyTradeOnboardingIntent::query()->create([
            'user_id' => $user->id,
            'broker_market_id' => $historyMarket->id,
            'referral_url_snapshot' => $historyMarket->referral_url,
            'opened_at' => now(),
            'expires_at' => now()->addHour(),
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.copy_trading.brokers.index'))
            ->assertOk()
            ->assertSee('Unused Delete Broker')
            ->assertSee('History Locked Broker')
            ->assertSee('Delete locked')
            ->assertSee('History preserved · deactivate instead');

        $unusedDeleteAction = 'action="'.route(
            'admin.copy_trading.brokers.destroy',
            ['copyTradeBroker' => $unusedBroker]
        ).'"';

        $historyDeleteAction = 'action="'.route(
            'admin.copy_trading.brokers.destroy',
            ['copyTradeBroker' => $historyBroker]
        ).'"';

        $response->assertSee($unusedDeleteAction, false);
        $response->assertDontSee($historyDeleteAction, false);
    }

    private function brokerWithMarket(
        array $brokerOverrides = []
    ): array {
        $broker = CopyTradeBroker::query()->create(array_merge([
            'code' => 'DELETE_'.Str::upper(Str::random(10)),
            'name' => 'Broker '.Str::upper(Str::random(6)),
            'is_active' => true,
        ], $brokerOverrides));

        $market = CopyTradeBrokerMarket::query()->create([
            'broker_id' => $broker->id,
            'market_category' => 'FOREX',
            'referral_url' => 'https://broker.example.test/tradex-partner',
            'required_currency' => 'USD',
            'live_account_only' => true,
            'is_active' => true,
        ]);

        return [$broker->fresh(), $market->fresh()];
    }

    private function adminWithPermissions(
        array $codes
    ): AdminUser {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Broker Delete Test Admin',
            'email' => 'broker-delete-'
                .Str::lower(Str::random(12))
                .'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
        ]);

        $role = AdminRole::query()->create([
            'code' => 'BROKER_DELETE_'.Str::upper(Str::random(8)),
            'name' => 'Broker Delete Test Role',
            'description' => 'ADMIN-22 safe delete test role.',
            'is_system' => false,
            'is_active' => true,
        ]);

        $permissionIds = [];

        foreach ($codes as $code) {
            $permission = AdminPermission::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $code,
                    'module' => 'COPY_TRADING',
                    'description' => null,
                ]
            );

            $permissionIds[] = $permission->id;
        }

        $role->permissions()->sync($permissionIds);
        $admin->roles()->sync([$role->id]);

        return $admin->fresh('roles');
    }
}
