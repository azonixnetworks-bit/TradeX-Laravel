<?php

namespace App\Http\Controllers\Api\Education;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Education\AssignmentApiService;
use App\Services\Education\EducationResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssignmentWhatsAppActionController extends Controller
{
    public function __invoke(
        Request $request,
        string $assignment,
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

        $assignmentModel =
            $resolver->assignment(
                $assignment
            );

        return response()->json([
            'success' => true,
            'data' =>
                $assignments
                    ->prepareWhatsAppAction(
                        $user,
                        $assignmentModel,
                        $request
                    ),
        ]);
    }
}
