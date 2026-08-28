<?php

namespace App\Http\Controllers\Api\CopyTrading;

use App\Http\Controllers\Controller;
use App\Models\CopyTradeBrokerAccount;
use App\Models\User;
use App\Services\CopyTrading\CopyTradePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CopyTradeBrokerAccountActivationShowController extends Controller
{
    public function __invoke(
        Request $request,
        CopyTradeBrokerAccount $copyTradeBrokerAccount,
        CopyTradePresenter $presenter
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        abort_unless(
            $copyTradeBrokerAccount->user_id
                === $user->getKey(),
            404
        );

        return response()->json([
            'success' => true,
            'data' => [
                'broker_account_uuid' =>
                    $copyTradeBrokerAccount->uuid,
                'activation' =>
                    $presenter->activation(
                        $copyTradeBrokerAccount
                    ),
            ],
        ]);
    }
}
