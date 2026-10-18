<?php

namespace App\Http\Controllers\Api\Education;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Education\LearningHubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LearningProgressController extends Controller
{
    public function __invoke(Request $request, LearningHubService $learning): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');

        return response()->json(['success' => true, 'data' => $learning->progress($user)]);
    }
}
