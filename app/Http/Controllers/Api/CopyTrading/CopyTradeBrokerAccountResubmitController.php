<?php

namespace App\Http\Controllers\Api\CopyTrading;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CopyTrading\ResubmitCopyTradeBrokerAccountRequest;
use App\Models\CopyTradeBrokerAccount;
use App\Models\User;
use App\Services\CopyTrading\CopyTradeOnboardingService;
use App\Services\CopyTrading\CopyTradePresenter;
use DomainException;
use Illuminate\Http\JsonResponse;

class CopyTradeBrokerAccountResubmitController extends Controller
{
    public function __invoke(
        ResubmitCopyTradeBrokerAccountRequest $request,
        CopyTradeBrokerAccount $copyTradeBrokerAccount,
        CopyTradeOnboardingService $service,
        CopyTradePresenter $presenter
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        try {
            $account = $service->resubmitBrokerAccount(
                $user,
                $copyTradeBrokerAccount,
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
        ]);
    }
}
