<?php

namespace App\Http\Controllers\Admin\Investments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Investments\StartInvestmentEarlyTerminationRequest;
use App\Http\Requests\Admin\Investments\StoreInvestmentSettlementAssetPayoutRequest;
use App\Http\Requests\Admin\Investments\StoreInvestmentSettlementWalletRequest;
use App\Http\Requests\Admin\Investments\StoreInvestmentTerminalProfitPayoutRequest;
use App\Models\AdminUser;
use App\Models\Investment;
use App\Models\InvestmentSettlement;
use App\Models\InvestmentSettlementAsset;
use App\Models\InvestmentTransaction;
use App\Services\Admin\Investments\InvestmentSettlementService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvestmentSettlementController extends Controller
{
    public function index(Request $request): View
    {
        $admin = $this->admin();
        abort_unless(
            $admin->hasPermission('investments.settlements.view'),
            403
        );

        $settlements = InvestmentSettlement::query()
            ->with([
                'investment.user',
                'investment.product',
            ])
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        $maturityDue = Investment::query()
            ->with(['user', 'product'])
            ->whereIn(
                'status',
                [
                    Investment::STATUS_ACTIVE,
                    Investment::STATUS_MATURED,
                ]
            )
            ->whereDate('maturity_date', '<=', today())
            ->whereDoesntHave('settlements')
            ->orderBy('maturity_date')
            ->limit(50)
            ->get();

        return view(
            'admin.investments.settlements.index',
            [
                'settlements' => $settlements,
                'maturityDue' => $maturityDue,
                'canPrepare' =>
                    $admin->hasPermission(
                        'investments.settlements.prepare'
                    ),
            ]
        );
    }

    public function show(
        Investment $investment,
        InvestmentSettlement $settlement
    ): View {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission('investments.settlements.view'),
            403
        );

        abort_unless(
            $settlement->investment_id
                === $investment->getKey(),
            404
        );

        $investment->load([
            'user',
            'product',
        ]);

        $settlement->load([
            'assets' =>
                fn ($query) =>
                    $query
                        ->orderBy('asset_symbol')
                        ->orderBy('id'),
            'transactions' =>
                fn ($query) =>
                    $query->latest('id'),
        ]);

        $terminalProfitPayout =
            $settlement->transactions
                ->firstWhere(
                    'type',
                    InvestmentTransaction::TYPE_TERMINAL_PROFIT_PAYOUT
                );

        return view(
            'admin.investments.settlements.show',
            [
                'investment' => $investment,
                'settlement' => $settlement,
                'terminalProfitPayout' =>
                    $terminalProfitPayout,
                'permissions' => [
                    'prepare' =>
                        $admin->hasPermission(
                            'investments.settlements.prepare'
                        ),
                    'approve' =>
                        $admin->hasPermission(
                            'investments.principal_settlement.approve'
                        ),
                    'wallet_record' =>
                        $admin->hasPermission(
                            'investments.settlements.wallets.record'
                        ),
                    'wallet_verify' =>
                        $admin->hasPermission(
                            'investments.settlements.wallets.verify'
                        ),
                    'payment_record' =>
                        $admin->hasPermission(
                            'investments.settlements.payments.record'
                        )
                        && $admin->hasPermission(
                            'investments.transactions.record'
                        ),
                    'payment_verify' =>
                        $admin->hasPermission(
                            'investments.settlements.payments.verify'
                        )
                        && $admin->hasPermission(
                            'investments.transactions.verify'
                        ),
                    'close' =>
                        $admin->hasPermission(
                            'investments.settlements.close'
                        ),
                ],
            ]
        );
    }

    public function initiateEarlyTermination(
        StartInvestmentEarlyTerminationRequest $request,
        Investment $investment,
        InvestmentSettlementService $service
    ): RedirectResponse {
        try {
            $settlement =
                $service->initiateEarlyTermination(
                    $investment,
                    (string) $request->validated(
                        'reason'
                    ),
                    $this->admin(),
                    $request
                );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'settlement' =>
                    $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route(
                'admin.investments.settlements.show',
                [
                    $investment,
                    $settlement,
                ]
            )
            ->with(
                'success',
                'Early-termination cutoff frozen. Trading/top-ups are stopped; complete ledger verification before settlement preparation.'
            );
    }

    public function prepareMaturity(
        Request $request,
        Investment $investment,
        InvestmentSettlementService $service
    ): RedirectResponse {
        try {
            $settlement =
                $service->prepareMaturity(
                    $investment,
                    $this->admin(),
                    $request
                );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'settlement' =>
                    $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route(
                'admin.investments.settlements.show',
                [
                    $investment,
                    $settlement,
                ]
            )
            ->with(
                'success',
                'Maturity settlement prepared from the frozen attributable-asset ledger.'
            );
    }

    public function prepare(
        Request $request,
        Investment $investment,
        InvestmentSettlement $settlement,
        InvestmentSettlementService $service
    ): RedirectResponse {
        try {
            $service->prepare(
                $investment,
                $settlement,
                $this->admin(),
                $request
            );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'settlement' =>
                    $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'Terminal settlement prepared. Record and Finance-verify every destination wallet before approval.'
        );
    }

    public function recordWallet(
        StoreInvestmentSettlementWalletRequest $request,
        Investment $investment,
        InvestmentSettlement $settlement,
        InvestmentSettlementAsset $asset,
        InvestmentSettlementService $service
    ): RedirectResponse {
        try {
            $service->recordWallet(
                $investment,
                $settlement,
                $asset,
                $request->validated(),
                $this->admin(),
                $request
            );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'wallet' =>
                    $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'Settlement destination wallet recorded for Finance verification.'
        );
    }

    public function verifyWallet(
        Request $request,
        Investment $investment,
        InvestmentSettlement $settlement,
        InvestmentSettlementAsset $asset,
        InvestmentSettlementService $service
    ): RedirectResponse {
        try {
            $service->verifyWallet(
                $investment,
                $settlement,
                $asset,
                $this->admin(),
                $request
            );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'wallet' =>
                    $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'Settlement destination wallet verified by Finance.'
        );
    }

    public function approve(
        Request $request,
        Investment $investment,
        InvestmentSettlement $settlement,
        InvestmentSettlementService $service
    ): RedirectResponse {
        try {
            $service->approve(
                $investment,
                $settlement,
                $this->admin(),
                $request
            );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'settlement' =>
                    $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'Terminal settlement approved by Finance. Asset transfers may now be recorded.'
        );
    }

    public function recordAssetPayout(
        StoreInvestmentSettlementAssetPayoutRequest $request,
        Investment $investment,
        InvestmentSettlement $settlement,
        InvestmentSettlementAsset $asset,
        InvestmentSettlementService $service
    ): RedirectResponse {
        try {
            $service->recordAssetPayout(
                $investment,
                $settlement,
                $asset,
                $request->validated(),
                $this->admin(),
                $request
            );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'payment' =>
                    $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'Settlement asset transfer recorded. TxID verification is still required.'
        );
    }

    public function verifyAssetPayout(
        Request $request,
        Investment $investment,
        InvestmentSettlement $settlement,
        InvestmentSettlementAsset $asset,
        InvestmentTransaction $transaction,
        InvestmentSettlementService $service
    ): RedirectResponse {
        try {
            $service->verifyAssetPayout(
                $investment,
                $settlement,
                $asset,
                $transaction,
                $this->admin(),
                $request
            );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'payment' =>
                    $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'Settlement asset transfer verified.'
        );
    }

    public function recordTerminalProfitPayout(
        StoreInvestmentTerminalProfitPayoutRequest $request,
        Investment $investment,
        InvestmentSettlement $settlement,
        InvestmentSettlementService $service
    ): RedirectResponse {
        try {
            $service->recordTerminalProfitPayout(
                $investment,
                $settlement,
                $request->validated(),
                $this->admin(),
                $request
            );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'payment' =>
                    $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'Early-termination customer profit payout recorded. Finance TxID verification is still required.'
        );
    }

    public function verifyTerminalProfitPayout(
        Request $request,
        Investment $investment,
        InvestmentSettlement $settlement,
        InvestmentTransaction $transaction,
        InvestmentSettlementService $service
    ): RedirectResponse {
        try {
            $service->verifyTerminalProfitPayout(
                $investment,
                $settlement,
                $transaction,
                $this->admin(),
                $request
            );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'payment' =>
                    $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'Early-termination customer profit payout verified.'
        );
    }

    public function close(
        Request $request,
        Investment $investment,
        InvestmentSettlement $settlement,
        InvestmentSettlementService $service
    ): RedirectResponse {
        try {
            $service->close(
                $investment,
                $settlement,
                $this->admin(),
                $request
            );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'settlement' =>
                    $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route(
                'admin.investments.show',
                $investment
            )
            ->with(
                'success',
                'Investment closed after full terminal settlement.'
            );
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless(
            $admin instanceof AdminUser,
            401
        );

        return $admin;
    }
}
