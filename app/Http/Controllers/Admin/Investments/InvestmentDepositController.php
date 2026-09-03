<?php

namespace App\Http\Controllers\Admin\Investments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Investments\RejectInvestmentDepositRequest;
use App\Http\Requests\Admin\Investments\StoreInvestmentDepositRequest;
use App\Models\AdminUser;
use App\Models\Investment;
use App\Models\InvestmentTransaction;
use App\Services\Admin\Investments\InvestmentLifecycleService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvestmentDepositController extends Controller
{
    public function index(): View
    {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission('investments.deposits.view'),
            403
        );

        $pending = InvestmentTransaction::query()
            ->with(['investment.user'])
            ->where(
                'type',
                InvestmentTransaction::TYPE_INVESTMENT_DEPOSIT
            )
            ->where(
                'status',
                InvestmentTransaction::STATUS_PENDING
            )
            ->latest('id')
            ->paginate(25);

        $awaiting = Investment::query()
            ->with(['user', 'product'])
            ->where(
                'status',
                Investment::STATUS_AWAITING_DEPOSIT
            )
            ->latest('id')
            ->limit(50)
            ->get();

        return view(
            'admin.investments.deposits.index',
            [
                'pendingDeposits' => $pending,
                'awaitingInvestments' => $awaiting,
                'canVerify' =>
                    $admin->hasPermission(
                        'investments.deposits.verify'
                    ),
            ]
        );
    }

    public function store(
        StoreInvestmentDepositRequest $request,
        Investment $investment,
        InvestmentLifecycleService $service
    ): RedirectResponse {
        try {
            $service->recordDeposit(
                $investment,
                $request->validated(),
                $this->admin(),
                $request
            );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'deposit' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'USDT TRC20 deposit recorded and queued for verification.'
        );
    }

    public function verify(
        Request $request,
        Investment $investment,
        InvestmentTransaction $transaction,
        InvestmentLifecycleService $service
    ): RedirectResponse {
        try {
            $service->verifyDeposit(
                $investment,
                $transaction,
                $this->admin(),
                $request
            );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'deposit' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'Investment deposit verified. Risk acceptance is now required.'
        );
    }

    public function reject(
        RejectInvestmentDepositRequest $request,
        Investment $investment,
        InvestmentTransaction $transaction,
        InvestmentLifecycleService $service
    ): RedirectResponse {
        try {
            $service->rejectDeposit(
                $investment,
                $transaction,
                (string) $request->validated('reason'),
                $this->admin(),
                $request
            );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'deposit' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'Investment deposit rejected. A new deposit can now be recorded.'
        );
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }
}
