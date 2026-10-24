<?php

namespace Tests\Feature\Api\TradeJournal;

use App\Models\TradeJournalEntry;
use App\Models\TradeJournalReview;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class TradeJournalRetentionTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_save_belongs_to_authenticated_customer_and_prunes_only_their_expired_data(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 12:00:00', 'UTC'));
        $customer = $this->createApiUser();
        $otherCustomer = $this->createApiUser();
        $token = $this->issueBoundToken($customer);

        $expired = $this->entryFor($customer->id, 'OLDUSDT', now()->subDays(46));
        $otherExpired = $this->entryFor($otherCustomer->id, 'OTHERUSDT', now()->subDays(46));

        $response = $this->withToken($token)->postJson(
            '/api/v1/trade-journal',
            $this->payload()
        );

        $response->assertCreated()->assertJsonPath('data.symbol', 'BTCUSDT');
        $this->assertDatabaseHas('trade_journal_entries', [
            'uuid' => $response->json('data.uuid'),
            'user_id' => $customer->id,
        ]);
        $this->assertDatabaseMissing('trade_journal_entries', ['id' => $expired->id]);
        $this->assertDatabaseHas('trade_journal_entries', ['id' => $otherExpired->id]);
    }

    public function test_prune_command_permanently_deletes_expired_data_and_screenshots(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 12:00:00', 'UTC'));
        Storage::fake('private');
        config()->set('trade_journal.screenshots.disk', 'private');
        $customer = $this->createApiUser();

        $expired = $this->entryFor($customer->id, 'OLDUSDT', now()->subDays(46), [
            'before_screenshot_path' => 'trade-journals/old/before.png',
            'after_screenshot_path' => 'trade-journals/old/after.png',
        ]);
        Storage::disk('private')->put($expired->before_screenshot_path, 'before');
        Storage::disk('private')->put($expired->after_screenshot_path, 'after');
        $recent = $this->entryFor($customer->id, 'BTCUSDT', now()->subDays(45));
        $review = $this->reviewFor($customer->id, now()->subDays(46));

        $this->artisan('tradex:trade-journal:prune')
            ->expectsOutput('Deleted 2 expired Trade Journal record(s); retention is 45 days.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('trade_journal_entries', ['id' => $expired->id]);
        $this->assertDatabaseMissing('trade_journal_reviews', ['id' => $review->id]);
        $this->assertDatabaseHas('trade_journal_entries', ['id' => $recent->id]);
        Storage::disk('private')->assertMissing('trade-journals/old/before.png');
        Storage::disk('private')->assertMissing('trade-journals/old/after.png');
    }

    private function entryFor(
        int $userId,
        string $symbol,
        Carbon $createdAt,
        array $overrides = []
    ): TradeJournalEntry {
        $entry = new TradeJournalEntry(array_merge([
            'uuid' => (string) str()->uuid(),
            'market' => 'crypto',
            'symbol' => $symbol,
            'direction' => 'buy',
            'status' => 'planned',
            'trade_at' => $createdAt,
            'user_timezone' => 'UTC',
            'session_source' => 'manual',
            'entry_reason' => 'Retention test',
        ], $overrides));
        $entry->user_id = $userId;
        $entry->created_at = $createdAt;
        $entry->updated_at = $createdAt;
        $entry->save(['timestamps' => false]);

        return $entry;
    }

    private function reviewFor(int $userId, Carbon $createdAt): TradeJournalReview
    {
        $review = new TradeJournalReview([
            'uuid' => (string) str()->uuid(),
            'period_type' => 'weekly',
            'period_start' => $createdAt->toDateString(),
            'period_end' => $createdAt->copy()->addDays(6)->toDateString(),
            'user_timezone' => 'UTC',
        ]);
        $review->user_id = $userId;
        $review->created_at = $createdAt;
        $review->updated_at = $createdAt;
        $review->save(['timestamps' => false]);

        return $review;
    }

    private function payload(): array
    {
        return [
            'market' => 'crypto',
            'symbol' => 'BTCUSDT',
            'direction' => 'buy',
            'status' => 'planned',
            'trade_date' => '2026-10-24',
            'trade_time' => '10:00',
            'timezone' => 'UTC',
            'entry_reason' => 'Confirmed setup',
            'planned_entry' => '68000',
            'planned_sl' => '67999.9',
            'planned_tp' => '68000.5',
            'checklist' => [],
        ];
    }
}
