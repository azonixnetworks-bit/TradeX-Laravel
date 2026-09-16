<?php

namespace Tests\Feature\EconomicNews;

use App\Models\EconomicEvent;
use App\Services\EconomicNews\Providers\ForexFactoryJsonCalendarProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ForexFactoryJsonCalendarProviderTest extends TestCase
{
    public function test_provider_normalizes_weekly_json_filters_impact_and_converts_time_to_utc(): void
    {
        config()->set('economic_news.sync.minimum_importance', 2);
        config()->set(
            'economic_news.providers.forex_factory_json.feed_url',
            'https://nfs.faireconomy.media/ff_calendar_thisweek.json'
        );
        config()->set('economic_news.currency_country_map.USD', 'US');
        config()->set('economic_news.currency_country_map.GBP', 'GB');

        Http::fake([
            '*' => Http::response([
                [
                    'title' => 'Core CPI m/m',
                    'country' => 'USD',
                    'date' => '2026-09-16T08:30:00-04:00',
                    'impact' => 'High',
                    'forecast' => '0.3%',
                    'previous' => '0.2%',
                    'actual' => '0.4%',
                ],
                [
                    'title' => 'GDP m/m',
                    'country' => 'GBP',
                    'date' => '2026-09-16T02:00:00-04:00',
                    'impact' => 'Medium',
                    'forecast' => '0.1%',
                    'previous' => '0.0%',
                ],
                [
                    'title' => 'Low Tier Event',
                    'country' => 'USD',
                    'date' => '2026-09-16T10:00:00-04:00',
                    'impact' => 'Low',
                    'forecast' => '',
                    'previous' => '',
                ],
                [
                    'title' => 'Bank Holiday',
                    'country' => 'USD',
                    'date' => '2026-09-16T00:00:00-04:00',
                    'impact' => 'Holiday',
                    'forecast' => '',
                    'previous' => '',
                ],
                [
                    'title' => 'Outside Requested Range',
                    'country' => 'USD',
                    'date' => '2026-09-20T08:30:00-04:00',
                    'impact' => 'High',
                    'forecast' => '1',
                    'previous' => '1',
                ],
            ], 200),
        ]);

        $events = (new ForexFactoryJsonCalendarProvider())->fetch(
            CarbonImmutable::parse('2026-09-16', 'UTC')->startOfDay(),
            CarbonImmutable::parse('2026-09-17', 'UTC')->endOfDay()
        );

        $this->assertCount(2, $events);

        $usd = collect($events)->firstWhere('currency', 'USD');
        $this->assertNotNull($usd);
        $this->assertStringStartsWith('ff-', $usd['provider_event_id']);
        $this->assertSame('US', $usd['country_code']);
        $this->assertSame(EconomicEvent::IMPACT_HIGH, $usd['impact']);
        $this->assertSame('0.4%', $usd['actual']);
        $this->assertSame('0.3%', $usd['forecast']);
        $this->assertSame('0.2%', $usd['previous']);
        $this->assertSame(
            '2026-09-16T12:30:00+00:00',
            $usd['scheduled_at_utc']->toIso8601String()
        );

        $gbp = collect($events)->firstWhere('currency', 'GBP');
        $this->assertNotNull($gbp);
        $this->assertSame('GB', $gbp['country_code']);
        $this->assertSame(EconomicEvent::IMPACT_MEDIUM, $gbp['impact']);

        Http::assertSent(fn ($request): bool => $request->url()
            === 'https://nfs.faireconomy.media/ff_calendar_thisweek.json');
    }
}
