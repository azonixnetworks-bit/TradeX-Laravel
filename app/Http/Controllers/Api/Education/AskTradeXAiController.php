<?php

namespace App\Http\Controllers\Api\Education;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Education\AskTradeXAiRequest;
use App\Models\User;
use App\Services\Education\TradeXAiLearningService;
use Illuminate\Http\JsonResponse;

class AskTradeXAiController extends Controller
{
    public function __invoke(AskTradeXAiRequest $request, TradeXAiLearningService $ai): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');
        $validated = $request->validated();

        return response()->json([
            'success' => true,
            'data' => $ai->answer(
                $user,
                (string) $validated['question'],
                $validated['lesson_uuid'] ?? null
            ),
        ]);
    }
}
