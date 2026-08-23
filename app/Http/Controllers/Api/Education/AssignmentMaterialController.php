<?php

namespace App\Http\Controllers\Api\Education;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Education\AssignmentApiService;
use App\Services\Education\EducationResolver;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssignmentMaterialController extends Controller
{
    public function __invoke(
        Request $request,
        string $assignment,
        EducationResolver $resolver,
        AssignmentApiService $assignments
    ): StreamedResponse {
        $user =
            $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        $assignmentModel =
            $resolver->assignment(
                $assignment
            );

        return $assignments
            ->downloadMaterial(
                $user,
                $assignmentModel,
                $request
            );
    }
}
