<?php

namespace App\Http\Controllers\Api\Languages;

use App\Http\Controllers\Controller;
use App\Services\Languages\RuntimeTranslationService;
use Illuminate\Http\JsonResponse;

class RuntimeLanguageIndexController extends Controller
{
    public function __invoke(
        RuntimeTranslationService $service
    ): JsonResponse {
        return response()
            ->json([
                'success' => true,
                'data' => $service->languages(),
            ])
            ->header(
                'Cache-Control',
                'public, max-age='.(int) config(
                    'tradex_translation_api.cache_seconds',
                    300
                ).', must-revalidate'
            )
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
