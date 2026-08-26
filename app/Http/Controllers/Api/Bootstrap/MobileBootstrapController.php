<?php

namespace App\Http\Controllers\Api\Bootstrap;

use App\Http\Controllers\Controller;
use App\Services\Bootstrap\MobileBootstrapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MobileBootstrapController extends Controller
{
    public function __invoke(
        Request $request,
        MobileBootstrapService $service
    ): JsonResponse|Response {
        $data = $service->bootstrap();
        $etag = '"'.$data['bootstrap_sha256'].'"';

        $headers = [
            'ETag' => $etag,
            'Cache-Control' =>
                'public, max-age='.(int) config(
                    'tradex_mobile_bootstrap.cache_seconds',
                    60
                ).', must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if (
            trim((string) $request->header('If-None-Match'))
            === $etag
        ) {
            return response('', 304, $headers);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ], 200, $headers);
    }
}
