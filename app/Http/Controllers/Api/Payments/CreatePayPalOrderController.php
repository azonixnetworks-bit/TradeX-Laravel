<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Payments\CreatePayPalOrderRequest;
use App\Models\User;
use App\Services\Payments\PayPalOrderService;
use Illuminate\Http\JsonResponse;

class CreatePayPalOrderController extends Controller
{
    public function __invoke(
        CreatePayPalOrderRequest $request,
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
            'data' => $service->create(
                $user,
                (string) $request->validated(
                    'idempotency_key'
                ),
                $request,
                $request->validated(
                    'product_uuid'
                )
            ),
        ], 201);
    }
}
