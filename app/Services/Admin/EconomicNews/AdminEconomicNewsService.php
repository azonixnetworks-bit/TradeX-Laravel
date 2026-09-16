<?php

namespace App\Services\Admin\EconomicNews;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\EconomicEvent;
use App\Models\EconomicEventOverride;
use App\Models\EconomicEventUpdate;
use App\Models\EconomicNewsSource;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminEconomicNewsService
{
    public function createEvent(AdminUser $admin, array $data, Request $request): EconomicEvent
    {
        $pairs = (array) ($data['pairs'] ?? []);
        $this->guardPublishChange($admin, false, (bool) $data['is_published']);
        $this->guardPairPayload($admin, $pairs);

        if ($this->canManagePairs($admin)) {
            $this->validatePairCatalog($pairs);
        }

        return DB::transaction(function () use ($admin, $data, $pairs, $request): EconomicEvent {
            $event = EconomicEvent::query()->create(
                $this->eventAttributes($data, $admin, null)
            );

            if ($this->canManagePairs($admin)) {
                $this->syncPairs($event, $pairs);
            }

            $event->load('pairs');

            $this->audit(
                $admin,
                $request,
                'ECONOMIC_EVENT_CREATED',
                'ECONOMIC_EVENT',
                $event->getKey(),
                null,
                $this->eventSnapshot($event)
            );

            return $event->fresh(['source', 'pairs', 'activeOverride']);
        }, 3);
    }

    public function updateEvent(
        EconomicEvent $event,
        AdminUser $admin,
        array $data,
        Request $request
    ): EconomicEvent {
        $pairs = (array) ($data['pairs'] ?? []);
        $this->guardPairPayload($admin, $pairs);

        if ($this->canManagePairs($admin)) {
            $this->validatePairCatalog($pairs);
        }

        return DB::transaction(function () use ($event, $admin, $data, $pairs, $request): EconomicEvent {
            $locked = EconomicEvent::query()
                ->whereKey($event->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $locked->load('pairs');
            $this->guardPublishChange(
                $admin,
                (bool) $locked->is_published,
                (bool) $data['is_published']
            );

            $before = $this->eventSnapshot($locked);

            $locked->forceFill(
                $this->eventAttributes($data, $admin, $locked)
            )->save();

            if ($this->canManagePairs($admin)) {
                $this->syncPairs($locked, $pairs);
            }

            $locked->load('pairs');

            $this->audit(
                $admin,
                $request,
                'ECONOMIC_EVENT_UPDATED',
                'ECONOMIC_EVENT',
                $locked->getKey(),
                $before,
                $this->eventSnapshot($locked)
            );

            return $locked->fresh(['source', 'pairs', 'activeOverride']);
        }, 3);
    }

    public function deleteEvent(EconomicEvent $event, AdminUser $admin, Request $request): void
    {
        DB::transaction(function () use ($event, $admin, $request): void {
            $locked = EconomicEvent::query()
                ->whereKey($event->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $locked->load('pairs');

            if (
                $locked->is_published
                || $locked->published_at !== null
                || $locked->updates()->exists()
                || $locked->overrides()->exists()
            ) {
                throw ValidationException::withMessages([
                    'event' => 'Published events or events with intelligence history cannot be deleted. Cancel or unpublish the event instead.',
                ]);
            }

            $before = $this->eventSnapshot($locked);
            $targetId = $locked->getKey();
            $locked->delete();

            $this->audit(
                $admin,
                $request,
                'ECONOMIC_EVENT_DELETED',
                'ECONOMIC_EVENT',
                $targetId,
                $before,
                null
            );
        }, 3);
    }

    public function createSource(AdminUser $admin, array $data, Request $request): EconomicNewsSource
    {
        return DB::transaction(function () use ($admin, $data, $request): EconomicNewsSource {
            $source = EconomicNewsSource::query()->create([
                ...$this->sourceAttributes($data),
                'created_by_admin_id' => $admin->getKey(),
                'updated_by_admin_id' => $admin->getKey(),
            ]);

            $this->audit(
                $admin,
                $request,
                'ECONOMIC_NEWS_SOURCE_CREATED',
                'ECONOMIC_NEWS_SOURCE',
                $source->getKey(),
                null,
                $this->sourceSnapshot($source)
            );

            return $source;
        }, 3);
    }

    public function updateSource(
        EconomicNewsSource $source,
        AdminUser $admin,
        array $data,
        Request $request
    ): EconomicNewsSource {
        return DB::transaction(function () use ($source, $admin, $data, $request): EconomicNewsSource {
            $locked = EconomicNewsSource::query()
                ->whereKey($source->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $before = $this->sourceSnapshot($locked);
            $locked->forceFill([
                ...$this->sourceAttributes($data),
                'updated_by_admin_id' => $admin->getKey(),
            ])->save();

            $this->audit(
                $admin,
                $request,
                'ECONOMIC_NEWS_SOURCE_UPDATED',
                'ECONOMIC_NEWS_SOURCE',
                $locked->getKey(),
                $before,
                $this->sourceSnapshot($locked)
            );

            return $locked->fresh();
        }, 3);
    }

    public function deleteSource(EconomicNewsSource $source, AdminUser $admin, Request $request): void
    {
        DB::transaction(function () use ($source, $admin, $request): void {
            $locked = EconomicNewsSource::query()
                ->whereKey($source->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->events()->exists()) {
                throw ValidationException::withMessages([
                    'source' => 'A news source with linked economic events cannot be deleted. Disable the source instead.',
                ]);
            }

            $before = $this->sourceSnapshot($locked);
            $targetId = $locked->getKey();
            $locked->delete();

            $this->audit(
                $admin,
                $request,
                'ECONOMIC_NEWS_SOURCE_DELETED',
                'ECONOMIC_NEWS_SOURCE',
                $targetId,
                $before,
                null
            );
        }, 3);
    }

    public function createOverride(
        EconomicEvent $event,
        AdminUser $admin,
        array $data,
        Request $request
    ): EconomicEventOverride {
        return DB::transaction(function () use ($event, $admin, $data, $request): EconomicEventOverride {
            $lockedEvent = EconomicEvent::query()
                ->whereKey($event->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $before = $lockedEvent->fresh('activeOverride')->effectiveIntelligence();

            EconomicEventOverride::query()
                ->where('economic_event_id', $lockedEvent->getKey())
                ->where('is_active', true)
                ->lockForUpdate()
                ->get()
                ->each(function (EconomicEventOverride $override) use ($admin): void {
                    $override->forceFill([
                        'is_active' => false,
                        'revoked_at' => now(),
                        'revoked_by_admin_id' => $admin->getKey(),
                    ])->save();
                });

            $override = EconomicEventOverride::query()->create([
                'economic_event_id' => $lockedEvent->getKey(),
                'impact' => $data['impact'] ?? null,
                'status' => $data['status'] ?? null,
                'bias' => $data['bias'] ?? null,
                'confidence_score' => $data['confidence_score'] ?? null,
                'volatility' => $data['volatility'] ?? null,
                'reason' => $data['reason'] ?? null,
                'analysis' => $data['analysis'] ?? null,
                'override_note' => $data['override_note'],
                'is_active' => true,
                'starts_at' => now(),
                'expires_at' => filled($data['expires_at'] ?? null)
                    ? CarbonImmutable::parse((string) $data['expires_at'], 'UTC')->utc()
                    : null,
                'overridden_by_admin_id' => $admin->getKey(),
            ]);

            $after = $lockedEvent->fresh('activeOverride')->effectiveIntelligence();

            $this->audit(
                $admin,
                $request,
                'ECONOMIC_EVENT_OVERRIDE_CREATED',
                'ECONOMIC_EVENT',
                $lockedEvent->getKey(),
                $before,
                [
                    ...$after,
                    'override_note' => $override->override_note,
                    'expires_at' => $override->expires_at?->toIso8601String(),
                ]
            );

            return $override->fresh();
        }, 3);
    }

    public function revokeOverride(
        EconomicEvent $event,
        EconomicEventOverride $override,
        AdminUser $admin,
        Request $request
    ): void {
        DB::transaction(function () use ($event, $override, $admin, $request): void {
            $lockedEvent = EconomicEvent::query()
                ->whereKey($event->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $locked = EconomicEventOverride::query()
                ->whereKey($override->getKey())
                ->where('economic_event_id', $lockedEvent->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->is_active) {
                throw ValidationException::withMessages([
                    'override' => 'This manual override is already inactive.',
                ]);
            }

            $before = $lockedEvent->fresh('activeOverride')->effectiveIntelligence();
            $locked->forceFill([
                'is_active' => false,
                'revoked_at' => now(),
                'revoked_by_admin_id' => $admin->getKey(),
            ])->save();
            $after = $lockedEvent->fresh('activeOverride')->effectiveIntelligence();

            $this->audit(
                $admin,
                $request,
                'ECONOMIC_EVENT_OVERRIDE_REVOKED',
                'ECONOMIC_EVENT',
                $lockedEvent->getKey(),
                $before,
                $after
            );
        }, 3);
    }

    public function createLiveUpdate(
        EconomicEvent $event,
        AdminUser $admin,
        array $data,
        Request $request
    ): EconomicEventUpdate {
        return DB::transaction(function () use ($event, $admin, $data, $request): EconomicEventUpdate {
            $locked = EconomicEvent::query()
                ->whereKey($event->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $before = $this->eventSnapshot($locked->load('pairs'));
            $publishedAt = now();

            $update = EconomicEventUpdate::query()->create([
                'economic_event_id' => $locked->getKey(),
                'economic_news_source_id' => $locked->economic_news_source_id,
                'update_type' => $data['update_type'],
                'message' => $data['message'],
                'actual' => $data['actual'] ?? null,
                'forecast' => $data['forecast'] ?? null,
                'previous' => $data['previous'] ?? null,
                'bias' => $data['bias'] ?? null,
                'confidence_score' => $data['confidence_score'] ?? null,
                'volatility' => $data['volatility'] ?? null,
                'is_published' => (bool) $data['is_published'],
                'published_at' => $publishedAt,
                'created_by_admin_id' => $admin->getKey(),
            ]);

            $eventChanges = [];

            foreach (['actual', 'forecast', 'previous', 'bias', 'confidence_score', 'volatility'] as $field) {
                if (array_key_exists($field, $data) && $data[$field] !== null && $data[$field] !== '') {
                    $eventChanges[$field] = $data[$field];
                }
            }

            if ($data['update_type'] === EconomicEventUpdate::TYPE_LIVE) {
                $eventChanges['status'] = EconomicEvent::STATUS_LIVE;
            } elseif ($data['update_type'] === EconomicEventUpdate::TYPE_RELEASE) {
                $eventChanges['status'] = EconomicEvent::STATUS_RELEASED;
                $eventChanges['released_at'] = $publishedAt;
            } elseif ($data['update_type'] === EconomicEventUpdate::TYPE_REVISION) {
                $eventChanges['status'] = EconomicEvent::STATUS_REVISED;
            }

            if ($eventChanges !== []) {
                $eventChanges['updated_by_admin_id'] = $admin->getKey();
                $locked->forceFill($eventChanges)->save();
            }

            $this->audit(
                $admin,
                $request,
                'ECONOMIC_EVENT_LIVE_UPDATE_CREATED',
                'ECONOMIC_EVENT_UPDATE',
                $update->getKey(),
                $before,
                [
                    ...$this->eventSnapshot($locked->fresh('pairs')),
                    'update_uuid' => $update->uuid,
                    'update_type' => $update->update_type,
                    'is_published' => $update->is_published,
                ]
            );

            return $update->fresh(['event', 'source']);
        }, 3);
    }

    private function guardPublishChange(AdminUser $admin, bool $before, bool $after): void
    {
        if ($before === $after) {
            return;
        }

        abort_unless(
            $admin->isSuperAdmin() || $admin->hasPermission('economic_news.publish'),
            403
        );
    }

    private function guardPairPayload(AdminUser $admin, array $pairs): void
    {
        if ($pairs === [] || $this->canManagePairs($admin)) {
            return;
        }

        abort(403);
    }

    private function canManagePairs(AdminUser $admin): bool
    {
        return $admin->isSuperAdmin() || $admin->hasPermission('economic_news.pairs.manage');
    }

    /** @return array<string, mixed> */
    private function eventAttributes(array $data, AdminUser $admin, ?EconomicEvent $event): array
    {
        $sourceId = null;

        if (filled($data['source_uuid'] ?? null)) {
            $sourceId = EconomicNewsSource::query()
                ->where('uuid', $data['source_uuid'])
                ->value('id');
        }

        $publishedAt = $event?->published_at;

        if ((bool) $data['is_published'] && $publishedAt === null) {
            $publishedAt = now();
        }

        return [
            'economic_news_source_id' => $sourceId,
            'origin' => 'MANUAL',
            'title' => $data['title'],
            'category' => $data['category'] ?? null,
            'country_code' => $data['country_code'] ?? null,
            'currency' => $data['currency'] ?? null,
            'impact' => $data['impact'],
            'scheduled_at_utc' => CarbonImmutable::parse((string) $data['scheduled_at_utc'], 'UTC')->utc(),
            'source_timezone' => 'UTC',
            'status' => $data['status'],
            'actual' => $data['actual'] ?? null,
            'forecast' => $data['forecast'] ?? null,
            'previous' => $data['previous'] ?? null,
            'revised_previous' => $data['revised_previous'] ?? null,
            'bias' => $data['bias'],
            'confidence_score' => (int) $data['confidence_score'],
            'volatility' => $data['volatility'],
            'reason' => $data['reason'] ?? null,
            'analysis' => $data['analysis'] ?? null,
            'is_published' => (bool) $data['is_published'],
            'published_at' => $publishedAt,
            'created_by_admin_id' => $event?->created_by_admin_id ?? $admin->getKey(),
            'updated_by_admin_id' => $admin->getKey(),
        ];
    }

    /** @return array<string, mixed> */
    private function sourceAttributes(array $data): array
    {
        return [
            'code' => $data['code'],
            'name' => $data['name'],
            'source_type' => $data['source_type'],
            'homepage_url' => $data['homepage_url'] ?? null,
            'is_enabled' => (bool) $data['is_enabled'],
            'priority' => (int) $data['priority'],
            'reliability_weight' => $data['reliability_weight'],
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function syncPairs(EconomicEvent $event, array $pairs): void
    {
        $event->pairs()->delete();

        foreach ($pairs as $index => $pair) {
            $event->pairs()->create([
                'market' => $pair['market'],
                'symbol' => $pair['symbol'],
                'bias' => $pair['bias'],
                'confidence_score' => (int) $pair['confidence_score'],
                'volatility' => $pair['volatility'],
                'reason' => $pair['reason'] ?? null,
                'mapping_source' => 'MANUAL',
                'is_primary' => (bool) $pair['is_primary'],
                'sort_order' => (int) ($pair['sort_order'] ?? (($index + 1) * 10)),
            ]);
        }
    }

    private function validatePairCatalog(array $pairs): void
    {
        foreach ($pairs as $index => $pair) {
            $market = strtoupper((string) ($pair['market'] ?? ''));
            $symbol = strtoupper((string) ($pair['symbol'] ?? ''));

            if ($market === 'STOCKS') {
                continue;
            }

            $catalog = (array) config('tradex_screener.'.strtolower($market).'.symbols', []);
            $symbols = array_values(array_filter(array_map(
                static fn (mixed $row): string => is_array($row) ? strtoupper((string) ($row['symbol'] ?? '')) : '',
                $catalog
            )));

            if ($symbols !== [] && ! in_array($symbol, $symbols, true)) {
                throw ValidationException::withMessages([
                    "pairs.$index.symbol" => "The symbol $symbol is not in the approved TradeX $market screener catalog.",
                ]);
            }
        }
    }

    /** @return array<string, mixed> */
    private function eventSnapshot(EconomicEvent $event): array
    {
        return [
            'uuid' => $event->uuid,
            'source_id' => $event->economic_news_source_id,
            'title' => $event->title,
            'category' => $event->category,
            'country_code' => $event->country_code,
            'currency' => $event->currency,
            'impact' => $event->impact,
            'scheduled_at_utc' => $event->scheduled_at_utc?->utc()->toIso8601String(),
            'status' => $event->status,
            'actual' => $event->actual,
            'forecast' => $event->forecast,
            'previous' => $event->previous,
            'bias' => $event->bias,
            'confidence_score' => (int) $event->confidence_score,
            'volatility' => $event->volatility,
            'is_published' => (bool) $event->is_published,
            'published_at' => $event->published_at?->utc()->toIso8601String(),
            'pairs' => $event->pairs->map(fn ($pair): array => [
                'market' => $pair->market,
                'symbol' => $pair->symbol,
                'bias' => $pair->bias,
                'confidence_score' => (int) $pair->confidence_score,
                'volatility' => $pair->volatility,
                'is_primary' => (bool) $pair->is_primary,
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function sourceSnapshot(EconomicNewsSource $source): array
    {
        return [
            'uuid' => $source->uuid,
            'code' => $source->code,
            'name' => $source->name,
            'source_type' => $source->source_type,
            'homepage_url' => $source->homepage_url,
            'is_enabled' => (bool) $source->is_enabled,
            'priority' => (int) $source->priority,
            'reliability_weight' => (string) $source->reliability_weight,
        ];
    }

    private function audit(
        AdminUser $admin,
        Request $request,
        string $action,
        string $targetType,
        int $targetId,
        ?array $oldValues,
        ?array $newValues
    ): void {
        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->getKey(),
            action: $action,
            module: 'ECONOMIC_NEWS',
            targetType: $targetType,
            targetId: $targetId,
            oldValues: $oldValues,
            newValues: $newValues,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );
    }
}
