<?php

namespace App\Http\Controllers\Api\TradeJournal;

use App\Http\Controllers\Controller;
use App\Models\TradeJournalReview;
use App\Models\User;
use App\Services\TradeJournal\TradeJournalAnalyticsService;
use App\Services\TradeJournal\TradeJournalService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class TradeJournalReviewController extends Controller
{
    public function show(
        Request $request,
        string $type,
        TradeJournalService $journal,
        TradeJournalAnalyticsService $analytics
    ): JsonResponse {
        $user = $this->user($request);
        $type = $this->type($type);
        $timezone = $this->timezone($request, $journal);
        [$start, $end] = $this->period($request, $type, $timezone);

        $review = TradeJournalReview::query()
            ->where('user_id', $user->getKey())
            ->where('period_type', $type)
            ->whereDate('period_start', $start->toDateString())
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'period_type' => $type,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'timezone' => $timezone,
                'summary' => $analytics->summary($user, $start, $end),
                'review' => $review ? $this->serialize($review) : null,
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function update(
        Request $request,
        string $type,
        TradeJournalService $journal,
        TradeJournalAnalyticsService $analytics
    ): JsonResponse {
        $user = $this->user($request);
        $type = $this->type($type);
        $timezone = $this->timezone($request, $journal);
        [$start, $end] = $this->period($request, $type, $timezone);
        $data = $request->validate($this->rules($type));
        $summary = $analytics->summary($user, $start, $end);

        $review = DB::transaction(function () use ($user, $type, $start, $end, $timezone, $data, $summary): TradeJournalReview {
            $review = TradeJournalReview::withTrashed()
                ->where('user_id', $user->getKey())
                ->where('period_type', $type)
                ->whereDate('period_start', $start->toDateString())
                ->first();

            if (! $review) {
                $review = new TradeJournalReview([
                    'uuid' => (string) Str::uuid(),
                    'period_type' => $type,
                    'period_start' => $start->toDateString(),
                ]);
                $review->user()->associate($user);
            } elseif ($review->trashed()) {
                $review->restore();
            }

            $review->fill(array_merge($data, [
                'period_end' => $end->toDateString(),
                'user_timezone' => $timezone,
                'summary_snapshot' => $summary,
            ]));
            $review->save();

            return $review->fresh();
        });

        return response()->json([
            'success' => true,
            'data' => $this->serialize($review),
        ])->header('Cache-Control', 'private, no-store');
    }

    private function period(Request $request, string $type, string $timezone): array
    {
        $requested = $request->query('date');
        try {
            $anchor = filled($requested)
                ? CarbonImmutable::createFromFormat('Y-m-d', (string) $requested, $timezone)->startOfDay()
                : CarbonImmutable::now($timezone);
        } catch (\Throwable) {
            abort(422, 'Invalid review date.');
        }

        return $type === 'weekly'
            ? [$anchor->startOfWeek(), $anchor->endOfWeek()]
            : [$anchor->startOfMonth(), $anchor->endOfMonth()];
    }

    private function rules(string $type): array
    {
        $common = [
            'user_id' => ['prohibited'],
            'positive_reflection' => ['nullable', 'string', 'max:8000'],
            'negative_reflection' => ['nullable', 'string', 'max:8000'],
            'biggest_lesson' => ['nullable', 'string', 'max:8000'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
        ];

        return $type === 'weekly'
            ? array_merge($common, [
                'stop_doing' => ['nullable', 'string', 'max:8000'],
                'continue_doing' => ['nullable', 'string', 'max:8000'],
                'next_focus' => ['nullable', 'string', 'max:8000'],
                'biggest_achievement' => ['prohibited'],
                'biggest_weakness' => ['prohibited'],
                'habit_to_remove' => ['prohibited'],
                'habit_to_continue' => ['prohibited'],
            ])
            : array_merge($common, [
                'biggest_achievement' => ['nullable', 'string', 'max:8000'],
                'biggest_weakness' => ['nullable', 'string', 'max:8000'],
                'habit_to_remove' => ['nullable', 'string', 'max:8000'],
                'habit_to_continue' => ['nullable', 'string', 'max:8000'],
                'next_focus' => ['nullable', 'string', 'max:8000'],
                'stop_doing' => ['prohibited'],
                'continue_doing' => ['prohibited'],
            ]);
    }

    private function serialize(TradeJournalReview $review): array
    {
        return [
            'uuid' => $review->uuid,
            'period_type' => $review->period_type,
            'period_start' => $review->period_start?->toDateString(),
            'period_end' => $review->period_end?->toDateString(),
            'timezone' => $review->user_timezone,
            'summary_snapshot' => $review->summary_snapshot,
            'positive_reflection' => $review->positive_reflection,
            'negative_reflection' => $review->negative_reflection,
            'biggest_lesson' => $review->biggest_lesson,
            'stop_doing' => $review->stop_doing,
            'continue_doing' => $review->continue_doing,
            'next_focus' => $review->next_focus,
            'biggest_achievement' => $review->biggest_achievement,
            'biggest_weakness' => $review->biggest_weakness,
            'habit_to_remove' => $review->habit_to_remove,
            'habit_to_continue' => $review->habit_to_continue,
            'rating' => $review->rating,
            'updated_at' => $review->updated_at?->toIso8601String(),
        ];
    }

    private function type(string $type): string
    {
        abort_unless(in_array($type, ['weekly', 'monthly'], true), 404);
        return $type;
    }

    private function timezone(Request $request, TradeJournalService $journal): string
    {
        try {
            return $journal->timezone((string) $request->query('timezone', 'UTC'));
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
}
