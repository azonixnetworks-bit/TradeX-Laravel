<?php

namespace App\Http\Controllers\Api\TradeJournal;

use App\Http\Controllers\Controller;
use App\Models\TradeJournalEntry;
use App\Models\User;
use App\Services\TradeJournal\TradeJournalAnalyticsService;
use App\Services\TradeJournal\TradeJournalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class TradeJournalController extends Controller
{
    public function home(
        Request $request,
        TradeJournalAnalyticsService $analytics,
        TradeJournalService $journal
    ): JsonResponse {
        $user = $this->user($request);
        $timezone = $this->queryTimezone($request, $journal);

        return $this->ok($analytics->home($user, $timezone));
    }

    public function analytics(
        Request $request,
        TradeJournalAnalyticsService $analytics,
        TradeJournalService $journal
    ): JsonResponse {
        $user = $this->user($request);
        $timezone = $this->queryTimezone($request, $journal);
        $validated = $request->validate([
            'period' => ['sometimes', Rule::in(['today', 'week', 'month', '3months', '6months', 'year', 'custom'])],
            'from' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d'],
            'to' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return $this->ok($analytics->analytics(
            $user,
            $timezone,
            $validated['period'] ?? 'month',
            $validated['from'] ?? null,
            $validated['to'] ?? null
        ));
    }

    public function index(Request $request, TradeJournalService $journal): JsonResponse
    {
        $user = $this->user($request);
        $timezone = $this->queryTimezone($request, $journal);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'market' => ['nullable', Rule::in(config('trade_journal.markets', []))],
            'stock_region' => ['nullable', Rule::in(config('trade_journal.stock_regions', []))],
            'symbol' => ['nullable', 'string', 'max:64'],
            'direction' => ['nullable', Rule::in(config('trade_journal.directions', []))],
            'status' => ['nullable', Rule::in(config('trade_journal.statuses', []))],
            'result' => ['nullable', Rule::in(config('trade_journal.results', []))],
            'session' => ['nullable', 'string', 'max:160'],
            'setup' => ['nullable', 'string', 'max:160'],
            'emotion' => ['nullable', 'string', 'max:40'],
            'followed_plan' => ['nullable', Rule::in(config('trade_journal.followed_plan_after', []))],
            'account' => ['nullable', 'string', 'max:120'],
            'broker' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'highest_pnl', 'lowest_pnl', 'best_rating'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = TradeJournalEntry::query()->where('user_id', $user->getKey());

        if (filled($validated['search'] ?? null)) {
            $search = '%'.$validated['search'].'%';
            $query->where(function ($nested) use ($search): void {
                $nested->where('symbol', 'like', $search)
                    ->orWhere('setup_strategy', 'like', $search)
                    ->orWhere('entry_reason', 'like', $search)
                    ->orWhere('notes_before', 'like', $search);
            });
        }

        foreach ([
            'market' => 'market',
            'stock_region' => 'stock_region',
            'symbol' => 'symbol',
            'direction' => 'direction',
            'status' => 'status',
            'result' => 'result',
            'session' => 'session_label',
            'setup' => 'setup_strategy',
            'emotion' => 'emotion_before',
            'followed_plan' => 'followed_plan_after',
            'account' => 'account_label',
            'broker' => 'broker',
        ] as $input => $column) {
            if (filled($validated[$input] ?? null)) {
                $query->where($column, $validated[$input]);
            }
        }

        if (filled($validated['from'] ?? null)) {
            $query->where('trade_at', '>=', $journal->localToUtc($validated['from'], '00:00:00', $timezone));
        }
        if (filled($validated['to'] ?? null)) {
            $query->where('trade_at', '<=', $journal->localToUtc($validated['to'], '23:59:59', $timezone));
        }

        match ($validated['sort'] ?? 'newest') {
            'oldest' => $query->orderBy('trade_at'),
            'highest_pnl' => $query->orderByDesc('net_pnl'),
            'lowest_pnl' => $query->orderBy('net_pnl'),
            'best_rating' => $query->orderByDesc('rating')->orderByDesc('trade_at'),
            default => $query->orderByDesc('trade_at'),
        };

        $page = $query->paginate((int) ($validated['per_page'] ?? 20));
        $page->setCollection($page->getCollection()->map(
            fn (TradeJournalEntry $entry): array => $journal->serialize($entry, $timezone)
        ));

        return response()->json(['success' => true, 'data' => $page])
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, TradeJournalService $journal): JsonResponse
    {
        $user = $this->user($request);
        $data = $request->validate($this->storeRules());
        $result = $journal->create($user, $data);

        return response()->json([
            'success' => true,
            'data' => $journal->serialize($result['entry'], $data['timezone']),
            'warnings' => $result['warnings'],
        ], 201)->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, string $uuid, TradeJournalService $journal): JsonResponse
    {
        $user = $this->user($request);
        $entry = $journal->owned($user, $uuid);
        $timezone = $this->queryTimezone($request, $journal, $entry->user_timezone);

        return $this->ok($journal->serialize($entry, $timezone));
    }

    public function update(Request $request, string $uuid, TradeJournalService $journal): JsonResponse
    {
        $user = $this->user($request);
        $entry = $journal->owned($user, $uuid);
        $data = $request->validate($this->updateRules());
        $result = $journal->update($user, $entry, $data);

        return response()->json([
            'success' => true,
            'data' => $journal->serialize($result['entry']),
            'warnings' => $result['warnings'],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function open(Request $request, string $uuid, TradeJournalService $journal): JsonResponse
    {
        $user = $this->user($request);
        $entry = $journal->owned($user, $uuid);
        $data = $request->validate([
            'user_id' => ['prohibited'],
            'actual_entry' => ['required', 'numeric', 'gt:0'],
            'entry_date' => ['required', 'date_format:Y-m-d'],
            'entry_time' => ['required', 'date_format:H:i'],
            'timezone' => ['required', 'string', 'timezone', 'max:96'],
            'current_sl' => ['nullable', 'numeric', 'gt:0'],
            'current_tp' => ['nullable', 'numeric', 'gt:0'],
            'position_size' => ['nullable', 'numeric', 'gt:0'],
            'execution_note' => ['nullable', 'string', 'max:5000'],
        ]);
        $result = $journal->open($user, $entry, $data);

        return response()->json([
            'success' => true,
            'data' => $journal->serialize($result['entry'], $data['timezone']),
            'warnings' => $result['warnings'],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function close(Request $request, string $uuid, TradeJournalService $journal): JsonResponse
    {
        $user = $this->user($request);
        $entry = $journal->owned($user, $uuid);
        $data = $request->validate([
            'user_id' => ['prohibited'],
            'exit_price' => ['required', 'numeric', 'gt:0'],
            'exit_date' => ['required', 'date_format:Y-m-d'],
            'exit_time' => ['required', 'date_format:H:i'],
            'timezone' => ['required', 'string', 'timezone', 'max:96'],
            'result' => ['required', Rule::in(config('trade_journal.results', []))],
            'gross_pnl' => ['nullable', 'numeric'],
            'fees' => ['nullable', 'numeric', 'min:0'],
            'pnl_currency' => ['required_with:gross_pnl,fees,pnl_percent', 'nullable', 'string', 'max:16'],
            'pnl_percent' => ['nullable', 'numeric'],
            'emotion_after' => ['nullable', Rule::in(config('trade_journal.emotions_after', []))],
            'followed_plan_after' => ['required', Rule::in(config('trade_journal.followed_plan_after', []))],
            'mistake_tags' => ['nullable', 'array', 'max:20'],
            'mistake_tags.*' => [Rule::in(config('trade_journal.mistake_tags', []))],
            'went_well' => ['nullable', 'string', 'max:5000'],
            'went_wrong' => ['nullable', 'string', 'max:5000'],
            'lesson_learned' => ['nullable', 'string', 'max:5000'],
            'improvement_next_trade' => ['nullable', 'string', 'max:5000'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
        ]);
        $closed = $journal->close($user, $entry, $data);

        return $this->ok($journal->serialize($closed, $data['timezone']));
    }

    public function cancel(Request $request, string $uuid, TradeJournalService $journal): JsonResponse
    {
        $user = $this->user($request);
        $entry = $journal->owned($user, $uuid);
        $data = $request->validate([
            'user_id' => ['prohibited'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $cancelled = $journal->cancel($user, $entry, $data['reason'] ?? null);

        return $this->ok($journal->serialize($cancelled));
    }

    public function destroy(Request $request, string $uuid, TradeJournalService $journal): JsonResponse
    {
        $user = $this->user($request);
        $entry = $journal->owned($user, $uuid);
        $entry->delete();

        return response()->json(['success' => true]);
    }

    private function storeRules(): array
    {
        return array_merge($this->editableRules(), [
            'status' => ['required', Rule::in(['planned', 'open'])],
            'trade_date' => ['required', 'date_format:Y-m-d'],
            'trade_time' => ['required', 'date_format:H:i'],
            'timezone' => ['required', 'string', 'timezone', 'max:96'],
            'actual_entry' => ['required_if:status,open', 'nullable', 'numeric', 'gt:0'],
        ]);
    }

    private function updateRules(): array
    {
        return array_merge($this->editableRules(false), [
            'actual_entry' => ['prohibited'],
            'status' => ['prohibited'],
            'timezone' => ['prohibited'],
            'trade_date' => ['prohibited'],
            'trade_time' => ['prohibited'],
            'current_sl' => ['nullable', 'numeric', 'gt:0'],
            'current_tp' => ['nullable', 'numeric', 'gt:0'],
            'execution_note' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function editableRules(bool $required = true): array
    {
        return [
            'user_id' => ['prohibited'],
            'market' => [$required ? 'required' : 'sometimes', Rule::in(config('trade_journal.markets', []))],
            'stock_region' => ['required_if:market,stocks', 'nullable', Rule::in(config('trade_journal.stock_regions', []))],
            'symbol' => [$required ? 'required' : 'sometimes', 'string', 'max:64'],
            'direction' => [$required ? 'required' : 'sometimes', Rule::in(config('trade_journal.directions', []))],
            'session_label' => ['nullable', 'string', 'max:160'],
            'broker' => ['nullable', 'string', 'max:120'],
            'account_label' => ['nullable', 'string', 'max:120'],
            'setup_strategy' => ['nullable', 'string', 'max:160'],
            'timeframes' => ['nullable', 'array', 'max:8'],
            'timeframes.*' => [Rule::in(config('trade_journal.timeframes', []))],
            'entry_reason' => [$required ? 'required' : 'sometimes', 'string', 'max:5000'],
            'notes_before' => ['nullable', 'string', 'max:5000'],
            'planned_entry' => ['nullable', 'numeric', 'gt:0'],
            'planned_sl' => ['nullable', 'numeric', 'gt:0'],
            'planned_tp' => ['nullable', 'numeric', 'gt:0'],
            'position_size' => ['nullable', 'numeric', 'gt:0'],
            'risk_amount' => ['nullable', 'numeric', 'min:0'],
            'risk_currency' => ['required_with:risk_amount', 'nullable', 'string', 'max:16'],
            'risk_percent' => ['nullable', 'numeric', 'gt:0', 'max:100'],
            'emotion_before' => ['nullable', Rule::in(config('trade_journal.emotions_before', []))],
            'confidence_before' => ['nullable', 'integer', 'between:1,5'],
            'mental_state_note' => ['nullable', 'string', 'max:5000'],
            'checklist' => ['nullable', 'array'],
            'checklist.followed_trading_plan' => ['nullable', Rule::in(['yes', 'no'])],
            'checklist.sl_planned' => ['nullable', Rule::in(config('trade_journal.checklist_values', []))],
            'checklist.tp_planned' => ['nullable', Rule::in(config('trade_journal.checklist_values', []))],
            'checklist.session_checked' => ['nullable', Rule::in(['yes', 'no'])],
            'checklist.news_checked' => ['nullable', Rule::in(config('trade_journal.checklist_values', []))],
            'checklist.risk_checked' => ['nullable', Rule::in(config('trade_journal.checklist_values', []))],
            'checklist.setup_confirmed' => ['nullable', Rule::in(['yes', 'no'])],
        ];
    }

    private function queryTimezone(Request $request, TradeJournalService $journal, string $fallback = 'UTC'): string
    {
        try {
            return $journal->timezone((string) $request->query('timezone', $fallback));
        } catch (InvalidArgumentException) {
            abort(422, 'Invalid IANA timezone.');
        }
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');
        return $user;
    }

    private function ok(array $data): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
        ])->header('Cache-Control', 'private, no-store');
    }
}
