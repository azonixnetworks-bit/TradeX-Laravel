<?php

namespace App\Http\Controllers\Api\CopyTrading;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CopyTrading\CopyTradeOnboardingService;
use App\Services\CopyTrading\CopyTradePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CopyTradeBootstrapController extends Controller
{
    public function __invoke(
        Request $request,
        CopyTradeOnboardingService $service,
        CopyTradePresenter $presenter
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        return response()->json([
            'success' => true,
            'data' => $presenter->bootstrap(
                $user,
                $service->availableBrokerMarkets(),
                $service->accountsForUser($user)
            ),
        ]);
    }
}
