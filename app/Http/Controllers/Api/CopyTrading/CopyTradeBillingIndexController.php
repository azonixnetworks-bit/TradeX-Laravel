<?php

namespace App\Http\Controllers\Api\CopyTrading;

use App\Http\Controllers\Controller;
use App\Models\CopyTradeBrokerAccount;
use App\Models\User;
use App\Services\CopyTrading\Billing\CopyTradeBillingPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CopyTradeBillingIndexController extends Controller
{
    public function __invoke(
        Request $request,
        CopyTradeBillingPresenter $presenter
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        $accounts = CopyTradeBrokerAccount::query()
            ->with([
                'broker',
                'brokerMarket',
                'billingCycles',
                'billingInvoices.lines',
                'billingInvoices.snapshot',
            ])
            ->where(
                'user_id',
                $user->getKey()
            )
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'accounts' =>
                    $accounts
                        ->map(
                            fn (
                                CopyTradeBrokerAccount $account
                            ): array =>
                                $presenter->account(
                                    $account
                                )
                        )
                        ->values()
                        ->all(),
            ],
        ]);
    }
}
