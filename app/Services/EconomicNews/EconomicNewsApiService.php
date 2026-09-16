<?php

namespace App\Services\EconomicNews;

use App\Models\EconomicEvent;
use App\Models\EconomicEventUpdate;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class EconomicNewsApiService
{
    public function events(array $filters, ?string $forcedImpact = null, bool $calendarWindow = false): LengthAwarePaginator
    {
        $query = EconomicEvent::query()
            ->where('is_published', true)
            ->with([
                'source:id,uuid,code,name',
                'pairs',
                'activeOverride',
            ])
            ->withCount([
                'updates' => fn (Builder $query) => $query->where('is_published', true),
            ]);

        $impact = $forcedImpact ?? ($filters['impact'] ?? null);

        if ($impact !== null) {
            $this->applyEffectiveImpactFilter($query, $impact);
        }

        if (filled($filters['currency'] ?? null)) {
            $query->where('currency', $filters['currency']);
        }

        if (filled($filters['status'] ?? null)) {
            $status = (string) $filters['status'];
            $this->applyEffectiveStatusFilter($query, $status);
        }

        if (filled($filters['symbol'] ?? null)) {
            $symbol = (string) $filters['symbol'];
            $query->whereHas('pairs', fn (Builder $pair) => $pair->where('symbol', $symbol));
        }

        if ($calendarWindow && blank($filters['from'] ?? null) && blank($filters['to'] ?? null)) {
            $query->whereBetween('scheduled_at_utc', [
                now()->subDays(7)->startOfDay(),
                now()->addDays(60)->endOfDay(),
            ]);
        } else {
            if (filled($filters['from'] ?? null)) {
                $query->where('scheduled_at_utc', '>=', CarbonImmutable::parse((string) $filters['from'], 'UTC')->utc());
            }

            if (filled($filters['to'] ?? null)) {
                $query->where('scheduled_at_utc', '<=', CarbonImmutable::parse((string) $filters['to'], 'UTC')->utc());
            }
        }

        return $query
            ->orderBy('scheduled_at_utc')
            ->paginate((int) ($filters['per_page'] ?? 30))
            ->withQueryString();
    }

    public function liveUpdates(array $filters): LengthAwarePaginator
    {
        $query = EconomicEventUpdate::query()
            ->where('is_published', true)
            ->whereHas('event', fn (Builder $event) => $event->where('is_published', true))
            ->with([
                'source:id,uuid,code,name',
                'event' => fn ($query) => $query->with(['pairs', 'activeOverride']),
            ]);

        if (filled($filters['symbol'] ?? null)) {
            $symbol = (string) $filters['symbol'];
            $query->whereHas('event.pairs', fn (Builder $pair) => $pair->where('symbol', $symbol));
        }

        if (filled($filters['currency'] ?? null)) {
            $currency = (string) $filters['currency'];
            $query->whereHas('event', fn (Builder $event) => $event->where('currency', $currency));
        }

        if (filled($filters['from'] ?? null)) {
            $query->where('published_at', '>=', CarbonImmutable::parse((string) $filters['from'], 'UTC')->utc());
        }

        if (filled($filters['to'] ?? null)) {
            $query->where('published_at', '<=', CarbonImmutable::parse((string) $filters['to'], 'UTC')->utc());
        }

        return $query
            ->latest('published_at')
            ->paginate((int) ($filters['per_page'] ?? 40))
            ->withQueryString();
    }

    public function publishedEvent(EconomicEvent $event): EconomicEvent
    {
        abort_unless($event->is_published, 404);

        return $event->load([
            'source:id,uuid,code,name',
            'pairs',
            'activeOverride',
            'updates' => fn ($query) => $query->where('is_published', true)->limit(100),
        ]);
    }

    /** @return array<string, mixed> */
    public function summary(EconomicEvent $event): array
    {
        $effective = $event->effectiveIntelligence();

        return [
            'uuid' => $event->uuid,
            'title' => $event->title,
            'category' => $event->category,
            'country_code' => $event->country_code,
            'currency' => $event->currency,
            'scheduled_at_utc' => $event->scheduled_at_utc?->utc()->toIso8601String(),
            'released_at_utc' => $event->released_at?->utc()->toIso8601String(),
            'impact' => $effective['impact'],
            'status' => $effective['status'],
            'actual' => $event->actual,
            'forecast' => $event->forecast,
            'previous' => $event->previous,
            'revised_previous' => $event->revised_previous,
            'bias' => $effective['bias'],
            'confidence_score' => $effective['confidence_score'],
            'volatility' => $effective['volatility'],
            'reason' => $effective['reason'],
            'source' => $event->source === null ? null : [
                'code' => $event->source->code,
                'name' => $event->source->name,
            ],
            'affected_pairs' => $event->pairs->map(fn ($pair): array => [
                'market' => $pair->market,
                'symbol' => $pair->symbol,
                'bias' => $pair->bias,
                'confidence_score' => (int) $pair->confidence_score,
                'volatility' => $pair->volatility,
                'reason' => $pair->reason,
                'is_primary' => (bool) $pair->is_primary,
            ])->values(),
            'intelligence_provenance' => [
                'manual_override' => $effective['manual_override'],
                'mode' => $effective['manual_override'] ? 'ADMIN_OVERRIDE' : 'SOURCE_INTELLIGENCE',
            ],
            'published_at_utc' => $event->published_at?->utc()->toIso8601String(),
            'published_updates_count' => isset($event->updates_count) ? (int) $event->updates_count : null,
        ];
    }

    /** @return array<string, mixed> */
    public function detail(EconomicEvent $event): array
    {
        $summary = $this->summary($event);
        $effective = $event->effectiveIntelligence();

        return [
            ...$summary,
            'analysis' => $effective['analysis'],
            'updates' => $event->updates->map(fn (EconomicEventUpdate $update): array => $this->liveUpdate($update))->values(),
        ];
    }

    /** @return array<string, mixed> */
    public function liveUpdate(EconomicEventUpdate $update): array
    {
        $event = $update->event;
        $effective = $event?->effectiveIntelligence();

        return [
            'uuid' => $update->uuid,
            'event_uuid' => $event?->uuid,
            'event_title' => $event?->title,
            'update_type' => $update->update_type,
            'message' => $update->message,
            'actual' => $update->actual,
            'forecast' => $update->forecast,
            'previous' => $update->previous,
            'bias' => $update->bias ?? ($effective['bias'] ?? null),
            'confidence_score' => $update->confidence_score ?? ($effective['confidence_score'] ?? null),
            'volatility' => $update->volatility ?? ($effective['volatility'] ?? null),
            'impact' => $effective['impact'] ?? $event?->impact,
            'affected_pairs' => $event?->pairs->map(fn ($pair): array => [
                'market' => $pair->market,
                'symbol' => $pair->symbol,
                'bias' => $pair->bias,
            ])->values() ?? [],
            'source' => $update->source === null ? null : [
                'code' => $update->source->code,
                'name' => $update->source->name,
            ],
            'published_at_utc' => $update->published_at?->utc()->toIso8601String(),
        ];
    }

    private function applyEffectiveImpactFilter(Builder $query, string $impact): void
    {
        $this->applyEffectiveStringFilter($query, 'impact', $impact);
    }

    private function applyEffectiveStatusFilter(Builder $query, string $status): void
    {
        $this->applyEffectiveStringFilter($query, 'status', $status);
    }

    private function applyEffectiveStringFilter(Builder $query, string $field, string $value): void
    {
        $query->where(function (Builder $query) use ($field, $value): void {
            $query
                ->whereHas('activeOverride', fn (Builder $override) => $override->where($field, $value))
                ->orWhere(function (Builder $event) use ($field, $value): void {
                    $event
                        ->where($field, $value)
                        ->where(function (Builder $effective) use ($field): void {
                            $effective
                                ->whereDoesntHave('activeOverride')
                                ->orWhereHas('activeOverride', fn (Builder $override) => $override->whereNull($field));
                        });
                });
        });
    }
}
