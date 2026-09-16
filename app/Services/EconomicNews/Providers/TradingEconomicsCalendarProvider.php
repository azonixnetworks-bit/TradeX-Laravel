<?php

namespace App\Services\EconomicNews\Providers;

use App\Models\EconomicEvent;
use App\Services\EconomicNews\Contracts\EconomicCalendarProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class TradingEconomicsCalendarProvider implements EconomicCalendarProvider
{
    public function code(): string
    {
        return 'TRADING_ECONOMICS';
    }

    public function name(): string
    {
        return 'Trading Economics';
    }

    public function homepageUrl(): string
    {
        return 'https://tradingeconomics.com';
    }

    /** @return array<int, array<string, mixed>> */
    public function fetch(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $config = (array) config('economic_news.providers.trading_economics', []);
        $apiKey = trim((string) ($config['api_key'] ?? ''));

        if ($apiKey === '') {
            throw new RuntimeException('Trading Economics API key is not configured.');
        }

        $countries = array_values(array_filter(array_map(
            static fn ($country): string => trim((string) $country),
            (array) config('economic_news.sync.countries', [])
        )));

        if ($countries === []) {
            throw new RuntimeException('Economic news country scope is empty.');
        }

        $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://api.tradingeconomics.com'), '/');
        $countryPath = implode(',', array_map('rawurlencode', $countries));
        $url = sprintf(
            '%s/calendar/country/%s/%s/%s',
            $baseUrl,
            $countryPath,
            $from->utc()->format('Y-m-d'),
            $to->utc()->format('Y-m-d')
        );

        try {
            $response = Http::acceptJson()
                ->connectTimeout((int) ($config['connect_timeout_seconds'] ?? 5))
                ->timeout((int) ($config['timeout_seconds'] ?? 15))
                ->retry(2, 250, throw: false)
                ->get($url, [
                    'c' => $apiKey,
                    'f' => 'json',
                    'values' => 'true',
                ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Trading Economics connection failed.', 0, $exception);
        }

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'Trading Economics request failed with HTTP %d.',
                $response->status()
            ));
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Trading Economics returned an invalid JSON payload.');
        }

        $minimumImportance = (int) config('economic_news.sync.minimum_importance', 2);
        $events = [];

        foreach ($payload as $row) {
            if (! is_array($row)) {
                continue;
            }

            $normalized = $this->normalizeRow($row);
            if ($normalized === null || (int) $normalized['provider_importance'] < $minimumImportance) {
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
        $providerEventId = trim((string) ($fields['calendarid'] ?? ''));
        $title = trim((string) ($fields['event'] ?? $fields['category'] ?? ''));
        $date = trim((string) ($fields['date'] ?? ''));

        if ($providerEventId === '' || $title === '' || $date === '') {
            return null;
        }

        try {
            $scheduledAt = CarbonImmutable::parse($date, 'UTC')->utc();
        } catch (Throwable) {
            return null;
        }

        $countryName = trim((string) ($fields['country'] ?? ''));
        $country = $this->countryMetadata($countryName);
        $importance = max(1, min(3, (int) ($fields['importance'] ?? 1)));
        $rawCurrency = strtoupper(trim((string) ($fields['currency'] ?? '')));
        $currency = $country['currency'] ?? (preg_match('/^[A-Z]{3}$/', $rawCurrency) ? $rawCurrency : null);
        $lastUpdate = $this->utcTimestamp($fields['lastupdate'] ?? null);

        return [
            'provider_event_id' => $providerEventId,
            'title' => $title,
            'category' => $this->nullableString($fields['category'] ?? null),
            'country_name' => $countryName !== '' ? $countryName : null,
            'country_code' => $country['code'] ?? null,
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
            'revised_previous' => $this->nullableString($fields['revised'] ?? null),
            'provider_last_update_utc' => $lastUpdate,
            'provider_payload' => $row,
        ];
    }

    /** @return array{code:string,currency:string}|array{} */
    private function countryMetadata(string $countryName): array
    {
        if ($countryName === '') {
            return [];
        }

        $map = (array) config('economic_news.country_map', []);
        $metadata = $map[strtolower($countryName)] ?? null;

        return is_array($metadata) ? $metadata : [];
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        return $value === '' || strtolower($value) === 'null' ? null : $value;
    }

    private function utcTimestamp(mixed $value): ?string
    {
        $value = $this->nullableString($value);
        if ($value === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, 'UTC')->utc()->toIso8601String();
        } catch (Throwable) {
            return null;
        }
    }
}
