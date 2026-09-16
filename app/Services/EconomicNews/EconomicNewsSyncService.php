<?php

namespace App\Services\EconomicNews;

use App\Models\EconomicEvent;
use App\Models\EconomicEventUpdate;
use App\Models\EconomicNewsSource;
use App\Services\EconomicNews\Contracts\EconomicCalendarProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class EconomicNewsSyncService
{
    public function __construct(
        private readonly EconomicCalendarProvider $provider
    ) {
    }

    /**
     * @return array{enabled:bool,source_enabled:bool,provider:string,fetched:int,created:int,updated:int,unchanged:int,failed:int}
     */
    public function sync(?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array
    {
        $result = [
            'enabled' => (bool) config('economic_news.sync.enabled', false),
            'source_enabled' => true,
            'provider' => $this->provider->code(),
            'fetched' => 0,
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'failed' => 0,
        ];

        if (! $result['enabled']) {
            return $result;
        }

        $now = CarbonImmutable::now('UTC');
        $from ??= $now
            ->subDays((int) config('economic_news.sync.lookback_days', 2))
            ->startOfDay();
        $to ??= $now
            ->addDays((int) config('economic_news.sync.lookahead_days', 14))
            ->endOfDay();

        $from = $from->utc();
        $to = $to->utc();

        if ($from->greaterThan($to)) {
            throw new RuntimeException('Economic news sync start date must be before or equal to end date.');
        }

        $source = $this->source();
        if (! $source->is_enabled) {
            $result['source_enabled'] = false;
            return $result;
        }

        $source->forceFill([
            'last_fetched_at' => $now,
            'last_error_code' => null,
            'last_error_message' => null,
        ])->save();

        try {
            $rows = $this->provider->fetch($from, $to);
        } catch (Throwable $exception) {
            $source->forceFill([
                'last_error_at' => $now,
                'last_error_code' => class_basename($exception),
                'last_error_message' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();

            throw $exception;
        }

        $result['fetched'] = count($rows);

        foreach ($rows as $row) {
            try {
                $outcome = $this->syncEvent($source, $row, $now);
                $result[$outcome]++;
            } catch (Throwable $exception) {
                $result['failed']++;

                Log::warning('Economic news provider row failed to synchronize.', [
                    'provider' => $this->provider->code(),
                    'provider_event_id' => (string) ($row['provider_event_id'] ?? ''),
                    'exception' => $exception::class,
                ]);
            }
        }

        if ($result['failed'] === 0) {
            $source->forceFill([
                'last_success_at' => CarbonImmutable::now('UTC'),
                'last_error_at' => null,
                'last_error_code' => null,
                'last_error_message' => null,
            ])->save();
        } else {
            $source->forceFill([
                'last_error_at' => CarbonImmutable::now('UTC'),
                'last_error_code' => 'ROW_SYNC_FAILURE',
                'last_error_message' => sprintf('%d provider row(s) could not be synchronized.', $result['failed']),
            ])->save();
        }

        return $result;
    }

    private function source(): EconomicNewsSource
    {
        $source = EconomicNewsSource::query()->firstOrCreate(
            ['code' => $this->provider->code()],
            [
                'name' => $this->provider->name(),
                'source_type' => EconomicNewsSource::TYPE_API,
                'homepage_url' => $this->provider->homepageUrl(),
                'is_enabled' => true,
                'priority' => 10,
                'reliability_weight' => 100.00,
                'public_config_json' => $this->publicProviderConfig(),
                'notes' => 'Automatic economic-calendar source. Provider credentials remain in deployment secrets only.',
            ]
        );

        $source->forceFill([
            'name' => $this->provider->name(),
            'source_type' => EconomicNewsSource::TYPE_API,
            'homepage_url' => $this->provider->homepageUrl(),
            'public_config_json' => $this->publicProviderConfig(),
        ])->save();

        return $source;
    }

    /** @return array<string, mixed> */
    private function publicProviderConfig(): array
    {
        return [
            'provider' => (string) config('economic_news.sync.provider', 'trading_economics'),
            'countries' => array_values((array) config('economic_news.sync.countries', [])),
            'minimum_importance' => (int) config('economic_news.sync.minimum_importance', 2),
            'lookback_days' => (int) config('economic_news.sync.lookback_days', 2),
            'lookahead_days' => (int) config('economic_news.sync.lookahead_days', 14),
        ];
    }

    /** @param array<string, mixed> $row */
    private function syncEvent(EconomicNewsSource $source, array $row, CarbonImmutable $syncedAt): string
    {
        return DB::transaction(function () use ($source, $row, $syncedAt): string {
            $providerEventId = trim((string) ($row['provider_event_id'] ?? ''));
            if ($providerEventId === '') {
                throw new RuntimeException('Provider event id is missing.');
            }

            $event = EconomicEvent::query()
                ->where('economic_news_source_id', $source->getKey())
                ->where('provider_event_id', $providerEventId)
                ->lockForUpdate()
                ->first();

            $isNew = $event === null;
            $event ??= new EconomicEvent();

            $beforeActual = $event->actual;
            $beforePrevious = $event->previous;
            $beforeRevisedPrevious = $event->revised_previous;
            $beforeCurrency = $event->currency;

            $scheduledAt = $row['scheduled_at_utc'] instanceof CarbonImmutable
                ? $row['scheduled_at_utc']->utc()
                : CarbonImmutable::parse((string) $row['scheduled_at_utc'], 'UTC')->utc();

            $actual = $this->nullableString($row['actual'] ?? null);
            $forecast = $this->nullableString($row['forecast'] ?? null);
            $previous = $this->nullableString($row['previous'] ?? null);
            $revisedPrevious = $this->nullableString($row['revised_previous'] ?? null);
            $impact = (string) ($row['impact'] ?? EconomicEvent::IMPACT_MEDIUM);
            $status = $this->statusFor($scheduledAt, $actual, $revisedPrevious, $syncedAt);

            $attributes = [
                'economic_news_source_id' => $source->getKey(),
                'provider_event_id' => $providerEventId,
                'origin' => 'API',
                'title' => (string) $row['title'],
                'category' => $this->nullableString($row['category'] ?? null),
                'country_code' => $this->nullableString($row['country_code'] ?? null),
                'currency' => $this->nullableString($row['currency'] ?? null),
                'impact' => $impact,
                'scheduled_at_utc' => $scheduledAt,
                'source_timezone' => 'UTC',
                'status' => $status,
                'actual' => $actual,
                'forecast' => $forecast,
                'previous' => $previous,
                'revised_previous' => $revisedPrevious,
                'last_synced_at' => $syncedAt,
                'source_payload_json' => (array) ($row['provider_payload'] ?? []),
            ];

            if ($actual !== null && $event->released_at === null) {
                $attributes['released_at'] = $scheduledAt;
            }

            if ($isNew) {
                $autoPublish = (bool) config('economic_news.sync.auto_publish', false);
                $attributes += [
                    'bias' => EconomicEvent::BIAS_NEUTRAL,
                    'confidence_score' => 0,
                    'volatility' => $this->volatilityForImpact($impact),
                    'reason' => null,
                    'analysis' => null,
                    'is_published' => $autoPublish,
                    'published_at' => $autoPublish ? $syncedAt : null,
                    'created_by_admin_id' => null,
                    'updated_by_admin_id' => null,
                ];
            }

            $event->forceFill($attributes)->save();

            $this->syncAutoPairs($event, $beforeCurrency);

            if (! $isNew) {
                $this->createProviderUpdates(
                    $event,
                    $source,
                    $row,
                    $beforeActual,
                    $beforePrevious,
                    $beforeRevisedPrevious,
                    $syncedAt
                );
            }

            if ($isNew) {
                return 'created';
            }

            return $event->wasChanged() ? 'updated' : 'unchanged';
        }, 3);
    }

    private function statusFor(
        CarbonImmutable $scheduledAt,
        ?string $actual,
        ?string $revisedPrevious,
        CarbonImmutable $now
    ): string {
        if ($actual !== null && $revisedPrevious !== null) {
            return EconomicEvent::STATUS_REVISED;
        }

        if ($actual !== null) {
            return EconomicEvent::STATUS_RELEASED;
        }

        if (
            $scheduledAt->lessThanOrEqualTo($now)
            && $scheduledAt->greaterThanOrEqualTo($now->subMinutes(30))
        ) {
            return EconomicEvent::STATUS_LIVE;
        }

        return EconomicEvent::STATUS_SCHEDULED;
    }

    private function volatilityForImpact(string $impact): string
    {
        return match ($impact) {
            EconomicEvent::IMPACT_HIGH => EconomicEvent::VOLATILITY_HIGH,
            EconomicEvent::IMPACT_LOW => EconomicEvent::VOLATILITY_LOW,
            default => EconomicEvent::VOLATILITY_MEDIUM,
        };
    }

    private function syncAutoPairs(EconomicEvent $event, ?string $beforeCurrency): void
    {
        $currency = strtoupper(trim((string) $event->currency));
        if ($currency === '') {
            return;
        }

        $catalog = (array) config('tradex_screener.forex.symbols', []);
        $symbols = [];

        foreach ($catalog as $row) {
            if (! is_array($row) || ! (bool) ($row['enabled'] ?? true)) {
                continue;
            }

            $base = strtoupper((string) ($row['base_currency'] ?? ''));
            $quote = strtoupper((string) ($row['quote_currency'] ?? ''));
            $symbol = strtoupper((string) ($row['symbol'] ?? ''));

            if ($symbol !== '' && ($base === $currency || $quote === $currency)) {
                $symbols[] = $symbol;
            }
        }

        $symbols = array_values(array_unique($symbols));

        if ($beforeCurrency !== null && strtoupper($beforeCurrency) !== $currency) {
            $event->pairs()
                ->where('mapping_source', 'AUTO')
                ->whereNotIn('symbol', $symbols === [] ? ['__NONE__'] : $symbols)
                ->delete();
        }

        foreach ($symbols as $index => $symbol) {
            if ($event->pairs()->where('market', 'FOREX')->where('symbol', $symbol)->exists()) {
                continue;
            }

            $event->pairs()->create([
                'market' => 'FOREX',
                'symbol' => $symbol,
                'bias' => EconomicEvent::BIAS_NEUTRAL,
                'confidence_score' => 0,
                'volatility' => $this->volatilityForImpact((string) $event->impact),
                'reason' => sprintf(
                    'Auto-mapped from %s macro exposure; directional bias is pending TradeX intelligence analysis.',
                    $currency
                ),
                'mapping_source' => 'AUTO',
                'is_primary' => false,
                'sort_order' => ($index + 1) * 10,
            ]);
        }
    }

    /** @param array<string, mixed> $row */
    private function createProviderUpdates(
        EconomicEvent $event,
        EconomicNewsSource $source,
        array $row,
        ?string $beforeActual,
        ?string $beforePrevious,
        ?string $beforeRevisedPrevious,
        CarbonImmutable $syncedAt
    ): void {
        $actual = $this->nullableString($row['actual'] ?? null);
        $previous = $this->nullableString($row['previous'] ?? null);
        $revisedPrevious = $this->nullableString($row['revised_previous'] ?? null);
        $providerLastUpdate = (string) ($row['provider_last_update_utc'] ?? '');

        if ($beforeActual === null && $actual !== null) {
            $this->storeUpdate(
                event: $event,
                source: $source,
                type: EconomicEventUpdate::TYPE_RELEASE,
                fingerprint: implode('|', ['release', $actual, $providerLastUpdate]),
                message: $this->releaseMessage($event, $actual, $event->forecast, $previous),
                row: $row,
                syncedAt: $syncedAt
            );
        }

        $revisionChanged = $actual !== null && (
            ($revisedPrevious !== null && $revisedPrevious !== $beforeRevisedPrevious)
            || ($beforePrevious !== null && $previous !== null && $previous !== $beforePrevious)
        );

        if ($revisionChanged) {
            $this->storeUpdate(
                event: $event,
                source: $source,
                type: EconomicEventUpdate::TYPE_REVISION,
                fingerprint: implode('|', ['revision', (string) $previous, (string) $revisedPrevious, $providerLastUpdate]),
                message: sprintf('%s previous value was revised to %s.', $event->title, $previous ?? $revisedPrevious ?? 'updated value'),
                row: $row,
                syncedAt: $syncedAt
            );
        }
    }

    /** @param array<string, mixed> $row */
    private function storeUpdate(
        EconomicEvent $event,
        EconomicNewsSource $source,
        string $type,
        string $fingerprint,
        string $message,
        array $row,
        CarbonImmutable $syncedAt
    ): void {
        $providerUpdateId = sprintf(
            '%s:%s:%s',
            $event->provider_event_id,
            strtolower($type),
            substr(sha1($fingerprint), 0, 24)
        );

        EconomicEventUpdate::query()->firstOrCreate(
            [
                'economic_news_source_id' => $source->getKey(),
                'provider_update_id' => $providerUpdateId,
            ],
            [
                'economic_event_id' => $event->getKey(),
                'update_type' => $type,
                'message' => $message,
                'actual' => $event->actual,
                'forecast' => $event->forecast,
                'previous' => $event->previous,
                'bias' => null,
                'confidence_score' => null,
                'volatility' => null,
                'is_published' => (bool) $event->is_published,
                'published_at' => $syncedAt,
                'source_payload_json' => (array) ($row['provider_payload'] ?? []),
                'created_by_admin_id' => null,
            ]
        );
    }

    private function releaseMessage(
        EconomicEvent $event,
        string $actual,
        ?string $forecast,
        ?string $previous
    ): string {
        $parts = [sprintf('%s released: Actual %s', $event->title, $actual)];

        if ($forecast !== null) {
            $parts[] = 'Forecast '.$forecast;
        }

        if ($previous !== null) {
            $parts[] = 'Previous '.$previous;
        }

        return implode('; ', $parts).'.';
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
