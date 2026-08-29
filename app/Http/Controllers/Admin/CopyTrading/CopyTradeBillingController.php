<?php

namespace App\Http\Controllers\Admin\CopyTrading;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CopyTrading\LinkCopyTradeBillingPaymentRequest;
use App\Http\Requests\Admin\CopyTrading\ProcessCopyTradeExternalDepositRequest;
use App\Http\Requests\Admin\CopyTrading\StoreCopyTradeBillingAdjustmentRequest;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\CopyTradeBillingAdjustment;
use App\Models\CopyTradeBillingInvoice;
use App\Models\CopyTradeBrokerAccount;
use App\Models\CopyTradeVerifiedBalanceSnapshot;
use App\Models\Payment;
use App\Services\CopyTrading\Billing\CopyTradeBillingBalanceEventService;
use App\Services\CopyTrading\Billing\CopyTradeBillingEngine;
use App\Services\CopyTrading\Billing\CopyTradeBillingPaymentService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CopyTradeBillingController extends Controller
{
    public function index(Request $request): View
    {
        $admin = $this->admin();

        abort_unless(
            $admin->isSuperAdmin()
            || $admin->hasPermission(
                'copy_trading.billing.view'
            ),
            403
        );

        $invoices = CopyTradeBillingInvoice::query()
            ->with([
                'user:id,uuid,name,email',
                'brokerAccount.broker',
                'brokerAccount.brokerMarket',
                'cycle',
            ])
            ->latest('issued_at')
            ->paginate(30)
            ->withQueryString();

        return view(
            'admin.copy_trading.billing.index',
            [
                'invoices' => $invoices,
                'gracePeriodDays' =>
                    config(
                        'tradex_copy_trading.billing.grace_period_days'
                    ),
            ]
        );
    }

    public function show(
        CopyTradeBrokerAccount $copyTradeBrokerAccount
    ): View {
        $admin = $this->admin();

        abort_unless(
            $admin->isSuperAdmin()
            || $admin->hasPermission(
                'copy_trading.billing.view'
            ),
            403
        );

        $copyTradeBrokerAccount->load([
            'user',
            'broker',
            'brokerMarket',
            'accountBenefit',
            'verifiedBalanceSnapshots',
            'billingCycles.invoices.lines',
            'billingCycles.snapshots',
            'billingInvoices.snapshot',
            'billingInvoices.payment',
            'billingInvoices.paymentOrder',
            'billingAdjustments.createdByAdmin',
            'billingAdjustments.invoice',
        ]);

        return view(
            'admin.copy_trading.billing.show',
            [
                'account' =>
                    $copyTradeBrokerAccount,
                'canManage' =>
                    $admin->isSuperAdmin()
                    || $admin->hasPermission(
                        'copy_trading.billing.manage'
                    ),
                'canReconcile' =>
                    $admin->isSuperAdmin()
                    || (
                        $admin->hasPermission(
                            'copy_trading.billing.manage'
                        )
                        && $admin->hasPermission(
                            'payments.reconcile'
                        )
                    ),
                'canAdjust' =>
                    $admin->isSuperAdmin()
                    || $admin->hasPermission(
                        'copy_trading.billing.adjust'
                    ),
            ]
        );
    }

    public function initialize(
        CopyTradeBrokerAccount $copyTradeBrokerAccount,
        CopyTradeBillingEngine $engine
    ): RedirectResponse {
        $this->assertManage();

        try {
            $invoice =
                $engine->initializeAccountBilling(
                    $copyTradeBrokerAccount
                );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'billing' =>
                    $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            $invoice
                ? 'Copy Trade billing initialized and the applicable invoice is available.'
                : 'Billing state checked. No new paid invoice is required at this time.'
        );
    }

    public function upgrade(
        ProcessCopyTradeExternalDepositRequest $request,
        CopyTradeBrokerAccount $copyTradeBrokerAccount,
        CopyTradeBillingBalanceEventService $events,
        CopyTradeBillingEngine $engine
    ): RedirectResponse {
        $admin = $this->assertManage();

        $snapshot =
            CopyTradeVerifiedBalanceSnapshot::query()
                ->where(
                    'uuid',
                    $request->validated(
                        'balance_snapshot_uuid'
                    )
                )
                ->where(
                    'broker_account_id',
                    $copyTradeBrokerAccount
                        ->getKey()
                )
                ->first();

        if (! $snapshot) {
            return back()->withErrors([
                'billing' =>
                    'The selected balance snapshot does not belong to this broker account.',
            ]);
        }

        try {
            $event = $events->recordExternalDeposit(
                $copyTradeBrokerAccount,
                $snapshot,
                $request->depositAmountMinor(),
                $request->validated(
                    'source_reference'
                ),
                $admin
            );

            $invoice =
                $engine
                    ->evaluateExternalDepositUpgrade(
                        $copyTradeBrokerAccount,
                        $event,
                        $admin
                    );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'billing' =>
                    $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            $invoice
                ? 'External deposit evidence recorded and the prorated higher-tier difference invoice was issued.'
                : 'External deposit evidence recorded. The billing tier did not increase, so no upgrade invoice is due.'
        );
    }

    public function sweep(
        CopyTradeBrokerAccount $copyTradeBrokerAccount,
        CopyTradeBillingEngine $engine
    ): RedirectResponse {
        $this->assertManage();

        try {
            $result = $engine->sweepAccount(
                $copyTradeBrokerAccount
            );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'billing' =>
                    $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'Billing sweep result: '
            .($result['action'] ?? 'UNKNOWN')
        );
    }

    public function linkPayment(
        LinkCopyTradeBillingPaymentRequest $request,
        CopyTradeBillingInvoice $copyTradeBillingInvoice,
        CopyTradeBillingPaymentService $payments
    ): RedirectResponse {
        $payment = Payment::query()
            ->where(
                'uuid',
                $request->validated(
                    'payment_uuid'
                )
            )
            ->firstOrFail();

        try {
            $payments->linkClearedPayment(
                $copyTradeBillingInvoice,
                $payment,
                $this->admin()
            );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'payment' =>
                    $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'Cleared payment linked to the Copy Trade billing invoice.'
        );
    }

    public function storeAdjustment(
        StoreCopyTradeBillingAdjustmentRequest $request,
        CopyTradeBrokerAccount $copyTradeBrokerAccount
    ): RedirectResponse {
        $admin = $this->admin();
        $data = $request->validated();

        $invoice = null;

        if (filled($data['billing_invoice_uuid'] ?? null)) {
            $invoice = CopyTradeBillingInvoice::query()
                ->where(
                    'uuid',
                    $data['billing_invoice_uuid']
                )
                ->where(
                    'broker_account_id',
                    $copyTradeBrokerAccount->getKey()
                )
                ->first();

            if (! $invoice) {
                return back()->withErrors([
                    'adjustment' =>
                        'The selected billing invoice does not belong to this broker account.',
                ]);
            }
        }

        $adjustment =
            CopyTradeBillingAdjustment::query()
                ->create([
                    'billing_invoice_id' =>
                        $invoice?->getKey(),
                    'broker_account_id' =>
                        $copyTradeBrokerAccount
                            ->getKey(),
                    'created_by_admin_id' =>
                        $admin->getKey(),
                    'adjustment_type' =>
                        $data['adjustment_type'],
                    'amount_minor' =>
                        (int) $data[
                            'amount_minor'
                        ],
                    'reason' =>
                        $data['reason'],
                    'evidence' => [
                        'evidence_note' =>
                            $data[
                                'evidence_note'
                            ] ?? null,
                    ],
                ]);

        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->getKey(),
            action:
                'COPY_TRADE_BILLING_ADJUSTMENT_EVIDENCE_CREATED',
            module: 'COPY_TRADING',
            targetType:
                'COPY_TRADE_BILLING_ADJUSTMENT',
            targetId:
                $adjustment->getKey(),
            newValues: [
                'adjustment_uuid' =>
                    $adjustment->uuid,
                'broker_account_uuid' =>
                    $copyTradeBrokerAccount
                        ->uuid,
                'invoice_uuid' =>
                    $invoice?->uuid,
                'adjustment_type' =>
                    $adjustment
                        ->adjustment_type,
                'amount_minor' =>
                    $adjustment
                        ->amount_minor,
                'financial_side_effect' =>
                    false,
            ]
        );

        return back()->with(
            'success',
            'Immutable billing adjustment evidence recorded. No automatic refund or invoice overwrite was performed.'
        );
    }

    private function assertManage(): AdminUser
    {
        $admin = $this->admin();

        abort_unless(
            $admin->isSuperAdmin()
            || $admin->hasPermission(
                'copy_trading.billing.manage'
            ),
            403
        );

        return $admin;
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
