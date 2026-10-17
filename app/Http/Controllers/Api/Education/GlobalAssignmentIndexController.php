<?php

namespace App\Http\Controllers\Api\Education;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Education\LearningCollectionRequest;
use App\Models\User;
use App\Services\Education\LearningHubService;
use Illuminate\Http\JsonResponse;

class GlobalAssignmentIndexController extends Controller
{
    public function __invoke(LearningCollectionRequest $request, LearningHubService $learning): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');
        $validated = $request->validated();

        return response()->json([
            'success' => true,
            'data' => $learning->assignments(
                $user,
                strtoupper((string) ($validated['status'] ?? 'ALL')),
                (int) ($validated['per_page'] ?? 20)
            ),
        ]);
    }
}
