<?php

namespace App\Http\Controllers\Api\Education;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Education\AssignmentApiService;
use App\Services\Education\EducationResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssignmentIndexController extends Controller
{
    public function __invoke(
        Request $request,
        string $lesson,
        EducationResolver $resolver,
        AssignmentApiService $assignments
    ): JsonResponse {
        $user =
            $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        $lessonModel =
            $resolver->lesson(
                $lesson
            );

        $lessonModel->loadMissing(
            'module.course'
        );

        return response()->json([
            'success' => true,
            'data' =>
                $assignments->forLesson(
                    $user,
                    $lessonModel
                ),
        ]);
    }
}
