<?php

namespace Tests\Feature\Api\TradeJournal;

use App\Models\TradeJournalEntry;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class TradeJournalApiTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_mobile_user_can_create_low_rr_trade_and_read_only_own_journal(): void
    {
        $this->travelTo(Carbon::parse('2026-09-01 12:00:00', 'UTC'));
        $owner = $this->createApiUser();
        $other = $this->createApiUser();
        $ownerToken = $this->issueBoundToken($owner);
        $otherToken = $this->issueBoundToken($other);

        $created = $this->withToken($ownerToken)->postJson('/api/v1/trade-journal', $this->payload([
            'planned_entry' => '100',
            'planned_sl' => '90',
            'planned_tp' => '110',
        ]));

        $created
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'planned')
            ->assertJsonPath('data.risk.planned_rr', '1.00000000');

        $uuid = (string) $created->json('data.uuid');

        $this->withToken($ownerToken)
            ->getJson('/api/v1/trade-journal/'.$uuid.'?timezone=Asia/Colombo')
            ->assertOk()
            ->assertJsonPath('data.uuid', $uuid)
            ->assertJsonPath('data.display_timezone', 'Asia/Colombo');

        $this->withToken($otherToken)
            ->getJson('/api/v1/trade-journal/'.$uuid)
            ->assertNotFound();
    }

    public function test_planned_open_close_flow_and_analytics_work(): void
    {
        $this->travelTo(Carbon::parse('2026-09-01 12:00:00', 'UTC'));
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $created = $this->withToken($token)->postJson('/api/v1/trade-journal', $this->payload());
        $uuid = (string) $created->json('data.uuid');

        $this->withToken($token)->postJson('/api/v1/trade-journal/'.$uuid.'/open', [
            'actual_entry' => '100',
            'entry_date' => '2026-09-01',
            'entry_time' => '10:00',
            'timezone' => 'UTC',
        ])->assertOk()->assertJsonPath('data.status', 'open');

        $this->withToken($token)->postJson('/api/v1/trade-journal/'.$uuid.'/close', [
            'exit_price' => '110',
            'exit_date' => '2026-09-01',
            'exit_time' => '11:00',
            'timezone' => 'UTC',
            'result' => 'win',
            'gross_pnl' => '25',
            'fees' => '2',
            'pnl_currency' => 'USD',
            'emotion_after' => 'satisfied',
            'followed_plan_after' => 'yes',
            'mistake_tags' => [],
            'lesson_learned' => 'Waited for confirmation.',
            'rating' => 5,
        ])->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.pnl.net', '23.00000000');

        $this->withToken($token)
            ->getJson('/api/v1/trade-journal/analytics?timezone=UTC&period=month')
            ->assertOk()
            ->assertJsonPath('data.overview.total_executed', 1)
            ->assertJsonPath('data.overview.wins', 1)
            ->assertJsonPath('data.overview.win_rate', 100);
    }

    public function test_user_id_injection_is_rejected(): void
    {
        $this->travelTo(Carbon::parse('2026-09-01 12:00:00', 'UTC'));
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this->withToken($token)->postJson('/api/v1/trade-journal', $this->payload([
            'user_id' => 999999,
        ]))->assertUnprocessable();

        $this->assertDatabaseCount('trade_journal_entries', 0);
    }

    public function test_weekly_and_monthly_reviews_are_private_upserts(): void
    {
        $this->travelTo(Carbon::parse('2026-09-03 12:00:00', 'UTC'));
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this->withToken($token)->putJson('/api/v1/trade-journal/reviews/weekly?timezone=UTC', [
            'positive_reflection' => 'Followed the plan.',
            'negative_reflection' => 'One rushed entry.',
            'biggest_lesson' => 'Wait for confirmation.',
            'stop_doing' => 'Chasing price.',
            'continue_doing' => 'Pre-trade checklist.',
            'next_focus' => 'Patience.',
            'rating' => 4,
        ])->assertOk()->assertJsonPath('data.period_type', 'weekly');

        $this->withToken($token)->putJson('/api/v1/trade-journal/reviews/weekly?timezone=UTC', [
            'positive_reflection' => 'Updated review.',
            'next_focus' => 'Execution quality.',
            'rating' => 5,
        ])->assertOk()->assertJsonPath('data.rating', 5);

        $this->assertDatabaseCount('trade_journal_reviews', 1);

        $this->withToken($token)
            ->getJson('/api/v1/trade-journal/reviews/weekly?timezone=UTC')
            ->assertOk()
            ->assertJsonPath('data.review.positive_reflection', 'Updated review.');
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
            'setup_strategy' => 'liquidity_sweep',
            'timeframes' => ['H1', 'M15'],
            'entry_reason' => 'Liquidity sweep and confirmation.',
            'planned_entry' => '100',
            'planned_sl' => '90',
            'planned_tp' => '120',
            'risk_percent' => '1.00',
            'emotion_before' => 'calm',
            'confidence_before' => 4,
            'checklist' => [
                'followed_trading_plan' => 'yes',
                'sl_planned' => 'yes',
                'tp_planned' => 'yes',
                'session_checked' => 'yes',
                'news_checked' => 'yes',
                'risk_checked' => 'yes',
                'setup_confirmed' => 'yes',
            ],
        ], $overrides);
    }
}
