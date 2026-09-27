<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Payments\PaymentMethodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PaymentMethodIndexController extends Controller
{
    public function __invoke(
        Request $request,
        PaymentMethodService $service
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        $ttlSeconds = max(
            0,
            (int) config(
                'tradex.performance.payment_methods_cache_seconds',
                30
            )
        );

        $cacheKey = implode(':', [
            'tradex',
            'api',
            'payment-methods',
            'v1',
            (string) $user->getKey(),
            (string) ($user->updated_at?->getTimestamp() ?? 0),
            (string) $user->country_code,
            (string) $user->account_type,
        ]);

        $data = $ttlSeconds === 0
            ? $service->methodsFor($user)
            : Cache::remember(
                $cacheKey,
                now()->addSeconds($ttlSeconds),
                fn (): array =>
                    $service->methodsFor($user)
            );

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
