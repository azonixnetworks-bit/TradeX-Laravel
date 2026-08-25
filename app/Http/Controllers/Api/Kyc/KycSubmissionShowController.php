<?php

namespace App\Http\Controllers\Api\Kyc;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Kyc\KycApiPresenter;
use App\Services\Kyc\KycQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KycSubmissionShowController extends Controller
{
    public function __invoke(
        Request $request,
        string $kycSubmission,
        KycQueryService $queries,
        KycApiPresenter $presenter
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        $submission =
            $queries
                ->resolveOwnedSubmission(
                    $user,
                    $kycSubmission
                );

        return response()->json([
            'success' => true,
            'data' => [
                'submission' =>
                    $presenter
                        ->submission(
                            $submission
                        ),
            ],
        ]);
    }
}
