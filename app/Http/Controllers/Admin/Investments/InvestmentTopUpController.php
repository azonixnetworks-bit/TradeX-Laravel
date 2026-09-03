<?php

namespace App\Http\Controllers\Admin\Investments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Investments\StoreInvestmentTopUpRequest;
use App\Models\AdminUser;
use App\Models\Investment;
use App\Models\InvestmentTransaction;
use App\Services\Admin\Investments\InvestmentTopUpService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvestmentTopUpController extends Controller
{
    public function store(
        StoreInvestmentTopUpRequest $request,
        Investment $investment,
        InvestmentTopUpService $service
    ): RedirectResponse {
        try {
            $service->record($investment, $request->validated(), $this->admin(), $request);
        } catch (DomainException $exception) {
            return back()->withErrors(['top_up' => $exception->getMessage()])->withInput();
        }
        return back()->with('success', 'USDT TRC20 top-up recorded for Finance verification.');
    }

    public function verify(
        Request $request,
        Investment $investment,
        InvestmentTransaction $transaction,
        InvestmentTopUpService $service
    ): RedirectResponse {
        try {
            $service->verify($investment, $transaction, $this->admin(), $request);
        } catch (DomainException $exception) {
            return back()->withErrors(['top_up' => $exception->getMessage()]);
        }
        return back()->with('success', 'Top-up verified. Reference capital/HWM increased without resetting loss history.');
    }

    public function reject(
        Request $request,
        Investment $investment,
        InvestmentTransaction $transaction,
        InvestmentTopUpService $service
    ): RedirectResponse {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        try {
            $service->reject($investment, $transaction, $data['reason'], $this->admin(), $request);
        } catch (DomainException $exception) {
            return back()->withErrors(['top_up' => $exception->getMessage()]);
        }
        return back()->with('success', 'Top-up rejected.');
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);
        return $admin;
    }
}
