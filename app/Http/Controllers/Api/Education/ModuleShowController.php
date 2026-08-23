<?php

namespace App\Http\Controllers\Api\Education;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Education\EducationCatalogService;
use App\Services\Education\EducationResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModuleShowController extends Controller
{
    public function __invoke(
        Request $request,
        string $module,
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
            $resolver->module(
                $module
            );

        $model->loadMissing(
            'course'
        );

        return response()->json([
            'success' => true,
            'data' =>
                $catalog->module(
                    $user,
                    $model
                ),
        ]);
    }
}
