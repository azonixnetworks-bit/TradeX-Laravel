<?php

namespace App\Http\Controllers\Admin\Investments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Investments\StoreInvestmentProfitPayoutRequest;
use App\Models\AdminUser;
use App\Models\Investment;
use App\Models\InvestmentProfitPeriod;
use App\Models\InvestmentTransaction;
use App\Services\Admin\Investments\InvestmentProfitPayoutService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvestmentProfitPayoutController extends Controller
{
    public function store(
        StoreInvestmentProfitPayoutRequest $request,
        Investment $investment,
        InvestmentProfitPeriod $period,
        InvestmentProfitPayoutService $service
    ): RedirectResponse {
        try {
            $service->record($investment, $period, $request->validated(), $this->admin(), $request);
        } catch (DomainException $exception) {
            return back()->withErrors(['payout' => $exception->getMessage()]);
        }
        return back()->with('success', 'USDT TRC20 customer profit payout recorded. TxID verification is still required.');
    }

    public function verify(
        Request $request,
        Investment $investment,
        InvestmentProfitPeriod $period,
        InvestmentTransaction $transaction,
        InvestmentProfitPayoutService $service
    ): RedirectResponse {
        try {
            $service->verify($investment, $period, $transaction, $this->admin(), $request);
        } catch (DomainException $exception) {
            return back()->withErrors(['payout' => $exception->getMessage()]);
        }
        return back()->with('success', 'Profit payout TxID verified. Period is SETTLED.');
    }

    public function reject(
        Request $request,
        Investment $investment,
        InvestmentProfitPeriod $period,
        InvestmentTransaction $transaction,
        InvestmentProfitPayoutService $service
    ): RedirectResponse {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        try {
            $service->reject($investment, $period, $transaction, $data['reason'], $this->admin(), $request);
        } catch (DomainException $exception) {
            return back()->withErrors(['payout' => $exception->getMessage()]);
        }
        return back()->with('success', 'Profit payout transaction rejected; period returned to APPROVED.');
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);
        return $admin;
    }
}
