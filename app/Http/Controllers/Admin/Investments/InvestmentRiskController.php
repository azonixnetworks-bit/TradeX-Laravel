<?php

namespace App\Http\Controllers\Admin\Investments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Investments\RecordInvestmentRiskAcceptanceRequest;
use App\Models\AdminUser;
use App\Models\Investment;
use App\Services\Admin\Investments\InvestmentLifecycleService;
use DomainException;
use Illuminate\Http\RedirectResponse;

class InvestmentRiskController extends Controller
{
    public function store(
        RecordInvestmentRiskAcceptanceRequest $request,
        Investment $investment,
        InvestmentLifecycleService $service
    ): RedirectResponse {
        try {
            $service->recordRiskAcceptance(
                $investment,
                $request->validated(),
                $this->admin(),
                $request
            );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'risk_acceptance' =>
                    $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'Customer crypto risk-acceptance evidence recorded. Agreement generation is now available.'
        );
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }
}
