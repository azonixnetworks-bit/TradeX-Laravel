<?php

namespace App\Http\Controllers\Api\CopyTrading;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CopyTrading\CopyTradeOnboardingService;
use App\Services\CopyTrading\CopyTradePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CopyTradeBrokerIndexController extends Controller
{
    public function __invoke(
        Request $request,
        CopyTradeOnboardingService $service,
        CopyTradePresenter $presenter
    ): JsonResponse {
        abort_unless(
            $request->user() instanceof User,
            401,
            'Unauthenticated.'
        );

        return response()->json([
            'success' => true,
            'data' => [
                'brokers' => $service
                    ->availableBrokerMarkets()
                    ->map(fn ($market): array =>
                        $presenter->brokerMarket($market))
                    ->values()
                    ->all(),
            ],
        ]);
    }
}
