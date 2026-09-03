<?php

namespace App\Http\Controllers\Admin\Investments;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\Investment;
use App\Services\Admin\Investments\InvestmentLifecycleService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvestmentWalletController extends Controller
{
    public function verify(
        Request $request,
        Investment $investment,
        InvestmentLifecycleService $service
    ): RedirectResponse {
        try {
            $service->verifyPayoutWallet(
                $investment,
                $this->admin(),
                $request
            );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'payout_wallet' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'Customer USDT TRC20 payout wallet verified.'
        );
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }
}
