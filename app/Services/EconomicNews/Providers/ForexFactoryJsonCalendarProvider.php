<?php

namespace App\Services\EconomicNews\Providers;

use App\Models\EconomicEvent;
use App\Services\EconomicNews\Contracts\EconomicCalendarProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class ForexFactoryJsonCalendarProvider implements EconomicCalendarProvider
{
    public function code(): string
    {
        return 'FOREX_FACTORY_JSON';
    }

    public function name(): string
    {
        return 'Forex Factory Weekly JSON';
    }

    public function homepageUrl(): string
    {
        return 'https://www.forexfactory.com/calendar';
    }

    /** @return array<int, array<string, mixed>> */
    public function fetch(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $config = (array) config('economic_news.providers.forex_factory_json', []);
        $feedUrl = trim((string) ($config['feed_url'] ?? ''));

        if ($feedUrl === '') {
            throw new RuntimeException('Forex Factory JSON feed URL is not configured.');
        }

        try {
            $response = Http::acceptJson()
                ->withUserAgent((string) ($config['user_agent'] ?? 'TradeX Economic News/1.0'))
                ->connectTimeout((int) ($config['connect_timeout_seconds'] ?? 5))
                ->timeout((int) ($config['timeout_seconds'] ?? 15))
                ->retry(2, 250, throw: false)
                ->get($feedUrl);
        } catch (Throwable $exception) {
            throw new RuntimeException('Forex Factory JSON feed connection failed.', 0, $exception);
        }

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'Forex Factory JSON feed request failed with HTTP %d.',
                $response->status()
            ));
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Forex Factory JSON feed returned an invalid JSON payload.');
        }

        $minimumImportance = (int) config('economic_news.sync.minimum_importance', 2);
        $events = [];

        foreach ($payload as $row) {
            if (! is_array($row)) {
                continue;
            }

            $normalized = $this->normalizeRow($row);
            if ($normalized === null) {
                continue;
            }

            if ((int) $normalized['provider_importance'] < $minimumImportance) {
                continue;
            }

            /** @var CarbonImmutable $scheduledAt */
            $scheduledAt = $normalized['scheduled_at_utc'];
            if ($scheduledAt->lessThan($from) || $scheduledAt->greaterThan($to)) {
                continue;
            }

            $events[] = $normalized;
        }

        return $events;
    }

    /** @return array<string, mixed>|null */
    private function normalizeRow(array $row): ?array
    {
        $fields = array_change_key_case($row, CASE_LOWER);
        $title = trim((string) ($fields['title'] ?? ''));
        $currency = strtoupper(trim((string) ($fields['country'] ?? '')));
        $date = trim((string) ($fields['date'] ?? ''));

        if ($title === '' || $currency === '' || $date === '') {
            return null;
        }

        try {
            $scheduledAt = CarbonImmutable::parse($date)->utc();
        } catch (Throwable) {
            return null;
        }

        $importance = $this->importance((string) ($fields['impact'] ?? ''));
        if ($importance === null) {
            return null;
        }

        $providerEventId = 'ff-'.substr(hash('sha256', implode('|', [
            $currency,
            mb_strtolower(preg_replace('/\s+/', ' ', $title) ?? $title),
            $scheduledAt->format('Y-m-d\TH:i:sP'),
        ])), 0, 40);

        return [
            'provider_event_id' => $providerEventId,
            'title' => $title,
            'category' => null,
            'country_name' => null,
            'country_code' => $this->countryCodeForCurrency($currency),
            'currency' => $currency,
            'impact' => match ($importance) {
                3 => EconomicEvent::IMPACT_HIGH,
                2 => EconomicEvent::IMPACT_MEDIUM,
                default => EconomicEvent::IMPACT_LOW,
            },
            'provider_importance' => $importance,
            'scheduled_at_utc' => $scheduledAt,
            'actual' => $this->nullableString($fields['actual'] ?? null),
            'forecast' => $this->nullableString($fields['forecast'] ?? null),
            'previous' => $this->nullableString($fields['previous'] ?? null),
            'revised_previous' => null,
            'provider_last_update_utc' => null,
            'provider_payload' => $row,
        ];
    }

    private function importance(string $impact): ?int
    {
        return match (strtolower(trim($impact))) {
            'high' => 3,
            'medium' => 2,
            'low' => 1,
            default => null,
        };
    }

    private function countryCodeForCurrency(string $currency): ?string
    {
        $map = (array) config('economic_news.currency_country_map', []);
        $code = $map[$currency] ?? null;

        if (! is_string($code)) {
            return null;
        }

        $code = strtoupper(trim($code));

        return $code !== '' ? $code : null;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' || strtolower($value) === 'null' ? null : $value;
    }
}
