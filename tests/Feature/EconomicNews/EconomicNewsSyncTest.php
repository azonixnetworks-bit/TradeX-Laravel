<?php

namespace Tests\Feature\EconomicNews;

use App\Models\EconomicEvent;
use App\Models\EconomicEventUpdate;
use App\Models\EconomicNewsSource;
use App\Services\EconomicNews\Contracts\EconomicCalendarProvider;
use App\Services\EconomicNews\EconomicNewsSyncService;
use App\Services\EconomicNews\Providers\TradingEconomicsCalendarProvider;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EconomicNewsSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_trading_economics_provider_normalizes_utc_calendar_rows_and_filters_low_impact(): void
    {
        config()->set('economic_news.sync.countries', ['United States']);
        config()->set('economic_news.sync.minimum_importance', 2);
        config()->set('economic_news.providers.trading_economics.api_key', 'test-secret-key');
        config()->set('economic_news.providers.trading_economics.base_url', 'https://api.tradingeconomics.com');

        Http::fake([
            '*' => Http::response([
                [
                    'CalendarId' => '1001',
                    'Date' => '2026-09-16T12:30:00',
                    'Country' => 'United States',
                    'Category' => 'Inflation Rate',
                    'Event' => 'CPI YoY',
                    'Actual' => '3.1%',
                    'Previous' => '3.0%',
                    'Forecast' => '3.0%',
                    'Importance' => 3,
                    'LastUpdate' => '2026-09-16T12:30:04',
                    'Currency' => '$',
                ],
                [
                    'CalendarId' => '1002',
                    'Date' => '2026-09-16T14:00:00',
                    'Country' => 'United States',
                    'Category' => 'Low Tier Event',
                    'Event' => 'Low Tier Event',
                    'Importance' => 1,
                ],
            ], 200),
        ]);

        $events = (new TradingEconomicsCalendarProvider)->fetch(
            CarbonImmutable::parse('2026-09-16', 'UTC')->startOfDay(),
            CarbonImmutable::parse('2026-09-17', 'UTC')->endOfDay()
        );

        $this->assertCount(1, $events);
        $this->assertSame('1001', $events[0]['provider_event_id']);
        $this->assertSame('CPI YoY', $events[0]['title']);
        $this->assertSame('US', $events[0]['country_code']);
        $this->assertSame('USD', $events[0]['currency']);
        $this->assertSame(EconomicEvent::IMPACT_HIGH, $events[0]['impact']);
        $this->assertSame('2026-09-16T12:30:00+00:00', $events[0]['scheduled_at_utc']->toIso8601String());

        Http::assertSent(fn ($request): bool => str_contains(
            $request->url(),
            '/calendar/country/United%20States/2026-09-16/2026-09-17'
        ));
    }

    public function test_sync_creates_provider_event_auto_pairs_and_release_update_without_overwriting_admin_intelligence(): void
    {
        config()->set('economic_news.sync.enabled', true);
        config()->set('economic_news.sync.auto_publish', false);
        config()->set('economic_news.sync.countries', ['United States']);
        config()->set('economic_news.sync.minimum_importance', 2);

        $scheduledAt = CarbonImmutable::parse('2026-09-16T12:30:00Z');
        $from = CarbonImmutable::parse('2026-09-16', 'UTC')->startOfDay();
        $to = CarbonImmutable::parse('2026-09-17', 'UTC')->endOfDay();

        $first = new EconomicNewsSyncService($this->fakeProvider([
            $this->normalizedRow($scheduledAt, null),
        ]));

        $result = $first->sync($from, $to);

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['failed']);

        $source = EconomicNewsSource::query()
            ->where('code', 'TEST_PROVIDER')
            ->firstOrFail();

        $event = EconomicEvent::query()
            ->where('economic_news_source_id', $source->id)
            ->where('provider_event_id', 'evt-1001')
            ->firstOrFail();

        $this->assertSame('API', $event->origin);
        $this->assertSame('USD', $event->currency);
        $this->assertSame(EconomicEvent::BIAS_NEUTRAL, $event->bias);
        $this->assertFalse((bool) $event->is_published);
        $this->assertStringNotContainsString('secret', json_encode($source->public_config_json));

        $this->assertDatabaseHas('economic_event_pairs', [
            'economic_event_id' => $event->id,
            'market' => 'FOREX',
            'symbol' => 'EURUSD',
            'mapping_source' => 'AUTO',
            'bias' => EconomicEvent::BIAS_NEUTRAL,
        ]);

        $this->assertDatabaseHas('economic_event_pairs', [
            'economic_event_id' => $event->id,
            'market' => 'FOREX',
            'symbol' => 'USDJPY',
            'mapping_source' => 'AUTO',
        ]);

        $event->forceFill([
            'bias' => EconomicEvent::BIAS_BULLISH,
            'confidence_score' => 88,
            'reason' => 'Admin-reviewed intelligence must survive provider refresh.',
        ])->save();

        $second = new EconomicNewsSyncService($this->fakeProvider([
            $this->normalizedRow($scheduledAt, '256K'),
        ]));

        $second->sync($from, $to);

        $event->refresh();

        $this->assertSame('256K', $event->actual);
        $this->assertSame(EconomicEvent::STATUS_RELEASED, $event->status);
        $this->assertSame(EconomicEvent::BIAS_BULLISH, $event->bias);
        $this->assertSame(88, $event->confidence_score);
        $this->assertSame(
            'Admin-reviewed intelligence must survive provider refresh.',
            $event->reason
        );

        $this->assertDatabaseHas('economic_event_updates', [
            'economic_event_id' => $event->id,
            'economic_news_source_id' => $source->id,
            'update_type' => EconomicEventUpdate::TYPE_RELEASE,
            'actual' => '256K',
            'is_published' => false,
        ]);
    }

    public function test_auto_publish_publishes_new_and_existing_provider_events(): void
    {
        config()->set('economic_news.sync.enabled', true);
        config()->set('economic_news.sync.auto_publish', false);

        $scheduledAt = CarbonImmutable::parse('2026-09-16T12:30:00Z');
        $from = CarbonImmutable::parse('2026-09-16', 'UTC')->startOfDay();
        $to = CarbonImmutable::parse('2026-09-17', 'UTC')->endOfDay();

        $service = new EconomicNewsSyncService($this->fakeProvider([
            $this->normalizedRow($scheduledAt, null),
        ]));
        $service->sync($from, $to);

        $event = EconomicEvent::query()->where('provider_event_id', 'evt-1001')->firstOrFail();
        $this->assertFalse((bool) $event->is_published);
        $this->assertNull($event->published_at);

        config()->set('economic_news.sync.auto_publish', true);
        $service->sync($from, $to);

        $event->refresh();
        $this->assertTrue((bool) $event->is_published);
        $this->assertNotNull($event->published_at);

        EconomicEvent::query()->whereKey($event->getKey())->update([
            'is_published' => false,
            'published_at' => null,
        ]);

        $service->sync($from, $to);

        $event->refresh();
        $this->assertTrue((bool) $event->is_published);
        $this->assertNotNull($event->published_at);
    }

    /** @return array<string, mixed> */
    private function normalizedRow(CarbonImmutable $scheduledAt, ?string $actual): array
    {
        return [
            'provider_event_id' => 'evt-1001',
            'title' => 'Non Farm Payrolls',
            'category' => 'Non Farm Payrolls',
            'country_name' => 'United States',
            'country_code' => 'US',
            'currency' => 'USD',
            'impact' => EconomicEvent::IMPACT_HIGH,
            'provider_importance' => 3,
            'scheduled_at_utc' => $scheduledAt,
            'actual' => $actual,
            'forecast' => '250K',
            'previous' => '240K',
            'revised_previous' => null,
            'provider_last_update_utc' => $actual === null
                ? '2026-09-16T10:00:00+00:00'
                : '2026-09-16T12:30:02+00:00',
            'provider_payload' => [
                'CalendarId' => 'evt-1001',
                'Event' => 'Non Farm Payrolls',
            ],
        ];
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function fakeProvider(array $rows): EconomicCalendarProvider
    {
        return new class($rows) implements EconomicCalendarProvider
        {
            /** @param array<int, array<string, mixed>> $rows */
            public function __construct(private readonly array $rows) {}

            public function code(): string
            {
                return 'TEST_PROVIDER';
            }

            public function name(): string
            {
                return 'Test Economic Provider';
            }

            public function homepageUrl(): string
            {
                return 'https://provider.test';
            }

            /** @return array<int, array<string, mixed>> */
            public function fetch(CarbonImmutable $from, CarbonImmutable $to): array
            {
                return $this->rows;
            }
        };
    }
}
