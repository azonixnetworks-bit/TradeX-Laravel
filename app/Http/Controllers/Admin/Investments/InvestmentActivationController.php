<?php

namespace App\Http\Controllers\Admin\Investments;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\Investment;
use App\Services\Admin\Investments\InvestmentLifecycleService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvestmentActivationController extends Controller
{
    public function index(
        InvestmentLifecycleService $service
    ): View {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission('investments.view'),
            403
        );

        $investments = Investment::query()
            ->with([
                'user',
                'product',
                'latestAgreement',
            ])
            ->where(
                'status',
                Investment::STATUS_AWAITING_AGREEMENT
            )
            ->latest('id')
            ->limit(100)
            ->get();

        $rows = $investments->map(
            fn (Investment $investment) => [
                'investment' => $investment,
                'readiness' =>
                    $service->readiness($investment),
            ]
        );

        return view(
            'admin.investments.activation.index',
            [
                'rows' => $rows,
                'canActivate' =>
                    $admin->hasPermission(
                        'investments.activate'
                    ),
            ]
        );
    }

    public function activate(
        Request $request,
        Investment $investment,
        InvestmentLifecycleService $service
    ): RedirectResponse {
        try {
            $service->activate(
                $investment,
                $this->admin(),
                $request
            );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'activation' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route(
                'admin.investments.show',
                $investment
            )
            ->with(
                'success',
                'Investment activated. Maturity date and profit review schedule were generated.'
            );
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }
}
