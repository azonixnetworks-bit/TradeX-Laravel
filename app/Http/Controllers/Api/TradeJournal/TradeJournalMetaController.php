<?php

namespace App\Http\Controllers\Api\TradeJournal;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TradeJournalMetaController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user() instanceof User, 401, 'Unauthenticated.');

        return response()->json([
            'success' => true,
            'data' => [
                'markets' => config('trade_journal.markets', []),
                'stock_regions' => config('trade_journal.stock_regions', []),
                'directions' => config('trade_journal.directions', []),
                'statuses' => config('trade_journal.statuses', []),
                'results' => config('trade_journal.results', []),
                'timeframes' => config('trade_journal.timeframes', []),
                'emotions_before' => config('trade_journal.emotions_before', []),
                'emotions_after' => config('trade_journal.emotions_after', []),
                'followed_plan_after' => config('trade_journal.followed_plan_after', []),
                'mistake_tags' => config('trade_journal.mistake_tags', []),
                'discipline' => config('trade_journal.discipline', []),
                'rr_policy' => [
                    'minimum_required' => false,
                    'blocking' => false,
                ],
                'screenshot' => [
                    'max_kilobytes' => config('trade_journal.screenshots.max_kilobytes', 10240),
                    'mimes' => config('trade_journal.screenshots.mimes', []),
                ],
            ],
        ])->header('Cache-Control', 'private, no-store');
    }
}
