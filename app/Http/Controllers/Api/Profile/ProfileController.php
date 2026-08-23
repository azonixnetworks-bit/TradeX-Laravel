<?php

namespace App\Http\Controllers\Api\Profile;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Profile\ProfilePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __invoke(
        Request $request,
        ProfilePresenter $presenter
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        $user->loadMissing(
            'profile'
        );

        return response()->json([
            'success' => true,
            'data' =>
                $presenter->present(
                    $user
                ),
        ]);
    }
}
