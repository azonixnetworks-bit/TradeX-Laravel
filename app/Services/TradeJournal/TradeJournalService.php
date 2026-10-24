<?php

namespace App\Services\TradeJournal;

use App\Models\TradeJournalEntry;
use App\Models\User;
use App\Services\MarketClock\MarketClockService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TradeJournalService
{
    public function __construct(
        private readonly MarketClockService $marketClock,
        private readonly TradeJournalRetentionService $retention
    ) {
    }

    public function timezone(?string $timezone): string
    {
        return $this->marketClock->normalizeTimezone($timezone ?: 'UTC');
    }

    public function localToUtc(string $date, string $time, string $timezone): CarbonImmutable
    {
        $timezone = $this->timezone($timezone);

        try {
            return CarbonImmutable::createFromFormat(
                'Y-m-d H:i:s',
                $date.' '.(strlen($time) === 5 ? $time.':00' : $time),
                $timezone
            )->setTimezone('UTC');
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'trade_time' => ['Invalid local date/time.'],
            ]);
        }
    }

    public function owned(User $user, string $uuid): TradeJournalEntry
    {
        return TradeJournalEntry::query()
            ->where('user_id', $user->getKey())
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    public function create(User $user, array $data): array
    {
        // Keep the customer's private journal bounded even if the scheduler
        // has not run yet. The daily command performs the same cleanup globally.
        $this->retention->pruneExpired($user);

        $timezone = $this->timezone($data['timezone'] ?? null);
        $tradeAt = $this->localToUtc($data['trade_date'], $data['trade_time'], $timezone);
        $status = $data['status'];

        if ($status === 'open' && $tradeAt->isFuture()) {
            throw ValidationException::withMessages([
                'trade_time' => ['An open trade cannot start in the future.'],
            ]);
        }

        $session = $this->resolveSession(
            $timezone,
            $tradeAt,
            $data['session_label'] ?? null
        );

        $payload = $this->basePayload($data, $timezone, $tradeAt, $session);
        $payload['uuid'] = (string) Str::uuid();
        $payload['status'] = $status;
        $payload['symbol'] = $this->upper($data['symbol']);
        $payload['risk_currency'] = $this->upperNullable($data['risk_currency'] ?? null);
        $payload['current_sl'] = $data['current_sl'] ?? $data['planned_sl'] ?? null;
        $payload['current_tp'] = $data['current_tp'] ?? $data['planned_tp'] ?? null;
        $payload['planned_rr'] = $this->plannedRr(
            $data['direction'],
            $data['planned_entry'] ?? $data['actual_entry'] ?? null,
            $data['planned_sl'] ?? null,
            $data['planned_tp'] ?? null
        );

        if ($status === 'open') {
            $payload['opened_at'] = $tradeAt;
        }

        $entry = DB::transaction(function () use ($user, $payload): TradeJournalEntry {
            $entry = new TradeJournalEntry($payload);
            $entry->user()->associate($user);
            $entry->save();

            return $entry->fresh();
        });

        return [
            'entry' => $entry,
            'warnings' => $this->disciplineWarnings($user, $entry),
        ];
    }

    public function update(User $user, TradeJournalEntry $entry, array $data): array
    {
        $this->assertOwner($user, $entry);

        if (! in_array($entry->status, ['planned', 'open'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Closed or cancelled journal entries are read-only.'],
            ]);
        }

        $allowed = $entry->status === 'planned'
            ? [
                'market', 'stock_region', 'symbol', 'direction', 'session_label',
                'broker', 'account_label', 'setup_strategy', 'timeframes',
                'entry_reason', 'notes_before', 'planned_entry', 'planned_sl',
                'planned_tp', 'position_size', 'risk_amount', 'risk_currency',
                'risk_percent', 'emotion_before', 'emotion_before_other',
                'confidence_before', 'mental_state_note', 'checklist',
            ]
            : [
                'session_label', 'broker', 'account_label', 'setup_strategy',
                'timeframes', 'notes_before', 'current_sl', 'current_tp',
                'position_size', 'risk_amount', 'risk_currency', 'risk_percent',
                'execution_note', 'emotion_before', 'emotion_before_other',
                'confidence_before', 'mental_state_note', 'checklist',
            ];

        $entry->fill(array_intersect_key($data, array_flip($allowed)));

        if (array_key_exists('symbol', $data)) {
            $entry->symbol = $this->upper($data['symbol']);
        }

        if (array_key_exists('risk_currency', $data)) {
            $entry->risk_currency = $this->upperNullable($data['risk_currency']);
        }

        if (($data['emotion_before'] ?? $entry->emotion_before) !== 'other') {
            $entry->emotion_before_other = null;
        }

        if (array_key_exists('session_label', $data)) {
            if (filled($data['session_label'])) {
                $entry->session_label = trim((string) $data['session_label']);
                $entry->session_keys = [];
                $entry->session_source = 'manual';
            } else {
                $session = $this->resolveSession(
                    $entry->user_timezone,
                    $entry->opened_at ?: $entry->trade_at,
                    null
                );
                $entry->session_label = $session['label'];
                $entry->session_keys = $session['keys'];
                $entry->session_source = 'auto';
            }
        }

        if ($entry->status === 'planned') {
            $entry->current_sl = $data['planned_sl'] ?? $entry->planned_sl;
            $entry->current_tp = $data['planned_tp'] ?? $entry->planned_tp;
            $entry->planned_rr = $this->plannedRr(
                $data['direction'] ?? $entry->direction,
                $data['planned_entry'] ?? $entry->planned_entry,
                $data['planned_sl'] ?? $entry->planned_sl,
                $data['planned_tp'] ?? $entry->planned_tp
            );
        }

        $entry->save();

        return [
            'entry' => $entry->fresh(),
            'warnings' => $this->disciplineWarnings($user, $entry),
        ];
    }

    public function open(User $user, TradeJournalEntry $entry, array $data): array
    {
        $this->assertOwner($user, $entry);

        if (! $entry->isPlanned()) {
            throw ValidationException::withMessages([
                'status' => ['Only a planned trade can be opened.'],
            ]);
        }

        $timezone = $this->timezone($data['timezone'] ?? $entry->user_timezone);
        $openedAt = $this->localToUtc(
            $data['entry_date'],
            $data['entry_time'],
            $timezone
        );

        if ($openedAt->isFuture()) {
            throw ValidationException::withMessages([
                'entry_time' => ['Entry time cannot be in the future.'],
            ]);
        }

        $entry->fill([
            'status' => 'open',
            'actual_entry' => $data['actual_entry'],
            'opened_at' => $openedAt,
            'user_timezone' => $timezone,
            'current_sl' => $data['current_sl'] ?? $entry->planned_sl,
            'current_tp' => $data['current_tp'] ?? $entry->planned_tp,
            'position_size' => $data['position_size'] ?? $entry->position_size,
            'execution_note' => $data['execution_note'] ?? null,
        ]);

        if ($entry->session_source !== 'manual') {
            $session = $this->resolveSession($timezone, $openedAt, null);
            $entry->session_label = $session['label'];
            $entry->session_keys = $session['keys'];
            $entry->session_source = 'auto';
        }

        $entry->save();

        return [
            'entry' => $entry->fresh(),
            'warnings' => $this->disciplineWarnings($user, $entry),
        ];
    }

    public function close(User $user, TradeJournalEntry $entry, array $data): TradeJournalEntry
    {
        $this->assertOwner($user, $entry);

        if (! $entry->isOpen()) {
            throw ValidationException::withMessages([
                'status' => ['Only an open trade can be closed.'],
            ]);
        }

        $timezone = $this->timezone($data['timezone'] ?? $entry->user_timezone);
        $exitAt = $this->localToUtc(
            $data['exit_date'],
            $data['exit_time'],
            $timezone
        );
        $openedAt = $entry->opened_at ?: $entry->trade_at;

        if ($exitAt->isFuture() || $exitAt->lessThan($openedAt)) {
            throw ValidationException::withMessages([
                'exit_time' => ['Exit time must be after entry and cannot be in the future.'],
            ]);
        }

        $gross = array_key_exists('gross_pnl', $data) && $data['gross_pnl'] !== null
            ? (float) $data['gross_pnl']
            : null;
        $fees = array_key_exists('fees', $data) && $data['fees'] !== null
            ? (float) $data['fees']
            : 0.0;
        $net = $gross === null ? null : $gross - $fees;

        $entry->fill([
            'status' => 'closed',
            'exit_price' => $data['exit_price'],
            'exit_at' => $exitAt,
            'result' => $data['result'],
            'gross_pnl' => $gross === null ? null : $this->decimal($gross),
            'fees' => array_key_exists('fees', $data) ? $this->decimal($fees) : null,
            'net_pnl' => $net === null ? null : $this->decimal($net),
            'pnl_currency' => $this->upperNullable($data['pnl_currency'] ?? null),
            'pnl_percent' => $data['pnl_percent'] ?? null,
            'realized_r' => $this->realizedR($entry, $data['exit_price']),
            'emotion_after' => $data['emotion_after'] ?? null,
            'emotion_after_other' => ($data['emotion_after'] ?? null) === 'other'
                ? ($data['emotion_after_other'] ?? null)
                : null,
            'followed_plan_after' => $data['followed_plan_after'],
            'mistake_tags' => $data['mistake_tags'] ?? [],
            'went_well' => $data['went_well'] ?? null,
            'went_wrong' => $data['went_wrong'] ?? null,
            'lesson_learned' => $data['lesson_learned'] ?? null,
            'improvement_next_trade' => $data['improvement_next_trade'] ?? null,
            'rating' => $data['rating'] ?? null,
            'closed_at' => CarbonImmutable::now('UTC'),
        ]);
        $entry->save();

        return $entry->fresh();
    }

    public function cancel(
        User $user,
        TradeJournalEntry $entry,
        ?string $reason
    ): TradeJournalEntry {
        $this->assertOwner($user, $entry);

        if (! $entry->isPlanned()) {
            throw ValidationException::withMessages([
                'status' => ['Only a planned trade can be cancelled.'],
            ]);
        }

        $entry->fill([
            'status' => 'cancelled',
            'cancellation_reason' => $reason,
            'cancelled_at' => CarbonImmutable::now('UTC'),
        ])->save();

        return $entry->fresh();
    }

    public function serialize(
        TradeJournalEntry $entry,
        ?string $displayTimezone = null
    ): array {
        $timezone = $this->timezone($displayTimezone ?: $entry->user_timezone);
        $local = fn ($date) => $date
            ? $date->setTimezone($timezone)->toIso8601String()
            : null;

        return [
            'uuid' => $entry->uuid,
            'market' => $entry->market,
            'stock_region' => $entry->stock_region,
            'symbol' => $entry->symbol,
            'direction' => $entry->direction,
            'status' => $entry->status,
            'trade_at_utc' => $entry->trade_at?->setTimezone('UTC')->toIso8601String(),
            'trade_at_local' => $local($entry->trade_at),
            'display_timezone' => $timezone,
            'session' => [
                'label' => $entry->session_label,
                'keys' => $entry->session_keys ?? [],
                'source' => $entry->session_source,
            ],
            'broker' => $entry->broker,
            'account_label' => $entry->account_label,
            'setup_strategy' => $entry->setup_strategy,
            'timeframes' => $entry->timeframes ?? [],
            'entry_reason' => $entry->entry_reason,
            'notes_before' => $entry->notes_before,
            'prices' => [
                'planned_entry' => $entry->planned_entry,
                'actual_entry' => $entry->actual_entry,
                'planned_sl' => $entry->planned_sl,
                'current_sl' => $entry->current_sl,
                'planned_tp' => $entry->planned_tp,
                'current_tp' => $entry->current_tp,
                'exit_price' => $entry->exit_price,
            ],
            'risk' => [
                'position_size' => $entry->position_size,
                'risk_amount' => $entry->risk_amount,
                'risk_currency' => $entry->risk_currency,
                'risk_percent' => $entry->risk_percent,
                'planned_rr' => $entry->planned_rr,
                'realized_r' => $entry->realized_r,
            ],
            'psychology_before' => [
                'emotion' => $entry->emotion_before,
                'other' => $entry->emotion_before_other,
                'confidence' => $entry->confidence_before,
                'note' => $entry->mental_state_note,
            ],
            'checklist' => $entry->checklist ?? [],
            'execution_note' => $entry->execution_note,
            'opened_at' => $local($entry->opened_at),
            'exit_at' => $local($entry->exit_at),
            'result' => $entry->result,
            'pnl' => [
                'gross' => $entry->gross_pnl,
                'fees' => $entry->fees,
                'net' => $entry->net_pnl,
                'currency' => $entry->pnl_currency,
                'percent' => $entry->pnl_percent,
            ],
            'review' => [
                'emotion_after' => $entry->emotion_after,
                'emotion_after_other' => $entry->emotion_after_other,
                'followed_plan' => $entry->followed_plan_after,
                'mistake_tags' => $entry->mistake_tags ?? [],
                'went_well' => $entry->went_well,
                'went_wrong' => $entry->went_wrong,
                'lesson_learned' => $entry->lesson_learned,
                'improvement_next_trade' => $entry->improvement_next_trade,
                'rating' => $entry->rating,
            ],
            'screenshots' => [
                'before_available' => filled($entry->before_screenshot_path),
                'after_available' => filled($entry->after_screenshot_path),
            ],
            'actions' => [
                'can_edit' => in_array($entry->status, ['planned', 'open'], true),
                'can_open' => $entry->status === 'planned',
                'can_close' => $entry->status === 'open',
                'can_cancel' => $entry->status === 'planned',
            ],
        ];
    }

    public function disciplineWarnings(User $user, TradeJournalEntry $entry): array
    {
        if (! in_array($entry->status, ['open', 'closed'], true)) {
            return [];
        }

        $timezone = $this->timezone($entry->user_timezone);
        $localDay = ($entry->opened_at ?: $entry->trade_at)->setTimezone($timezone);
        $from = $localDay->startOfDay()->setTimezone('UTC');
        $to = $localDay->endOfDay()->setTimezone('UTC');

        $dayEntries = TradeJournalEntry::query()
            ->where('user_id', $user->getKey())
            ->whereIn('status', ['open', 'closed'])
            ->where(function ($query) use ($from, $to): void {
                $query->whereBetween('opened_at', [$from, $to])
                    ->orWhere(function ($nested) use ($from, $to): void {
                        $nested
                            ->whereNull('opened_at')
                            ->whereBetween('trade_at', [$from, $to]);
                    });
            })
            ->get();

        $warnings = [];

        if ($dayEntries->count() > (int) config(
            'trade_journal.discipline.max_trades_per_day',
            3
        )) {
            $warnings[] = 'daily_trade_limit_exceeded';
        }

        $winsBefore = $dayEntries
            ->filter(fn (TradeJournalEntry $item): bool =>
                $item->status === 'closed'
                && $item->result === 'win'
                && $item->getKey() !== $entry->getKey()
                && ($item->opened_at ?: $item->trade_at)
                    ->lessThanOrEqualTo($entry->opened_at ?: $entry->trade_at)
            )
            ->count();

        if ($winsBefore >= (int) config(
            'trade_journal.discipline.stop_after_wins',
            2
        )) {
            $warnings[] = 'trade_recorded_after_two_wins';
        }

        return array_values(array_unique($warnings));
    }

    private function basePayload(
        array $data,
        string $timezone,
        CarbonImmutable $tradeAt,
        array $session
    ): array {
        $keys = [
            'market', 'stock_region', 'symbol', 'direction', 'broker',
            'account_label', 'setup_strategy', 'timeframes', 'entry_reason',
            'notes_before', 'planned_entry', 'actual_entry', 'planned_sl',
            'planned_tp', 'position_size', 'risk_amount', 'risk_currency',
            'risk_percent', 'emotion_before', 'emotion_before_other',
            'confidence_before', 'mental_state_note', 'checklist', 'execution_note',
        ];

        return array_merge(
            array_intersect_key($data, array_flip($keys)),
            [
                'trade_at' => $tradeAt,
                'user_timezone' => $timezone,
                'session_label' => $session['label'],
                'session_keys' => $session['keys'],
                'session_source' => $session['source'],
            ]
        );
    }

    private function resolveSession(
        string $timezone,
        CarbonImmutable $tradeAt,
        ?string $manual
    ): array {
        if (filled($manual)) {
            return [
                'label' => trim((string) $manual),
                'keys' => [],
                'source' => 'manual',
            ];
        }

        $snapshot = $this->marketClock->snapshot($timezone, $tradeAt);
        $active = $snapshot['active_sessions'] ?? [];
        $names = array_values(array_filter(array_column($active, 'name')));
        $keys = array_values(array_filter(array_column($active, 'key')));

        if ($names === []) {
            return [
                'label' => 'Outside Main Sessions',
                'keys' => [],
                'source' => 'auto',
            ];
        }

        return [
            'label' => count($names) > 1
                ? implode(' / ', $names).' Overlap'
                : $names[0],
            'keys' => $keys,
            'source' => 'auto',
        ];
    }

    private function plannedRr(
        string $direction,
        mixed $entry,
        mixed $sl,
        mixed $tp
    ): ?string {
        if ($entry === null || $sl === null || $tp === null) {
            return null;
        }

        $entry = (float) $entry;
        $sl = (float) $sl;
        $tp = (float) $tp;
        $risk = strtolower($direction) === 'buy'
            ? $entry - $sl
            : $sl - $entry;
        $reward = strtolower($direction) === 'buy'
            ? $tp - $entry
            : $entry - $tp;

        return $risk > 0 && $reward > 0
            ? $this->decimal($reward / $risk)
            : null;
    }

    private function realizedR(TradeJournalEntry $entry, mixed $exit): ?string
    {
        $actualEntry = $entry->actual_entry ?? $entry->planned_entry;
        $sl = $entry->planned_sl ?? $entry->current_sl;

        if ($actualEntry === null || $sl === null || $exit === null) {
            return null;
        }

        $actualEntry = (float) $actualEntry;
        $sl = (float) $sl;
        $exit = (float) $exit;
        $risk = $entry->direction === 'buy'
            ? $actualEntry - $sl
            : $sl - $actualEntry;
        $move = $entry->direction === 'buy'
            ? $exit - $actualEntry
            : $actualEntry - $exit;

        return $risk > 0
            ? $this->decimal($move / $risk)
            : null;
    }

    private function decimal(float $value): string
    {
        return number_format($value, 8, '.', '');
    }

    private function upper(string $value): string
    {
        return Str::upper(trim($value));
    }

    private function upperNullable(mixed $value): ?string
    {
        return filled($value) ? $this->upper((string) $value) : null;
    }

    private function assertOwner(User $user, TradeJournalEntry $entry): void
    {
        abort_unless(
            (int) $entry->user_id === (int) $user->getKey(),
            404
        );
    }
}
