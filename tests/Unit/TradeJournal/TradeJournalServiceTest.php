<?php

namespace Tests\Unit\TradeJournal;

use App\Models\TradeJournalEntry;
use App\Models\User;
use App\Services\TradeJournal\TradeJournalAnalyticsService;
use App\Services\TradeJournal\TradeJournalService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TradeJournalServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_rr_one_to_one_is_accepted_without_a_minimum_rule(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-01T12:00:00Z'));
        $user = User::factory()->create();
        $service = app(TradeJournalService::class);

        $result = $service->create($user, $this->payload([
            'planned_entry' => '100',
            'planned_sl' => '90',
            'planned_tp' => '110',
        ]));

        $this->assertSame('1.00000000', $result['entry']->planned_rr);
        $this->assertSame('planned', $result['entry']->status);
    }

    public function test_planned_trade_can_open_and_close_but_cannot_reopen(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-01T12:00:00Z'));
        $user = User::factory()->create();
        $service = app(TradeJournalService::class);
        $entry = $service->create($user, $this->payload())['entry'];

        $opened = $service->open($user, $entry, [
            'actual_entry' => '100',
            'entry_date' => '2026-09-01',
            'entry_time' => '10:00',
            'timezone' => 'UTC',
        ])['entry'];
        $this->assertSame('open', $opened->status);

        $closed = $service->close($user, $opened, [
            'exit_price' => '110',
            'exit_date' => '2026-09-01',
            'exit_time' => '11:00',
            'timezone' => 'UTC',
            'result' => 'win',
            'gross_pnl' => '25',
            'fees' => '2',
            'pnl_currency' => 'USD',
            'followed_plan_after' => 'yes',
        ]);

        $this->assertSame('closed', $closed->status);
        $this->assertSame('23.00000000', $closed->net_pnl);

        $this->expectException(ValidationException::class);
        $service->open($user, $closed, [
            'actual_entry' => '100',
            'entry_date' => '2026-09-01',
            'entry_time' => '10:00',
            'timezone' => 'UTC',
        ]);
    }

    public function test_user_cannot_resolve_another_users_private_journal(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $service = app(TradeJournalService::class);
        $entry = $service->create($owner, $this->payload())['entry'];

        $this->assertNull(
            TradeJournalEntry::query()
                ->where('user_id', $other->id)
                ->where('uuid', $entry->uuid)
                ->first()
        );
    }

    public function test_cancelled_trade_is_excluded_from_executed_analytics(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-01T12:00:00Z'));
        $user = User::factory()->create();
        $service = app(TradeJournalService::class);
        $analytics = app(TradeJournalAnalyticsService::class);

        $planned = $service->create($user, $this->payload())['entry'];
        $service->cancel($user, $planned, 'Setup invalidated');

        $data = $analytics->analytics($user, 'UTC', 'month');

        $this->assertSame(0, $data['overview']['total_executed']);
        $this->assertSame(0, $data['overview']['closed']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'market' => 'forex',
            'symbol' => 'EURUSD',
            'direction' => 'buy',
            'status' => 'planned',
            'trade_date' => '2026-09-01',
            'trade_time' => '09:00',
            'timezone' => 'UTC',
            'entry_reason' => 'Test setup confirmation',
            'planned_entry' => '100',
            'planned_sl' => '90',
            'planned_tp' => '120',
            'checklist' => [
                'followed_trading_plan' => 'yes',
                'session_checked' => 'yes',
                'news_checked' => 'yes',
                'risk_checked' => 'yes',
                'setup_confirmed' => 'yes',
            ],
        ], $overrides);
    }
}
