<?php

namespace App\Http\Controllers\Api\Education;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Education\EducationCatalogService;
use App\Services\Education\EducationResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseShowController extends Controller
{
    public function __invoke(
        Request $request,
        string $course,
        EducationResolver $resolver,
        EducationCatalogService $catalog
    ): JsonResponse {
        $user =
            $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        $model =
            $resolver->course(
                $course
            );

        return response()->json([
            'success' => true,
            'data' =>
                $catalog->course(
                    $user,
                    $model
                ),
        ]);
    }
}
