<?php

namespace App\Http\Controllers\Admin\TradingFloor;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\TradingFloorMembership;
use App\Models\TradingFloorMembershipPayment;
use Illuminate\View\View;

class TradingFloorMembershipDocumentController extends Controller
{
    public function invoice(TradingFloorMembership $membership): View
    {
        $admin = $this->admin();
        $this->requireViewPermission($admin);

        $membership->load([
            'client',
            'package',
            'floor',
            'desk',
            'payments' => fn ($query) => $query
                ->with('receivedByAdmin:id,name')
                ->orderBy('received_at'),
        ]);

        return view('admin.trading_floor.memberships.documents.invoice', compact('membership'));
    }

    public function receipt(
        TradingFloorMembership $membership,
        TradingFloorMembershipPayment $payment
    ): View {
        $admin = $this->admin();
        $this->requireViewPermission($admin);

        abort_unless($payment->trading_floor_membership_id === $membership->id, 404);

        $membership->load(['client', 'package', 'floor', 'desk']);
        $payment->load('receivedByAdmin:id,name');

        return view('admin.trading_floor.memberships.documents.receipt', compact(
            'membership',
            'payment'
        ));
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
            || $admin->hasPermission('trading_floor.memberships.view')
            || $admin->hasPermission('trading_floor.reports.view'),
            403
        );
    }
}
