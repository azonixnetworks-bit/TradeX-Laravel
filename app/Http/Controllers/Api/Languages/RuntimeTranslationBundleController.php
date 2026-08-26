<?php

namespace App\Http\Controllers\Api\Languages;

use App\Http\Controllers\Controller;
use App\Services\Languages\RuntimeTranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RuntimeTranslationBundleController extends Controller
{
    public function __invoke(
        Request $request,
        RuntimeTranslationService $service,
        string $languageCode,
        ?string $group = null
    ): JsonResponse|Response {
        $data = $service->bundle(
            $languageCode,
            $group
        );

        $etag = '"'.$data['bundle_sha256'].'"';

        $headers = [
            'ETag' => $etag,
            'Cache-Control' =>
                'public, max-age='.(int) config(
                    'tradex_translation_api.cache_seconds',
                    300
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
