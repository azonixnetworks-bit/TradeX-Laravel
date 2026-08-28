<?php

namespace Tests\Feature\Admin\CopyTrading;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\CopyTradeBroker;
use App\Models\CopyTradeBrokerAccount;
use App\Models\CopyTradeBrokerMarket;
use App\Models\User;
use App\Services\Admin\CopyTrading\CopyTradeVerificationService;
use App\Services\CopyTrading\CopyTradeOnboardingService;
use App\Services\CopyTrading\CopyTradePresenter;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class CopyTradingBrokerOnboardingTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'tradex_copy_trading.fingerprint_key',
            'copy-trading-test-fingerprint-key'
        );
        config()->set('tradex_copy_trading.max_account_number_digits', 15);
        config()->set('tradex_copy_trading.max_accounts_per_market', 5);
        config()->set('tradex_copy_trading.max_resubmissions', 3);
    }

    public function test_partner_action_is_required_and_submitted_account_number_is_encrypted_and_masked(): void
    {
        [$broker, $market] = $this->brokerMarket();
        $user = $this->createApiUser();
        $service = app(CopyTradeOnboardingService::class);
        $request = Request::create('/copy-trade', 'POST');

        $intent = $service->openAccountAction($user, $market, $request);

        $this->assertSame(
            'https://broker.example.test/tradex-partner',
            $intent->referral_url_snapshot
        );
        $this->assertNull($intent->consumed_at);

        $account = $service->submitBrokerAccount(
            $user,
            $intent,
            '123456789',
            $request
        );

        $this->assertSame(CopyTradeBrokerAccount::STATUS_PENDING, $account->status);
        $this->assertSame('••••6789', $account->maskedAccountNumber());
        $this->assertSame($broker->id, $account->broker_id);
        $this->assertSame($market->id, $account->broker_market_id);
        $this->assertNotNull($intent->fresh()->consumed_at);

        $rawAccountNumber = DB::table('copy_trade_broker_accounts')
            ->where('id', $account->id)
            ->value('account_number');

        $this->assertNotSame('123456789', $rawAccountNumber);
        $this->assertStringNotContainsString(
            '123456789',
            (string) $rawAccountNumber
        );
        $this->assertSame(64, strlen($account->account_number_fingerprint));

        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'USER',
            'actor_id' => $user->id,
            'action' => 'COPY_TRADE_PARTNER_LINK_OPENED',
            'module' => 'COPY_TRADING',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'USER',
            'actor_id' => $user->id,
            'action' => 'COPY_TRADE_BROKER_ACCOUNT_SUBMITTED',
            'module' => 'COPY_TRADING',
            'target_id' => $account->id,
        ]);
    }

    public function test_same_broker_and_account_number_cannot_be_registered_twice(): void
    {
        [, $market] = $this->brokerMarket();
        $service = app(CopyTradeOnboardingService::class);
        $request = Request::create('/copy-trade', 'POST');

        $firstUser = $this->createApiUser();
        $firstIntent = $service->openAccountAction($firstUser, $market, $request);
        $service->submitBrokerAccount(
            $firstUser,
            $firstIntent,
            '9988776655',
            $request
        );

        $secondUser = $this->createApiUser();
        $secondIntent = $service->openAccountAction($secondUser, $market, $request);

        try {
            $service->submitBrokerAccount(
                $secondUser,
                $secondIntent,
                '9988776655',
                $request
            );
            $this->fail('Duplicate broker account was accepted.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString(
                'already registered',
                $exception->getMessage()
            );
        }

        $this->assertSame(
            1,
            CopyTradeBrokerAccount::query()->count()
        );
    }

    public function test_account_number_rejects_non_digits_and_more_than_fifteen_digits(): void
    {
        [, $market] = $this->brokerMarket();
        $service = app(CopyTradeOnboardingService::class);
        $request = Request::create('/copy-trade', 'POST');
        $user = $this->createApiUser();

        $intent = $service->openAccountAction($user, $market, $request);

        foreach (['123ABC456', '1234567890123456'] as $invalid) {
            $freshIntent = $intent;

            if ($intent->consumed_at !== null) {
                $freshIntent = $service->openAccountAction($user, $market, $request);
            }

            try {
                $service->submitBrokerAccount(
                    $user,
                    $freshIntent,
                    $invalid,
                    $request
                );
                $this->fail('Invalid broker account number was accepted: '.$invalid);
            } catch (DomainException $exception) {
                $this->assertStringContainsString(
                    'digits only',
                    $exception->getMessage()
                );
            }
        }

        $this->assertSame(0, CopyTradeBrokerAccount::query()->count());
    }

    public function test_verification_sets_verified_but_never_auto_activates_copy_trading(): void
    {
        $account = $this->pendingAccount('555666777');
        $admin = $this->adminWithPermissions([
            'copy_trading.view',
            'copy_trading.accounts.verify',
        ]);

        $service = app(CopyTradeVerificationService::class);
        $verified = $service->verify(
            $account,
            $admin,
            [
                'partner_attribution_confirmed' => true,
                'live_account_confirmed' => true,
                'verified_currency' => 'USD',
                'portal_reference' => 'partner-portal-case-1001',
                'reason' => 'Internal verification note must not leak to customer API.',
            ],
            null,
            Request::create('/admin/copy-trading/verify', 'PATCH')
        );

        $this->assertSame(CopyTradeBrokerAccount::STATUS_VERIFIED, $verified->status);
        $this->assertNotNull($verified->verified_at);
        $this->assertNull($verified->activated_at);
        $this->assertFalse($verified->isActive());

        $presented = app(CopyTradePresenter::class)->account($verified);

        $this->assertSame('VERIFIED_PENDING_GATES', $presented['activation_state']);
        $this->assertFalse($presented['copy_trade_ready']);
        $this->assertNull($presented['status_reason']);

        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'ADMIN',
            'actor_id' => $admin->id,
            'action' => 'COPY_TRADE_BROKER_ACCOUNT_VERIFIED',
            'module' => 'COPY_TRADING',
            'target_id' => $verified->id,
        ]);
    }

    public function test_resubmission_reason_is_customer_visible_but_internal_verification_notes_are_not(): void
    {
        $account = $this->pendingAccount('777888999');
        $admin = $this->adminWithPermissions([
            'copy_trading.view',
            'copy_trading.accounts.request_resubmission',
        ]);

        $reason = 'Please correct the broker account number and resubmit.';

        $updated = app(CopyTradeVerificationService::class)
            ->requestResubmission(
                $account,
                $admin,
                ['reason' => $reason],
                null,
                Request::create('/admin/copy-trading/resubmit', 'PATCH')
            );

        $presented = app(CopyTradePresenter::class)->account($updated);

        $this->assertSame(
            CopyTradeBrokerAccount::STATUS_NEEDS_RESUBMISSION,
            $presented['status']
        );
        $this->assertSame($reason, $presented['status_reason']);
        $this->assertFalse($presented['copy_trade_ready']);
    }

    private function pendingAccount(string $accountNumber): CopyTradeBrokerAccount
    {
        [, $market] = $this->brokerMarket();
        $user = $this->createApiUser();
        $service = app(CopyTradeOnboardingService::class);
        $request = Request::create('/copy-trade', 'POST');
        $intent = $service->openAccountAction($user, $market, $request);

        return $service->submitBrokerAccount(
            $user,
            $intent,
            $accountNumber,
            $request
        );
    }

    private function brokerMarket(): array
    {
        $broker = CopyTradeBroker::query()->firstOrCreate(
            ['code' => 'IC_MARKETS'],
            [
                'name' => 'IC Markets',
                'is_active' => true,
            ]
        );

        $market = CopyTradeBrokerMarket::query()->firstOrCreate(
            [
                'broker_id' => $broker->id,
                'market_category' => 'FOREX',
            ],
            [
                'referral_url' => 'https://broker.example.test/tradex-partner',
                'required_currency' => 'USD',
                'live_account_only' => true,
                'is_active' => true,
            ]
        );

        return [$broker->fresh(), $market->fresh('broker')];
    }

    private function adminWithPermissions(array $codes): AdminUser
    {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Copy Trading Test Admin',
            'email' => 'copy-trading-admin-'.Str::lower(Str::random(10)).'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
        ]);

        $role = AdminRole::query()->create([
            'code' => 'COPY_TEST_'.Str::upper(Str::random(8)),
            'name' => 'Copy Trading Test Role',
            'description' => 'ADMIN-22 test role.',
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
