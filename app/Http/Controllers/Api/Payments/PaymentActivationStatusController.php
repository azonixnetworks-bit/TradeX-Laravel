<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Payments\PaymentOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentActivationStatusController extends Controller
{
    public function __invoke(
        Request $request,
        PaymentOrderService $service
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        return response()->json([
            'success' => true,
            'data' => $service->activationStatus(
                $user
            ),
        ]);
    }
}
