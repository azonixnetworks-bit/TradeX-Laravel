<?php

namespace App\Http\Controllers\Api\Education;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Education\UpdateLessonProgressRequest;
use App\Models\User;
use App\Services\Education\EducationAccessService;
use App\Services\Education\EducationProgressService;
use App\Services\Education\EducationResolver;
use Illuminate\Http\JsonResponse;

class UpdateLessonProgressController extends Controller
{
    public function __invoke(
        UpdateLessonProgressRequest $request,
        string $lesson,
        EducationResolver $resolver,
        EducationAccessService $access,
        EducationProgressService $progress
    ): JsonResponse {
        $user =
            $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        $model =
            $resolver->lesson(
                $lesson
            );

        $model->loadMissing(
            'module.course'
        );

        $access->assertLessonAccess(
            $user,
            $model
        );

        $progress->update(
            $user,
            $model,
            $request->validated(),
            $request
        );

        return response()->json([
            'success' => true,
            'data' =>
                $progress
                    ->lessonProgress(
                        $user,
                        $model
                    ),
        ]);
    }
}
