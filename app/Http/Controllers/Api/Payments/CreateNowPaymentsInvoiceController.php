<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Payments\CreateOnlineGatewayOrderRequest;
use App\Models\User;
use App\Services\Payments\NowPaymentsInvoiceService;
use Illuminate\Http\JsonResponse;

class CreateNowPaymentsInvoiceController extends Controller
{
    public function __invoke(
        CreateOnlineGatewayOrderRequest $request,
        NowPaymentsInvoiceService $service
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');

        return response()->json([
            'success' => true,
            'data' => $service->create(
                $user,
                (string) $request->validated('idempotency_key'),
                $request
            ),
        ], 201);
    }
}
