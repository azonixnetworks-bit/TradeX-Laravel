<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Dashboard\DashboardApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        DashboardApiService $service
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        return response()->json([
            'success' => true,
            'data' => $service->dashboard(
                $user
            ),
        ]);
    }
}
