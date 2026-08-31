<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Payments\PrepareManualPaymentRequest;
use App\Models\User;
use App\Services\Payments\PreparedManualPaymentService;
use Illuminate\Http\JsonResponse;

class PrepareManualPaymentController extends Controller
{
    public function __invoke(
        PrepareManualPaymentRequest $request,
        PreparedManualPaymentService $service
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        return response()->json([
            'success' => true,
            'data' => $service->prepare(
                $user,
                $request->validated(),
                $request
            ),
        ], 201);
    }
}
