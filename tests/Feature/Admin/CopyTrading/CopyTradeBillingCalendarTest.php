<?php

namespace Tests\Feature\Admin\CopyTrading;

use App\Models\CopyTradeBillingCalendarDay;
use App\Models\CopyTradeBroker;
use App\Models\CopyTradeBrokerAccount;
use App\Models\CopyTradeBrokerMarket;
use App\Services\CopyTrading\Billing\CopyTradeBillingCalendarService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class CopyTradeBillingCalendarTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'tradex_copy_trading.billing.forex_server_timezone',
            'UTC'
        );
    }

    public function test_forex_proration_uses_explicit_official_calendar_not_weekend_guessing(): void
    {
        $account = $this->forexAccount();

        for ($day = 1; $day <= 30; $day++) {
            CopyTradeBillingCalendarDay::query()
                ->create([
                    'market_category' =>
                        'FOREX',
                    'calendar_date' =>
                        sprintf(
                            '2026-09-%02d',
                            $day
                        ),
                    'is_billable_day' =>
                        $day <= 20,
                    'source' =>
                        'SYSTEM_IMPORT',
                ]);
        }

        $ratio = app(
            CopyTradeBillingCalendarService::class
        )->ratio(
            $account,
            CarbonImmutable::parse(
                '2026-09-11 00:00:00',
                'UTC'
            ),
            CarbonImmutable::parse(
                '2026-09-01 00:00:00',
                'UTC'
            ),
            CarbonImmutable::parse(
                '2026-10-01 00:00:00',
                'UTC'
            )
        );

        $this->assertSame(
            10,
            $ratio['numerator']
        );

        $this->assertSame(
            20,
            $ratio['denominator']
        );

        $this->assertSame(
            'FOREX_OFFICIAL_TRADING_DAYS',
            $ratio['formula_code']
        );
    }

    public function test_forex_proration_refuses_incomplete_official_calendar(): void
    {
        $account = $this->forexAccount();

        CopyTradeBillingCalendarDay::query()
            ->create([
                'market_category' => 'FOREX',
                'calendar_date' => '2026-09-01',
                'is_billable_day' => true,
                'source' => 'ADMIN_MANUAL',
            ]);

        $this->expectException(
            DomainException::class
        );

        app(
            CopyTradeBillingCalendarService::class
        )->ratio(
            $account,
            CarbonImmutable::parse(
                '2026-09-11 00:00:00',
                'UTC'
            ),
            CarbonImmutable::parse(
                '2026-09-01 00:00:00',
                'UTC'
            ),
            CarbonImmutable::parse(
                '2026-10-01 00:00:00',
                'UTC'
            )
        );
    }

    public function test_used_forex_calendar_rows_become_locked(): void
    {
        for ($day = 1; $day <= 30; $day++) {
            CopyTradeBillingCalendarDay::query()
                ->create([
                    'market_category' =>
                        'FOREX',
                    'calendar_date' =>
                        sprintf(
                            '2026-09-%02d',
                            $day
                        ),
                    'is_billable_day' => true,
                    'source' =>
                        'SYSTEM_IMPORT',
                ]);
        }

        app(
            CopyTradeBillingCalendarService::class
        )->lockForexCalendar(
            CarbonImmutable::parse(
                '2026-09-01 00:00:00',
                'UTC'
            ),
            CarbonImmutable::parse(
                '2026-10-01 00:00:00',
                'UTC'
            )
        );

        $this->assertSame(
            30,
            CopyTradeBillingCalendarDay::query()
                ->whereNotNull('locked_at')
                ->count()
        );
    }

    private function forexAccount(): CopyTradeBrokerAccount
    {
        $user = $this->createApiUser();

        $broker = CopyTradeBroker::query()
            ->create([
                'code' =>
                    'FX'.Str::upper(
                        Str::random(8)
                    ),
                'name' =>
                    'Forex Test Broker',
                'is_active' => true,
            ]);

        $market = CopyTradeBrokerMarket::query()
            ->create([
                'broker_id' =>
                    $broker->id,
                'market_category' =>
                    'FOREX',
                'referral_url' =>
                    'https://broker.example.test/forex',
                'required_currency' =>
                    'USD',
                'live_account_only' =>
                    true,
                'is_active' =>
                    true,
            ]);

        return CopyTradeBrokerAccount::query()
            ->create([
                'user_id' =>
                    $user->id,
                'broker_id' =>
                    $broker->id,
                'broker_market_id' =>
                    $market->id,
                'account_number' =>
                    '510000001',
                'account_number_fingerprint' =>
                    hash(
                        'sha256',
                        (string) Str::uuid()
                    ),
                'account_number_last4' =>
                    '0001',
                'status' =>
                    CopyTradeBrokerAccount::STATUS_ACTIVE,
                'resubmission_count' =>
                    0,
                'verified_currency' =>
                    'USD',
                'verified_account_mode' =>
                    'LIVE',
                'partner_attribution_confirmed_at' =>
                    now()->subDay(),
                'submitted_at' =>
                    now()->subDays(2),
                'verified_at' =>
                    now()->subDay(),
                'activated_at' =>
                    now(),
            ]);
    }
}
