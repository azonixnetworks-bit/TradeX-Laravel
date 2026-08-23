<?php

namespace App\Http\Controllers\Api\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Profile\UpdateProfilePreferencesRequest;
use App\Models\User;
use App\Services\Profile\ProfilePreferencesService;
use Illuminate\Http\JsonResponse;

class UpdateProfilePreferencesController extends Controller
{
    public function __invoke(
        UpdateProfilePreferencesRequest $request,
        ProfilePreferencesService $profiles
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        $profiles->updateLanguage(
            $user,
            $request->validated(
                'language_code'
            ),
            $request
        );

        return response()->json([
            'success' => true,
            'data' => [
                'language_code' =>
                    $user->fresh()
                        ->language_code,
            ],
        ]);
    }
}
