<?php

namespace App\Http\Controllers\Admin\Investments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Investments\RejectInvestmentCostRequest;
use App\Http\Requests\Admin\Investments\StoreInvestmentApprovedCostRequest;
use App\Models\AdminUser;
use App\Models\Investment;
use App\Models\InvestmentApprovedCost;
use App\Services\Admin\Investments\InvestmentCostService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvestmentCostController extends Controller
{
    public function store(
        StoreInvestmentApprovedCostRequest $request,
        Investment $investment,
        InvestmentCostService $service
    ): RedirectResponse {
        try {
            $service->record($investment, $request->validated(), $this->admin(), $request);
        } catch (DomainException $exception) {
            return back()->withErrors(['cost' => $exception->getMessage()])->withInput();
        }
        return back()->with('success', 'Approved-category investment cost recorded for Finance verification.');
    }

    public function verify(
        Request $request,
        Investment $investment,
        InvestmentApprovedCost $cost,
        InvestmentCostService $service
    ): RedirectResponse {
        try {
            $service->verify($investment, $cost, $this->admin(), $request);
        } catch (DomainException $exception) {
            return back()->withErrors(['cost' => $exception->getMessage()]);
        }
        return back()->with('success', 'Investment cost verified by Finance.');
    }

    public function reject(
        RejectInvestmentCostRequest $request,
        Investment $investment,
        InvestmentApprovedCost $cost,
        InvestmentCostService $service
    ): RedirectResponse {
        try {
            $service->reject(
                $investment,
                $cost,
                $request->validated('reason'),
                $this->admin(),
                $request
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['cost' => $exception->getMessage()]);
        }
        return back()->with('success', 'Investment cost rejected.');
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);
        return $admin;
    }
}
