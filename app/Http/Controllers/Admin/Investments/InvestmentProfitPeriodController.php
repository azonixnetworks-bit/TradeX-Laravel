<?php

namespace App\Http\Controllers\Admin\Investments;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\Investment;
use App\Models\InvestmentApprovedCost;
use App\Models\InvestmentEvidence;
use App\Models\InvestmentFinancialAdjustment;
use App\Models\InvestmentProfitPeriod;
use App\Models\InvestmentTrade;
use App\Models\InvestmentTransaction;
use App\Services\Admin\Investments\InvestmentProfitEngineService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvestmentProfitPeriodController extends Controller
{
    public function index(Request $request): View
    {
        $admin = $this->admin();
        abort_unless($admin->hasPermission('investments.profit_periods.view'), 403);

        $query = InvestmentProfitPeriod::query()
            ->with(['investment.user', 'investment.product'])
            ->orderBy('period_end')
            ->orderBy('investment_id')
            ->orderBy('sequence');

        if ($request->filled('status')) {
            $query->where('status', strtoupper((string) $request->query('status')));
        }

        if ($request->boolean('due')) {
            $query->whereDate('period_end', '<=', today());
        }

        return view('admin.investments.profit_periods.index', [
            'periods' => $query->paginate(30)->withQueryString(),
        ]);
    }

    public function show(
        Investment $investment,
        InvestmentProfitPeriod $period
    ): View {
        $admin = $this->admin();
        abort_unless($admin->hasPermission('investments.profit_periods.view'), 403);
        abort_unless($period->investment_id === $investment->getKey(), 404);

        $investment->load(['user', 'product', 'assetAllocations']);

        $trades = InvestmentTrade::query()
            ->with(['evidences'])
            ->where('investment_profit_period_id', $period->getKey())
            ->orderByDesc('executed_at')
            ->get();

        $costs = InvestmentApprovedCost::query()
            ->with(['evidences'])
            ->where('investment_profit_period_id', $period->getKey())
            ->orderByDesc('occurred_at')
            ->get();

        $evidences = InvestmentEvidence::query()
            ->where('investment_profit_period_id', $period->getKey())
            ->latest('id')
            ->get();

        $adjustments = InvestmentFinancialAdjustment::query()
            ->where('investment_profit_period_id', $period->getKey())
            ->latest('id')
            ->get();

        $payout = InvestmentTransaction::query()
            ->where('investment_profit_period_id', $period->getKey())
            ->where('type', InvestmentTransaction::TYPE_PROFIT_PAYOUT)
            ->latest('id')
            ->first();

        $topUps = InvestmentTransaction::query()
            ->where('investment_id', $investment->getKey())
            ->where('type', InvestmentTransaction::TYPE_TOP_UP)
            ->latest('id')
            ->limit(20)
            ->get();

        return view('admin.investments.profit_periods.show', [
            'investment' => $investment,
            'period' => $period,
            'trades' => $trades,
            'costs' => $costs,
            'evidences' => $evidences,
            'adjustments' => $adjustments,
            'payout' => $payout,
            'topUps' => $topUps,
            'approvedCostTypes' => InvestmentApprovedCost::allowedTypes(),
            'permissions' => [
                'trade_record' => $admin->hasPermission('investments.trades.record'),
                'trade_verify' => $admin->hasPermission('investments.trades.verify'),
                'evidence_manage' => $admin->hasPermission('investments.evidence.manage'),
                'evidence_verify' => $admin->hasPermission('investments.evidence.verify'),
                'evidence_view' => $admin->hasPermission('investments.evidence.view'),
                'cost_record' => $admin->hasPermission('investments.costs.record'),
                'cost_verify' => $admin->hasPermission('investments.costs.verify'),
                'adjustment_record' => $admin->hasPermission('investments.adjustments.record'),
                'adjustment_verify' => $admin->hasPermission('investments.adjustments.verify'),
                'prepare' => $admin->hasPermission('investments.profit_periods.prepare'),
                'approve' => $admin->hasPermission('investments.profit_periods.approve'),
                'topup_record' => $admin->hasPermission('investments.topups.record'),
                'topup_verify' => $admin->hasPermission('investments.topups.verify'),
                'payout_record' => $admin->hasPermission('investments.transactions.record')
                    && $admin->hasPermission('investments.payouts.approve'),
                'payout_verify' => $admin->hasPermission('investments.transactions.verify'),
            ],
        ]);
    }

    public function prepare(
        Request $request,
        Investment $investment,
        InvestmentProfitPeriod $period,
        InvestmentProfitEngineService $service
    ): RedirectResponse {
        try {
            $service->prepare($investment, $period, $this->admin(), $request);
        } catch (DomainException $exception) {
            return back()->withErrors(['profit_period' => $exception->getMessage()]);
        }

        return back()->with('success', 'Profit period prepared and sent for Finance approval.');
    }

    public function approve(
        Request $request,
        Investment $investment,
        InvestmentProfitPeriod $period,
        InvestmentProfitEngineService $service
    ): RedirectResponse {
        try {
            $service->approve($investment, $period, $this->admin(), $request);
        } catch (DomainException $exception) {
            return back()->withErrors(['profit_period' => $exception->getMessage()]);
        }

        return back()->with('success', 'Profit period approved by Finance.');
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);
        return $admin;
    }
}
