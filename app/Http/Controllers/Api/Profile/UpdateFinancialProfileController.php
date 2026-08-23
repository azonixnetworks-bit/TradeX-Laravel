<?php

namespace App\Http\Controllers\Api\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Profile\UpdateFinancialProfileRequest;
use App\Models\User;
use App\Services\Profile\FinancialProfileService;
use App\Services\Profile\ProfilePresenter;
use Illuminate\Http\JsonResponse;

class UpdateFinancialProfileController extends Controller
{
    public function __invoke(
        UpdateFinancialProfileRequest $request,
        FinancialProfileService $financial,
        ProfilePresenter $presenter
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        $financial->update(
            $user,
            $request->validated(),
            $request
        );

        $fresh =
            $user->fresh()
                ->load(
                    'profile'
                );

        return response()->json([
            'success' => true,
            'data' =>
                $presenter->present(
                    $fresh
                )['financial'],
        ]);
    }
}
