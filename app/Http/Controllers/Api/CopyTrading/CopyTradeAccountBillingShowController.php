<?php

namespace App\Http\Controllers\Api\CopyTrading;

use App\Http\Controllers\Controller;
use App\Models\CopyTradeBrokerAccount;
use App\Models\User;
use App\Services\CopyTrading\Billing\CopyTradeBillingPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CopyTradeAccountBillingShowController extends Controller
{
    public function __invoke(
        Request $request,
        CopyTradeBrokerAccount $copyTradeBrokerAccount,
        CopyTradeBillingPresenter $presenter
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        abort_unless(
            (int) $copyTradeBrokerAccount->user_id
            === (int) $user->getKey(),
            404
        );

        return response()->json([
            'success' => true,
            'data' =>
                $presenter->account(
                    $copyTradeBrokerAccount
                ),
        ]);
    }
}
