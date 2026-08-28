<?php

namespace App\Http\Controllers\Admin\CopyTrading;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CopyTrading\RecordCopyTradeVerifiedBalanceRequest;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\CopyTradeBrokerAccount;
use App\Services\CopyTrading\CopyTradeActivationService;
use App\Services\CopyTrading\CopyTradeBenefitService;
use App\Services\CopyTrading\CopyTradeVerifiedBalanceService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class CopyTradeActivationController extends Controller
{
    public function show(
        Request $request,
        CopyTradeBrokerAccount $copyTradeBrokerAccount,
        CopyTradeBenefitService $benefits
    ): View {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission(
                'copy_trading.accounts.view'
            ),
            403
        );

        $copyTradeBrokerAccount->load([
            'user',
            'broker',
            'brokerMarket',
            'latestVerifiedBalanceSnapshot',
            'accountBenefit.educationEntitlement',
            'entitlementPeriods',
            'latestActivationEvaluation.gates',
        ]);

        $preview = null;

        if (
            $copyTradeBrokerAccount
                ->hasVerifiedBrokerRelationship()
            || $copyTradeBrokerAccount->isVerified()
        ) {
            $preview = $benefits->preview(
                $copyTradeBrokerAccount
            );
        }

        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->id,
            action:
                'COPY_TRADE_ACTIVATION_REVIEW_OPENED',
            module: 'COPY_TRADING',
            targetType:
                'COPY_TRADE_BROKER_ACCOUNT',
            targetId:
                $copyTradeBrokerAccount->id,
            newValues: [
                'account_masked' =>
                    $copyTradeBrokerAccount
                        ->maskedAccountNumber(),
                'status' =>
                    $copyTradeBrokerAccount->status,
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return view(
            'admin.copy_trading.accounts.activation',
            [
                'account' =>
                    $copyTradeBrokerAccount,
                'benefitPreview' =>
                    $preview,
                'canActivate' =>
                    $admin->isSuperAdmin()
                    || $admin->hasPermission(
                        'copy_trading.accounts.activate'
                    ),
                'minimumBalanceMinor' =>
                    (int) config(
                        'tradex_copy_trading.activation.minimum_verified_billable_balance_minor',
                        25000
                    ),
                'requiredCurrency' =>
                    (string) config(
                        'tradex_copy_trading.activation.currency',
                        'USD'
                    ),
            ]
        );
    }

    public function recordBalance(
        RecordCopyTradeVerifiedBalanceRequest $request,
        CopyTradeBrokerAccount $copyTradeBrokerAccount,
        CopyTradeVerifiedBalanceService $balances
    ): RedirectResponse {
        try {
            $balances->record(
                $copyTradeBrokerAccount,
                $request->balanceMinor(),
                'USD',
                'ADMIN_MANUAL',
                $request->validated(
                    'source_reference'
                ),
                $this->admin(),
                filled(
                    $request->validated(
                        'observed_at'
                    )
                )
                    ? Carbon::parse(
                        (string) $request->validated(
                            'observed_at'
                        )
                    )
                    : now()
            );
        } catch (DomainException $exception) {
            return back()
                ->withInput()
                ->withErrors([
                    'balance' =>
                        $exception->getMessage(),
                ]);
        }

        return back()->with(
            'success',
            'Verified Billable Balance snapshot recorded. Activation still requires every frozen gate to pass.'
        );
    }

    public function activate(
        Request $request,
        CopyTradeBrokerAccount $copyTradeBrokerAccount,
        CopyTradeActivationService $activation
    ): RedirectResponse {
        $admin = $this->admin();

        abort_unless(
            $admin->isSuperAdmin()
            || $admin->hasPermission(
                'copy_trading.accounts.activate'
            ),
            403
        );

        try {
            $result = $activation->activate(
                $copyTradeBrokerAccount,
                $admin,
                'ADMIN'
            );
        } catch (AuthorizationException $exception) {
            abort(403, $exception->getMessage());
        } catch (DomainException $exception) {
            return back()->withErrors([
                'activation' =>
                    $exception->getMessage(),
            ]);
        }

        if (
            (bool) ($result['activated'] ?? false)
        ) {
            return back()->with(
                'success',
                (bool) (
                    $result['already_active']
                    ?? false
                )
                    ? 'Copy Trade account is already ACTIVE.'
                    : 'All activation gates passed. Copy Trade account is now ACTIVE.'
            );
        }

        $failed = collect(
            $result['evaluation']['gates']
            ?? []
        )
            ->filter(
                fn (array $gate): bool =>
                    ! (bool) (
                        $gate['passed']
                        ?? false
                    )
            )
            ->pluck('reason_code')
            ->filter()
            ->implode(', ');

        return back()->withErrors([
            'activation' =>
                'Activation blocked. Failed gates: '
                .($failed !== ''
                    ? $failed
                    : 'UNKNOWN'),
        ]);
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
