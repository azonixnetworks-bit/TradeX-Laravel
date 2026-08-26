<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Services\Payments\NowPaymentsWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NowPaymentsWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        NowPaymentsWebhookService $service
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $service->handle($request),
        ]);
    }
}
