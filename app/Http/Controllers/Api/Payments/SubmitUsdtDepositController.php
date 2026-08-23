<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Payments\CreateUsdtDepositRequest;
use App\Models\User;
use App\Services\Payments\ManualDepositService;
use Illuminate\Http\JsonResponse;

class SubmitUsdtDepositController extends Controller
{
    public function __invoke(
        CreateUsdtDepositRequest $request,
        ManualDepositService $service
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        return response()->json([
            'success' => true,
            'data' => $service->submitUsdtDeposit(
                $user,
                $request->validated(),
                $request
            ),
        ], 201);
    }
}
