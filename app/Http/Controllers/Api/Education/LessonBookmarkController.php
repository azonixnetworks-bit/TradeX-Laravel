<?php

namespace App\Http\Controllers\Api\Education;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Education\EducationResolver;
use App\Services\Education\LessonBookmarkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonBookmarkController extends Controller
{
    public function store(
        Request $request,
        string $lesson,
        EducationResolver $resolver,
        LessonBookmarkService $bookmarks
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');

        return response()->json([
            'success' => true,
            'data' => $bookmarks->save($user, $resolver->lesson($lesson)),
        ]);
    }

    public function destroy(
        Request $request,
        string $lesson,
        EducationResolver $resolver,
        LessonBookmarkService $bookmarks
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');

        return response()->json([
            'success' => true,
            'data' => $bookmarks->remove($user, $resolver->lesson($lesson)),
        ]);
    }
}
