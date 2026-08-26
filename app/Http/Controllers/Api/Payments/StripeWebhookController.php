<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Services\Payments\StripeWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        StripeWebhookService $service
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $service->handle($request),
        ]);
    }
}
