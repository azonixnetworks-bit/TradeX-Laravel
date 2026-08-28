<?php

namespace App\Http\Controllers\Api\CopyTrading;

use App\Http\Controllers\Controller;
use App\Models\CopyTradeBrokerMarket;
use App\Models\User;
use App\Services\CopyTrading\CopyTradeOnboardingService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CopyTradeOpenAccountActionController extends Controller
{
    public function __invoke(
        Request $request,
        CopyTradeBrokerMarket $copyTradeBrokerMarket,
        CopyTradeOnboardingService $service
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        try {
            $intent = $service->openAccountAction(
                $user,
                $copyTradeBrokerMarket,
                $request
            );
        } catch (DomainException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 409);
        }

        $intent->loadMissing('brokerMarket.broker');

        return response()->json([
            'success' => true,
            'data' => [
                'onboarding_intent_uuid' => $intent->uuid,
                'broker_name' => $intent->brokerMarket?->broker?->name,
                'market_category' => $intent->brokerMarket?->market_category,
                'open_account_via_tradex_url' => $intent->referral_url_snapshot,
                'expires_at' => $intent->expires_at?->toIso8601String(),
                'notice' => 'Opening a broker account through this TradeX partner action does not itself activate Copy Trading. TradeX must verify partner attribution, live-account eligibility and the remaining activation gates.',
            ],
        ]);
    }
}
