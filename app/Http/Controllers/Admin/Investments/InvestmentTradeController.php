<?php

namespace App\Http\Controllers\Admin\Investments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Investments\StoreInvestmentTradeRequest;
use App\Models\AdminUser;
use App\Models\Investment;
use App\Models\InvestmentTrade;
use App\Services\Admin\Investments\InvestmentTradeService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvestmentTradeController extends Controller
{
    public function store(
        StoreInvestmentTradeRequest $request,
        Investment $investment,
        InvestmentTradeService $service
    ): RedirectResponse {
        try {
            $service->record($investment, $request->validated(), $this->admin(), $request);
        } catch (DomainException $exception) {
            return back()->withErrors(['trade' => $exception->getMessage()])->withInput();
        }
        return back()->with('success', 'Spot trade recorded. Upload screenshot evidence and obtain Finance verification.');
    }

    public function verify(
        Request $request,
        Investment $investment,
        InvestmentTrade $trade,
        InvestmentTradeService $service
    ): RedirectResponse {
        try {
            $service->verify($investment, $trade, $this->admin(), $request);
        } catch (DomainException $exception) {
            return back()->withErrors(['trade' => $exception->getMessage()]);
        }
        return back()->with('success', 'Spot trade verified and customer asset sub-ledger updated.');
    }

    public function reject(
        Request $request,
        Investment $investment,
        InvestmentTrade $trade,
        InvestmentTradeService $service
    ): RedirectResponse {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        try {
            $service->reject($investment, $trade, $data['reason'], $this->admin(), $request);
        } catch (DomainException $exception) {
            return back()->withErrors(['trade' => $exception->getMessage()]);
        }
        return back()->with('success', 'Spot trade rejected.');
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);
        return $admin;
    }
}
