<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Services\Payments\PayPalWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayPalWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        PayPalWebhookService $service
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $service->handle(
                $request
            ),
        ]);
    }
}
