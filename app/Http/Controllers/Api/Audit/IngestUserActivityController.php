<?php

namespace App\Http\Controllers\Api\Audit;

use App\Http\Controllers\Controller;
use App\Services\Audit\ActivityAvailability;
use App\Services\Audit\UserActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IngestUserActivityController extends Controller
{
    public function __invoke(
        Request $request,
        UserActivityService $service,
        ActivityAvailability $availability
    ): JsonResponse {
        if (! $availability->isReady()) {
            return response()->json([
                'success' => false,
                'code' => 'ACTIVITY_DISABLED',
                'error' => [
                    'code' => 'ACTIVITY_DISABLED',
                    'message' => 'User activity ingestion is unavailable.',
                ],
                'message' => 'User activity ingestion is unavailable.',
            ], 503)->header('Cache-Control', 'private, no-store');
        }

        $allowed = [
            'screen_viewed', 'lesson_viewed', 'lesson_completed',
            'signal_viewed', 'journal_opened', 'journal_saved', 'calculator_used',
            'news_viewed', 'feature_opened', 'session_started',
            'session_heartbeat',
            'session_resumed', 'session_paused', 'session_ended',
        ];

        $data = $request->validate([
            'events' => ['required', 'array', 'min:1', 'max:25'],
            'events.*.event_id' => ['required', 'uuid'],
            'events.*.module' => ['required', 'string', Rule::in([
                'app', 'learning', 'trade_ideas', 'journal', 'calculator',
                'news', 'funded_accounts', 'copy_trading', 'investments',
                'affiliate', 'support', 'profile',
            ])],
            'events.*.event_name' => ['required', 'string', Rule::in($allowed)],
            'events.*.session_id' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/'],
            'events.*.subject_id' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/'],
            'events.*.occurred_at' => ['required', 'date', 'after_or_equal:-7 days', 'before_or_equal:+5 minutes'],
        ]);

        $userId = (int) $request->user()->getAuthIdentifier();
        $inserted = $service->ingest($userId, $data['events']);

        return response()->json([
            'success' => true,
            'data' => ['accepted' => count($data['events']), 'inserted' => $inserted],
        ], 202);
    }
}
