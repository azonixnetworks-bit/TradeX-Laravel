<?php

namespace App\Http\Controllers\Api\Education;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Education\ListCoursesRequest;
use App\Models\User;
use App\Services\Education\EducationCatalogService;
use Illuminate\Http\JsonResponse;

class CourseIndexController extends Controller
{
    public function __invoke(
        ListCoursesRequest $request,
        EducationCatalogService $catalog
    ): JsonResponse {
        $user =
            $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        return response()->json([
            'success' => true,
            'data' =>
                $catalog->courses(
                    $user,
                    $request->validated()
                ),
        ]);
    }
}
