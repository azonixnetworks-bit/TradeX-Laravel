<?php

namespace App\Http\Controllers\Api\CopyTrading;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CopyTrading\StoreCopyTradeBrokerAccountRequest;
use App\Models\CopyTradeOnboardingIntent;
use App\Models\User;
use App\Services\CopyTrading\CopyTradeOnboardingService;
use App\Services\CopyTrading\CopyTradePresenter;
use DomainException;
use Illuminate\Http\JsonResponse;

class CopyTradeBrokerAccountStoreController extends Controller
{
    public function __invoke(
        StoreCopyTradeBrokerAccountRequest $request,
        CopyTradeOnboardingService $service,
        CopyTradePresenter $presenter
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        $intent = CopyTradeOnboardingIntent::query()
            ->where('uuid', $request->intentUuid())
            ->firstOrFail();

        try {
            $account = $service->submitBrokerAccount(
                $user,
                $intent,
                $request->accountNumber(),
                $request
            );
        } catch (DomainException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 409);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'broker_account' => $presenter->account($account),
            ],
        ], 201);
    }
}
