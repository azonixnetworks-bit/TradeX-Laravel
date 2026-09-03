<?php

namespace App\Http\Controllers\Admin\Investments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Investments\StoreInvestmentRequest;
use App\Models\AdminUser;
use App\Models\Investment;
use App\Models\InvestmentProduct;
use App\Services\Admin\Investments\InvestmentAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvestmentController extends Controller
{
    public function index(Request $request): View
    {
        $admin = $this->admin();
        abort_unless(
            $admin->hasPermission('investments.view'),
            403
        );

        $query = Investment::query()
            ->with(['user', 'product', 'latestAgreement'])
            ->latest('id');

        if ($request->filled('status')) {
            $query->where(
                'status',
                strtoupper((string) $request->query('status'))
            );
        }

        if ($request->filled('product')) {
            $productCode = strtoupper(
                (string) $request->query('product')
            );

            $query->whereHas(
                'product',
                fn ($productQuery) =>
                    $productQuery->where('code', $productCode)
            );
        }

        if ($request->filled('q')) {
            $needle = trim((string) $request->query('q'));

            $query->where(function ($inner) use ($needle): void {
                $inner
                    ->where('investment_code', 'like', "%{$needle}%")
                    ->orWhereHas(
                        'user',
                        fn ($userQuery) =>
                            $userQuery
                                ->where('email', 'like', "%{$needle}%")
                                ->orWhere('name', 'like', "%{$needle}%")
                    );
            });
        }

        return view('admin.investments.index', [
            'investments' =>
                $query->paginate(25)->withQueryString(),
            'products' =>
                InvestmentProduct::query()
                    ->orderBy('sort_order')
                    ->get(),
            'canCreate' =>
                $admin->hasPermission('investments.create'),
        ]);
    }

    public function create(): View
    {
        $admin = $this->admin();
        abort_unless(
            $admin->hasPermission('investments.create'),
            403
        );

        return view('admin.investments.create', [
            'products' =>
                InvestmentProduct::query()
                    ->orderBy('sort_order')
                    ->get(),
        ]);
    }

    public function store(
        StoreInvestmentRequest $request,
        InvestmentAdminService $service
    ): RedirectResponse {
        $investment = $service->create(
            $request->validated(),
            $this->admin(),
            $request
        );

        return redirect()
            ->route(
                'admin.investments.show',
                $investment
            )
            ->with(
                'success',
                'Investment record created. Deposit verification, risk acceptance and signed agreement are still required before activation.'
            );
    }

    public function show(Investment $investment): View
    {
        $admin = $this->admin();
        abort_unless(
            $admin->hasPermission('investments.view'),
            403
        );

        $investment->load([
            'user.profile',
            'user.latestKycSubmission',
            'product',
            'latestAgreement',
            'transactions' =>
                fn ($query) => $query->latest('id')->limit(20),
            'profitPeriods' =>
                fn ($query) => $query->orderBy('sequence'),
            'assetAllocations' =>
                fn ($query) => $query->latest('id')->limit(20),
        ]);

        return view('admin.investments.show', [
            'investment' => $investment,
            'canViewCustomer' =>
                $admin->hasPermission(
                    'investments.customers.view'
                ),
            'canManageAgreements' =>
                $admin->hasPermission(
                    'investments.agreements.manage'
                ),
            'canViewTransactions' =>
                $admin->hasPermission(
                    'investments.transactions.view'
                ),
        ]);
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }
}
