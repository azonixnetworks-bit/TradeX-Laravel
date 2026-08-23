<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Payments\PaymentHistoryRequest;
use App\Models\User;
use App\Services\Payments\PaymentOrderService;
use Illuminate\Http\JsonResponse;

class PaymentHistoryController extends Controller
{
    public function __invoke(
        PaymentHistoryRequest $request,
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
            'data' => $service->history(
                $user,
                $request->validated()
            ),
        ]);
    }
}
