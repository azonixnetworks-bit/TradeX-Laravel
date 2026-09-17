<?php

namespace App\Http\Controllers\Admin\TradingFloor;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\TradingFloorCommissionInvoice;
use App\Models\TradingFloorCommissionPayment;
use Illuminate\View\View;

class TradingFloorDocumentController extends Controller
{
    public function invoice(TradingFloorCommissionInvoice $invoice): View
    {
        $admin = $this->admin();
        $this->requireViewPermission($admin);

        $invoice->load([
            'client',
            'payments' => fn ($query) => $query->with('receivedByAdmin:id,name')->orderBy('received_at'),
        ]);

        return view('admin.trading_floor.documents.invoice', compact('invoice'));
    }

    public function receipt(
        TradingFloorCommissionInvoice $invoice,
        TradingFloorCommissionPayment $payment
    ): View {
        $admin = $this->admin();
        $this->requireViewPermission($admin);
        abort_unless($payment->trading_floor_commission_invoice_id === $invoice->id, 404);

        $payment->load('receivedByAdmin:id,name');
        $invoice->load('client');

        return view('admin.trading_floor.documents.receipt', compact('invoice', 'payment'));
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }

    private function requireViewPermission(AdminUser $admin): void
    {
        abort_unless(
            $admin->isSuperAdmin()
            || $admin->hasPermission('trading_floor.view')
            || $admin->hasPermission('trading_floor.reports.view'),
            403
        );
    }
}
