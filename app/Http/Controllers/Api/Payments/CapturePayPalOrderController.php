<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Payments\PayPalOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CapturePayPalOrderController extends Controller
{
    public function __invoke(
        Request $request,
        string $paymentOrder,
        PayPalOrderService $service
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        return response()->json([
            'success' => true,
            'data' => $service->capture(
                $user,
                $paymentOrder,
                $request
            ),
        ]);
    }
}
