<?php

namespace App\Http\Controllers\Admin\Investments;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\Investment;
use App\Models\InvestmentAgreement;
use App\Models\InvestmentProduct;
use App\Models\InvestmentTransaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvestmentDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $admin = $this->admin();
        abort_unless(
            $admin->hasPermission('investments.view'),
            403
        );

        $activeStatuses = [
            Investment::STATUS_ACTIVE,
            Investment::STATUS_EARLY_TERMINATION_REVIEW,
        ];

        $stats = [
            'total_investments' =>
                Investment::query()->count(),

            'active_investments' =>
                Investment::query()
                    ->whereIn('status', $activeStatuses)
                    ->count(),

            'total_investors' =>
                Investment::query()
                    ->distinct('user_id')
                    ->count('user_id'),

            'active_capital_usdt' =>
                Investment::query()
                    ->whereIn('status', $activeStatuses)
                    ->sum('deposit_amount'),

            'awaiting_deposit' =>
                Investment::query()
                    ->where(
                        'status',
                        Investment::STATUS_AWAITING_DEPOSIT
                    )
                    ->count(),

            /*
             * Count investments that do not yet have any signed/verified
             * agreement. This correctly includes brand-new investments
             * where no agreement row has been created yet.
             */
            'unsigned_agreements' =>
                Investment::query()
                    ->whereDoesntHave(
                        'agreements',
                        fn ($query) => $query->whereIn(
                            'status',
                            [
                                InvestmentAgreement::STATUS_SIGNED,
                                InvestmentAgreement::STATUS_VERIFIED,
                            ]
                        )
                    )
                    ->count(),

            'verified_transactions' =>
                InvestmentTransaction::query()
                    ->where(
                        'status',
                        InvestmentTransaction::STATUS_VERIFIED
                    )
                    ->count(),
        ];

        $products = InvestmentProduct::query()
            ->orderBy('sort_order')
            ->get();

        $recentInvestments = Investment::query()
            ->with(['user', 'product'])
            ->latest('id')
            ->limit(10)
            ->get();

        return view('admin.investments.dashboard', [
            'stats' => $stats,
            'products' => $products,
            'recentInvestments' => $recentInvestments,
            'canCreate' =>
                $admin->hasPermission('investments.create'),
        ]);
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }
}
