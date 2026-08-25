<?php

namespace App\Http\Controllers\Api\Kyc;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Kyc\SubmitKycRequest;
use App\Models\User;
use App\Services\Kyc\KycApiPresenter;
use App\Services\Kyc\KycSubmissionService;
use Illuminate\Http\JsonResponse;

class KycSubmissionStoreController extends Controller
{
    public function __invoke(
        SubmitKycRequest $request,
        KycSubmissionService $service,
        KycApiPresenter $presenter
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        $submission =
            $service->submit(
                $user,
                $request
                    ->documentType(),
                $request
                    ->identityData(),
                $request
                    ->documentFiles(),
                $request
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
        ], 201);
    }
}
