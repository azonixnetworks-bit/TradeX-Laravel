<?php

namespace App\Services\Admin\Activity;

use App\Models\UserActivityEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class UserActivityAnalyticsService
{
    public const MAX_EXPORT_ROWS = 10000;

    /**
     * Event rows are retained for 45 days. Restrict interactive queries to
     * a 31-calendar-day window to bound dashboard and export resource use.
     */
    public function filters(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:100'],
            'module' => ['nullable', 'string', 'regex:/^[a-z_]{1,40}$/'],
            'event_name' => ['nullable', 'string', 'regex:/^[a-z_]{1,80}$/'],
            'per_page' => ['nullable', 'integer', 'in:20,50,100'],
        ]);

        $today = CarbonImmutable::today('UTC');
        $start = CarbonImmutable::parse(
            $validated['from'] ?? $today->subDays(6)->toDateString(),
            'UTC'
        )->startOfDay();
        $end = CarbonImmutable::parse(
            $validated['to'] ?? $today->toDateString(),
            'UTC'
        )->startOfDay();

        if ($start->greaterThan($end) ||
            $start->diffInDays($end) > 30 ||
            $end->greaterThan($today) ||
            $start->lessThan($today->subDays(44))) {
            throw ValidationException::withMessages([
                'from' => 'Select an inclusive range of up to 31 days within the last 45 UTC calendar days.',
            ]);
        }

        return [
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'start' => $start,
            'end_exclusive' => $end->addDay(),
            'user_id' => $validated['user_id'] ?? null,
            'search' => trim($validated['search'] ?? ''),
            'module' => $validated['module'] ?? '',
            'event_name' => $validated['event_name'] ?? '',
            'per_page' => (int) ($validated['per_page'] ?? 20),
        ];
    }

    public function query(array $filters, bool $includeEventFilters = true): Builder
    {
        $query = UserActivityEvent::query()
            ->where('occurred_at', '>=', $filters['start'])
            ->where('occurred_at', '<', $filters['end_exclusive']);

        if ($filters['user_id'] !== null) {
            $query->where('user_id', $filters['user_id']);
        }
        if ($filters['search'] !== '') {
            // Escaping wildcard characters prevents accidental broad searches.
            $term = '%'.addcslashes($filters['search'], '%_\\').'%';
            $query->whereHas('user', function (Builder $userQuery) use ($term): void {
                $userQuery->where(function (Builder $q) use ($term): void {
                    $q->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            });
        }

        if ($includeEventFilters) {
            if ($filters['module'] !== '') {
                $query->where('module', $filters['module']);
            }
            if ($filters['event_name'] !== '') {
                $query->where('event_name', $filters['event_name']);
            }
        }

        return $query;
    }

    public function timeline(array $filters)
    {
        return $this->query($filters)
            ->with('user:id,name')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($filters['per_page'])
            ->withQueryString();
    }

    public function summary(array $filters): array
    {
        // Summary always includes all modules/events; detail filters only
        // change the timeline. This avoids misleading filtered session time.
        $base = $this->query($filters, false);

        $totals = (clone $base)->selectRaw(
            'COUNT(*) AS events, COUNT(DISTINCT user_id) AS active_users, '
            .'COUNT(DISTINCT session_id) AS sessions'
        )->first();

        $days = (clone $base)
            ->selectRaw('DATE(occurred_at) AS day, COUNT(*) AS events, '
                .'COUNT(DISTINCT user_id) AS active_users, '
                .'COUNT(DISTINCT session_id) AS sessions')
            ->groupByRaw('DATE(occurred_at)')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $modules = (clone $base)
            ->selectRaw('module, COUNT(*) AS events')
            ->groupBy('module')
            ->orderByDesc('events')
            ->limit(12)
            ->get();

        $secondsByDay = $this->estimatedActiveSecondsByDay($base);
        $daily = [];
        $day = $filters['start'];
        while ($day->lessThan($filters['end_exclusive'])) {
            $date = $day->toDateString();
            $counts = $days->get($date);
            $daily[] = [
                'date' => $date,
                'active_users' => (int) ($counts->active_users ?? 0),
                'events' => (int) ($counts->events ?? 0),
                'sessions' => (int) ($counts->sessions ?? 0),
                'estimated_minutes' => round(($secondsByDay[$date] ?? 0) / 60, 1),
            ];
            $day = $day->addDay();
        }

        return [
            'events' => (int) ($totals->events ?? 0),
            'active_users' => (int) ($totals->active_users ?? 0),
            'sessions' => (int) ($totals->sessions ?? 0),
            'estimated_minutes' => round(array_sum($secondsByDay) / 60, 1),
            'daily' => $daily,
            'modules' => $modules,
        ];
    }

    /**
     * Estimated foreground seconds, never exact screen-time measurement:
     * accumulate only within a single user's session between consecutive
     * events; cap each interval at 180 seconds. Pause/ended markers end a
     * foreground interval, and start/resume markers begin a new one.
     *
     * Uses a streaming cursor so event volume doesn't inflate PHP memory.
     * Session heartbeats (every 2m) improve accuracy on otherwise idle views.
     */
    private function estimatedActiveSecondsByDay(Builder $query): array
    {
        $seconds = [];
        $lastKey = null;
        $lastTime = null;
        $lastEvent = null;

        foreach ((clone $query)
            ->whereNotNull('session_id')
            ->select(['id', 'user_id', 'session_id', 'event_name', 'occurred_at'])
            ->orderBy('user_id')
            ->orderBy('session_id')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->cursor() as $row) {
            $key = $row->user_id.':'.$row->session_id;
            $time = $row->occurred_at->getTimestamp();

            if ($key === $lastKey && $lastTime !== null &&
                ! in_array($lastEvent, ['session_paused', 'session_ended'], true) &&
                ! in_array($row->event_name, ['session_resumed', 'session_started'], true)) {
                $elapsed = max(0, $time - $lastTime);
                $date = $row->occurred_at->utc()->toDateString();
                $seconds[$date] = ($seconds[$date] ?? 0) + min($elapsed, 180);
            }

            $lastKey = $key;
            $lastTime = $time;
            $lastEvent = $row->event_name;
        }

        return $seconds;
    }
}
