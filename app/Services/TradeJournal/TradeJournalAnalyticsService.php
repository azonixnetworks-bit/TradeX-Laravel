<?php

namespace App\Services\TradeJournal;

use App\Models\TradeJournalEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class TradeJournalAnalyticsService
{
    public function __construct(
        private readonly TradeJournalService $journal
    ) {
    }

    public function home(User $user, string $timezone): array
    {
        $timezone = $this->journal->timezone($timezone);
        $now = CarbonImmutable::now($timezone);

        return [
            'timezone' => $timezone,
            'today' => $this->summary($user, $now->startOfDay(), $now->endOfDay()),
            'week' => $this->summary($user, $now->startOfWeek(), $now->endOfWeek()),
            'month' => $this->summary($user, $now->startOfMonth(), $now->endOfMonth()),
            'recent' => TradeJournalEntry::query()
                ->where('user_id', $user->getKey())
                ->orderByRaw('COALESCE(opened_at, trade_at) desc')
                ->limit(10)
                ->get()
                ->map(fn (TradeJournalEntry $entry): array =>
                    $this->journal->serialize($entry, $timezone)
                )
                ->values()
                ->all(),
        ];
    }

    public function analytics(
        User $user,
        string $timezone,
        string $period = 'month',
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        $timezone = $this->journal->timezone($timezone);
        [$from, $to] = $this->resolvePeriod(
            $timezone,
            $period,
            $fromDate,
            $toDate
        );
        $entries = $this->entries($user, $from, $to);
        $executed = $entries->whereIn('status', ['open', 'closed'])->values();
        $closed = $entries->where('status', 'closed')->values();

        return [
            'period' => [
                'type' => $period,
                'timezone' => $timezone,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'overview' => $this->overview($executed, $closed),
            'risk' => $this->risk($executed, $closed),
            'performance' => $this->performance($closed, $timezone),
            'sessions' => $this->groupPerformance($closed, 'session_label'),
            'symbols' => $this->groupPerformance($closed, 'symbol'),
            'setups' => $this->groupPerformance($closed, 'setup_strategy'),
            'directions' => $this->groupPerformance($closed, 'direction'),
            'weekdays' => $this->weekdayPerformance($closed, $timezone),
            'psychology' => $this->groupPerformance($closed, 'emotion_before'),
            'discipline' => $this->discipline($executed, $closed),
        ];
    }

    public function summary(
        User $user,
        CarbonImmutable $localFrom,
        CarbonImmutable $localTo
    ): array {
        $entries = $this->entries($user, $localFrom, $localTo);
        $executed = $entries->whereIn('status', ['open', 'closed'])->values();
        $closed = $entries->where('status', 'closed')->values();
        $overview = $this->overview($executed, $closed);
        $discipline = $this->discipline($executed, $closed);
        $sessions = $this->groupPerformance($closed, 'session_label');
        $symbols = $this->groupPerformance($closed, 'symbol');
        $setups = $this->groupPerformance($closed, 'setup_strategy');
        $emotions = $this->groupPerformance($closed, 'emotion_before');

        return array_merge($overview, [
            'plan_followed_percent' => $discipline['plan_followed_percent'],
            'best_session' => $this->bestGroup($sessions),
            'worst_session' => $this->worstGroup($sessions),
            'best_symbol' => $this->bestGroup($symbols),
            'worst_symbol' => $this->worstGroup($symbols),
            'best_setup' => $this->bestGroup($setups),
            'worst_setup' => $this->worstGroup($setups),
            'most_common_emotion' => $this->mostCommonKey($emotions),
            'most_common_mistake' => $discipline['most_common_mistake'],
        ]);
    }

    private function entries(
        User $user,
        CarbonImmutable $localFrom,
        CarbonImmutable $localTo
    ): Collection {
        $fromUtc = $localFrom->setTimezone('UTC');
        $toUtc = $localTo->setTimezone('UTC');

        return TradeJournalEntry::query()
            ->where('user_id', $user->getKey())
            ->where(function ($outer) use ($fromUtc, $toUtc): void {
                $outer->where(function ($executed) use ($fromUtc, $toUtc): void {
                    $executed
                        ->whereIn('status', ['open', 'closed'])
                        ->whereBetween('opened_at', [$fromUtc, $toUtc]);
                })->orWhere(function ($planned) use ($fromUtc, $toUtc): void {
                    $planned
                        ->whereIn('status', ['planned', 'cancelled'])
                        ->whereBetween('trade_at', [$fromUtc, $toUtc]);
                });
            })
            ->orderByRaw('COALESCE(opened_at, trade_at) asc')
            ->get();
    }

    private function overview(Collection $executed, Collection $closed): array
    {
        $wins = $closed->where('result', 'win')->count();
        $losses = $closed->where('result', 'loss')->count();
        $breakeven = $closed->where('result', 'breakeven')->count();
        $partial = $closed->where('result', 'partial')->count();
        $rValues = $this->numbers($closed, 'realized_r');
        $decided = $wins + $losses;
        $currency = $this->currencySummary($closed);

        return [
            'total_executed' => $executed->count(),
            'open' => $executed->where('status', 'open')->count(),
            'closed' => $closed->count(),
            'wins' => $wins,
            'losses' => $losses,
            'breakeven' => $breakeven,
            'partial' => $partial,
            'win_rate' => $decided > 0
                ? round(($wins / $decided) * 100, 2)
                : null,
            'mixed_pnl_currencies' => $currency['mixed'],
            'pnl_currency' => $currency['single_currency'],
            'net_pnl' => $currency['single']['net_pnl'] ?? null,
            'average_pnl' => $currency['single']['average_pnl'] ?? null,
            'best_trade' => $currency['single']['best_trade'] ?? null,
            'worst_trade' => $currency['single']['worst_trade'] ?? null,
            'pnl_by_currency' => $currency['rows'],
            'average_realized_r' => $rValues->isEmpty()
                ? null
                : round($rValues->avg(), 8),
        ];
    }

    private function risk(Collection $executed, Collection $closed): array
    {
        $riskPercent = $this->numbers($executed, 'risk_percent');
        $plannedR = $this->numbers($executed, 'planned_rr');
        $realizedR = $this->numbers($closed, 'realized_r');
        $plannedAverage = $plannedR->isEmpty() ? null : round($plannedR->avg(), 8);
        $realizedAverage = $realizedR->isEmpty() ? null : round($realizedR->avg(), 8);

        return [
            'average_risk_percent' => $riskPercent->isEmpty()
                ? null
                : round($riskPercent->avg(), 4),
            'average_planned_rr' => $plannedAverage,
            'average_realized_r' => $realizedAverage,
            'planned_vs_realized_r_delta' =>
                $plannedAverage !== null && $realizedAverage !== null
                    ? round($realizedAverage - $plannedAverage, 8)
                    : null,
            'rr_minimum_required' => false,
        ];
    }

    private function performance(Collection $closed, string $timezone): array
    {
        $currency = $this->currencySummary($closed, true, $timezone);
        [$currentWin, $currentLoss, $maxWin, $maxLoss] = $this->streaks($closed);

        return [
            'mixed_pnl_currencies' => $currency['mixed'],
            'pnl_currency' => $currency['single_currency'],
            'average_win' => $currency['single']['average_win'] ?? null,
            'average_loss' => $currency['single']['average_loss'] ?? null,
            'largest_win' => $currency['single']['largest_win'] ?? null,
            'largest_loss' => $currency['single']['largest_loss'] ?? null,
            'profit_factor' => $currency['single']['profit_factor'] ?? null,
            'current_win_streak' => $currentWin,
            'current_loss_streak' => $currentLoss,
            'max_win_streak' => $maxWin,
            'max_loss_streak' => $maxLoss,
            'currency_performance' => $currency['rows'],
            'equity_curves' => $this->equityCurves($closed, $timezone),
            'daily_pnl' => $this->pnlBuckets($closed, $timezone, 'day'),
            'weekly_pnl' => $this->pnlBuckets($closed, $timezone, 'week'),
            'monthly_pnl' => $this->pnlBuckets($closed, $timezone, 'month'),
        ];
    }

    private function groupPerformance(Collection $closed, string $field): array
    {
        return $closed
            ->filter(fn (TradeJournalEntry $entry): bool => filled($entry->{$field}))
            ->groupBy(fn (TradeJournalEntry $entry): string => (string) $entry->{$field})
            ->map(function (Collection $group, string $key): array {
                $wins = $group->where('result', 'win')->count();
                $losses = $group->where('result', 'loss')->count();
                $decided = $wins + $losses;
                $r = $this->numbers($group, 'realized_r');
                $plan = $group->pluck('followed_plan_after')->filter();
                $planYes = $plan->filter(fn ($value): bool => $value === 'yes')->count();
                $currency = $this->currencySummary($group);

                return [
                    'key' => $key,
                    'trades' => $group->count(),
                    'wins' => $wins,
                    'losses' => $losses,
                    'win_rate' => $decided > 0
                        ? round(($wins / $decided) * 100, 2)
                        : null,
                    'net_pnl' => $currency['single']['net_pnl'] ?? null,
                    'pnl_currency' => $currency['single_currency'],
                    'pnl_by_currency' => $currency['rows'],
                    'average_realized_r' => $r->isEmpty()
                        ? null
                        : round($r->avg(), 8),
                    'plan_followed_percent' => $plan->isEmpty()
                        ? null
                        : round(($planYes / $plan->count()) * 100, 2),
                ];
            })
            ->sortByDesc(fn (array $row): float => $this->qualityScore($row))
            ->values()
            ->all();
    }

    private function weekdayPerformance(Collection $closed, string $timezone): array
    {
        return $closed
            ->groupBy(fn (TradeJournalEntry $entry): string =>
                $this->executionAt($entry)
                    ->setTimezone($timezone)
                    ->format('l')
            )
            ->map(function (Collection $group, string $key): array {
                $wins = $group->where('result', 'win')->count();
                $losses = $group->where('result', 'loss')->count();
                $decided = $wins + $losses;
                $currency = $this->currencySummary($group);

                return [
                    'key' => $key,
                    'trades' => $group->count(),
                    'win_rate' => $decided > 0
                        ? round(($wins / $decided) * 100, 2)
                        : null,
                    'net_pnl' => $currency['single']['net_pnl'] ?? null,
                    'pnl_currency' => $currency['single_currency'],
                    'pnl_by_currency' => $currency['rows'],
                ];
            })
            ->sortByDesc(fn (array $row): float => $this->qualityScore($row))
            ->values()
            ->all();
    }

    private function discipline(Collection $executed, Collection $closed): array
    {
        $planAnswers = $closed->pluck('followed_plan_after')->filter();
        $planYes = $planAnswers->filter(fn ($value): bool => $value === 'yes')->count();
        $checklistPercent = function (string $key) use ($executed): ?float {
            $values = $executed
                ->map(fn (TradeJournalEntry $entry) => data_get($entry->checklist, $key))
                ->filter(fn ($value): bool => in_array($value, ['yes', 'no'], true));

            return $values->isEmpty()
                ? null
                : round(
                    ($values->filter(fn ($value): bool => $value === 'yes')->count()
                        / $values->count()) * 100,
                    2
                );
        };

        $mistakes = $closed
            ->flatMap(fn (TradeJournalEntry $entry): array => $entry->mistake_tags ?? [])
            ->countBy()
            ->sortDesc();

        $byLocalDay = $executed->groupBy(function (TradeJournalEntry $entry): string {
            return $this->executionAt($entry)
                ->setTimezone($entry->user_timezone)
                ->toDateString();
        });

        $overLimit = $byLocalDay
            ->filter(fn (Collection $day): bool =>
                $day->count() > (int) config(
                    'trade_journal.discipline.max_trades_per_day',
                    3
                )
            )
            ->count();

        $afterTwoWins = 0;
        foreach ($byLocalDay as $day) {
            $winsSeen = 0;
            foreach ($day->sortBy(
                fn (TradeJournalEntry $entry): int => $this->executionAt($entry)->timestamp
            ) as $entry) {
                if ($winsSeen >= (int) config(
                    'trade_journal.discipline.stop_after_wins',
                    2
                )) {
                    $afterTwoWins++;
                }
                if ($entry->status === 'closed' && $entry->result === 'win') {
                    $winsSeen++;
                }
            }
        }

        return [
            'plan_followed_percent' => $planAnswers->isEmpty()
                ? null
                : round(($planYes / $planAnswers->count()) * 100, 2),
            'session_checked_percent' => $checklistPercent('session_checked'),
            'news_checked_percent' => $checklistPercent('news_checked'),
            'setup_confirmed_percent' => $checklistPercent('setup_confirmed'),
            'risk_checked_percent' => $checklistPercent('risk_checked'),
            'days_above_trade_target' => $overLimit,
            'trades_after_two_wins' => $afterTwoWins,
            'most_common_mistake' => $mistakes->isEmpty()
                ? null
                : (string) $mistakes->keys()->first(),
            'mistake_counts' => $mistakes->all(),
            'limits_are_warnings_only' => true,
        ];
    }

    private function currencySummary(
        Collection $closed,
        bool $includePerformance = false,
        ?string $timezone = null
    ): array {
        $groups = $closed
            ->filter(fn (TradeJournalEntry $entry): bool =>
                $entry->net_pnl !== null && filled($entry->pnl_currency)
            )
            ->groupBy(fn (TradeJournalEntry $entry): string =>
                strtoupper((string) $entry->pnl_currency)
            );

        $rows = $groups->map(function (Collection $group, string $currency) use (
            $includePerformance,
            $timezone
        ): array {
            $values = $this->numbers($group, 'net_pnl');
            $wins = $values->filter(fn (float $value): bool => $value > 0);
            $losses = $values->filter(fn (float $value): bool => $value < 0);
            $grossLoss = abs($losses->sum());
            $best = $group
                ->filter(fn (TradeJournalEntry $entry): bool => $entry->net_pnl !== null)
                ->sortByDesc(fn (TradeJournalEntry $entry): float => (float) $entry->net_pnl)
                ->first();
            $worst = $group
                ->filter(fn (TradeJournalEntry $entry): bool => $entry->net_pnl !== null)
                ->sortBy(fn (TradeJournalEntry $entry): float => (float) $entry->net_pnl)
                ->first();

            $row = [
                'currency' => $currency,
                'trades_with_pnl' => $values->count(),
                'net_pnl' => $values->isEmpty() ? null : round($values->sum(), 8),
                'average_pnl' => $values->isEmpty() ? null : round($values->avg(), 8),
                'best_trade' => $best ? $this->tradeMoneyRef($best) : null,
                'worst_trade' => $worst ? $this->tradeMoneyRef($worst) : null,
            ];

            if ($includePerformance) {
                $row = array_merge($row, [
                    'average_win' => $wins->isEmpty() ? null : round($wins->avg(), 8),
                    'average_loss' => $losses->isEmpty() ? null : round($losses->avg(), 8),
                    'largest_win' => $wins->isEmpty() ? null : round($wins->max(), 8),
                    'largest_loss' => $losses->isEmpty() ? null : round($losses->min(), 8),
                    'profit_factor' => $grossLoss > 0
                        ? round($wins->sum() / $grossLoss, 4)
                        : null,
                    'equity_curve' => $timezone
                        ? $this->equityCurve($group, $timezone)
                        : [],
                ]);
            }

            return $row;
        })->values()->all();

        $single = count($rows) === 1 ? $rows[0] : null;

        return [
            'mixed' => count($rows) > 1,
            'single_currency' => $single['currency'] ?? null,
            'single' => $single,
            'rows' => $rows,
        ];
    }

    private function equityCurves(Collection $closed, string $timezone): array
    {
        return $closed
            ->filter(fn (TradeJournalEntry $entry): bool =>
                $entry->net_pnl !== null && filled($entry->pnl_currency)
            )
            ->groupBy(fn (TradeJournalEntry $entry): string =>
                strtoupper((string) $entry->pnl_currency)
            )
            ->map(fn (Collection $group, string $currency): array => [
                'currency' => $currency,
                'points' => $this->equityCurve($group, $timezone),
            ])
            ->values()
            ->all();
    }

    private function equityCurve(Collection $group, string $timezone): array
    {
        $running = 0.0;
        $points = [];

        foreach ($group->sortBy(
            fn (TradeJournalEntry $entry): int => $this->executionAt($entry)->timestamp
        ) as $entry) {
            $running += (float) $entry->net_pnl;
            $points[] = [
                'date' => $this->executionAt($entry)
                    ->setTimezone($timezone)
                    ->toIso8601String(),
                'uuid' => $entry->uuid,
                'net_pnl' => (float) $entry->net_pnl,
                'cumulative_pnl' => round($running, 8),
            ];
        }

        return $points;
    }

    private function pnlBuckets(
        Collection $closed,
        string $timezone,
        string $period
    ): array {
        return $closed
            ->filter(fn (TradeJournalEntry $entry): bool =>
                $entry->net_pnl !== null && filled($entry->pnl_currency)
            )
            ->groupBy(function (TradeJournalEntry $entry) use ($timezone, $period): string {
                $local = $this->executionAt($entry)->setTimezone($timezone);
                $periodKey = match ($period) {
                    'week' => $local->startOfWeek()->toDateString(),
                    'month' => $local->startOfMonth()->format('Y-m'),
                    default => $local->toDateString(),
                };

                return $periodKey.'|'.strtoupper((string) $entry->pnl_currency);
            })
            ->map(function (Collection $group, string $key): array {
                [$periodKey, $currency] = explode('|', $key, 2);
                $pnl = $this->numbers($group, 'net_pnl');

                return [
                    'period' => $periodKey,
                    'currency' => $currency,
                    'trades' => $group->count(),
                    'net_pnl' => $pnl->isEmpty() ? null : round($pnl->sum(), 8),
                ];
            })
            ->sortBy('period')
            ->values()
            ->all();
    }

    private function resolvePeriod(
        string $timezone,
        string $period,
        ?string $fromDate,
        ?string $toDate
    ): array {
        $now = CarbonImmutable::now($timezone);

        return match ($period) {
            'today' => [$now->startOfDay(), $now->endOfDay()],
            'week' => [$now->startOfWeek(), $now->endOfWeek()],
            'month' => [$now->startOfMonth(), $now->endOfMonth()],
            '3months' => [$now->subMonths(2)->startOfMonth(), $now->endOfMonth()],
            '6months' => [$now->subMonths(5)->startOfMonth(), $now->endOfMonth()],
            'year' => [$now->startOfYear(), $now->endOfYear()],
            'custom' => [
                CarbonImmutable::createFromFormat(
                    'Y-m-d',
                    (string) $fromDate,
                    $timezone
                )->startOfDay(),
                CarbonImmutable::createFromFormat(
                    'Y-m-d',
                    (string) $toDate,
                    $timezone
                )->endOfDay(),
            ],
            default => [$now->startOfMonth(), $now->endOfMonth()],
        };
    }

    private function numbers(Collection $entries, string $field): Collection
    {
        return $entries
            ->pluck($field)
            ->filter(fn ($value): bool => $value !== null)
            ->map(fn ($value): float => (float) $value);
    }

    private function executionAt(TradeJournalEntry $entry): CarbonImmutable
    {
        return $entry->opened_at ?: $entry->trade_at;
    }

    private function streaks(Collection $closed): array
    {
        $win = 0;
        $loss = 0;
        $maxWin = 0;
        $maxLoss = 0;

        foreach ($closed as $entry) {
            if ($entry->result === 'win') {
                $win++;
                $loss = 0;
                $maxWin = max($maxWin, $win);
            } elseif ($entry->result === 'loss') {
                $loss++;
                $win = 0;
                $maxLoss = max($maxLoss, $loss);
            } else {
                $win = 0;
                $loss = 0;
            }
        }

        $last = $closed->last();

        return [
            $last?->result === 'win' ? $win : 0,
            $last?->result === 'loss' ? $loss : 0,
            $maxWin,
            $maxLoss,
        ];
    }

    private function tradeMoneyRef(TradeJournalEntry $entry): array
    {
        return [
            'uuid' => $entry->uuid,
            'symbol' => $entry->symbol,
            'direction' => $entry->direction,
            'net_pnl' => (float) $entry->net_pnl,
            'currency' => $entry->pnl_currency,
        ];
    }

    private function qualityScore(array $row): float
    {
        $winRate = (float) ($row['win_rate'] ?? 0);
        $averageR = (float) ($row['average_realized_r'] ?? 0);

        return ($winRate * 1000) + $averageR;
    }

    private function bestGroup(array $groups): ?string
    {
        return $groups[0]['key'] ?? null;
    }

    private function worstGroup(array $groups): ?string
    {
        return $groups === []
            ? null
            : ($groups[array_key_last($groups)]['key'] ?? null);
    }

    private function mostCommonKey(array $groups): ?string
    {
        if ($groups === []) {
            return null;
        }

        usort(
            $groups,
            fn (array $a, array $b): int => $b['trades'] <=> $a['trades']
        );

        return $groups[0]['key'] ?? null;
    }
}
