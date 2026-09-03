<?php

namespace App\Http\Controllers\Admin\Investments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Investments\RejectInvestmentAdjustmentRequest;
use App\Http\Requests\Admin\Investments\StoreInvestmentAdjustmentRequest;
use App\Models\AdminUser;
use App\Models\Investment;
use App\Models\InvestmentFinancialAdjustment;
use App\Services\Admin\Investments\InvestmentAdjustmentService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvestmentAdjustmentController extends Controller
{
    public function store(
        StoreInvestmentAdjustmentRequest $request,
        Investment $investment,
        InvestmentAdjustmentService $service
    ): RedirectResponse {
        try {
            $service->record($investment, $request->validated(), $this->admin(), $request);
        } catch (DomainException $exception) {
            return back()->withErrors(['adjustment' => $exception->getMessage()])->withInput();
        }
        return back()->with('success', 'Immutable reversal/adjustment entry recorded for Finance verification.');
    }

    public function verify(
        Request $request,
        Investment $investment,
        InvestmentFinancialAdjustment $adjustment,
        InvestmentAdjustmentService $service
    ): RedirectResponse {
        try {
            $service->verify($investment, $adjustment, $this->admin(), $request);
        } catch (DomainException $exception) {
            return back()->withErrors(['adjustment' => $exception->getMessage()]);
        }
        return back()->with('success', 'Financial reversal/adjustment verified.');
    }

    public function reject(
        RejectInvestmentAdjustmentRequest $request,
        Investment $investment,
        InvestmentFinancialAdjustment $adjustment,
        InvestmentAdjustmentService $service
    ): RedirectResponse {
        try {
            $service->reject(
                $investment,
                $adjustment,
                $request->validated('reason'),
                $this->admin(),
                $request
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['adjustment' => $exception->getMessage()]);
        }
        return back()->with('success', 'Financial reversal/adjustment rejected.');
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);
        return $admin;
    }
}
