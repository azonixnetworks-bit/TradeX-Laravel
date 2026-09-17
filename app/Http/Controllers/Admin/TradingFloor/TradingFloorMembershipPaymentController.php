<?php

namespace App\Http\Controllers\Admin\TradingFloor;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\TradingFloorMembership;
use App\Models\TradingFloorMembershipPayment;
use App\Services\Admin\TradingFloor\TradingFloorMembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TradingFloorMembershipPaymentController extends Controller
{
    public function store(Request $request, TradingFloorMembership $membership, TradingFloorMembershipService $service): RedirectResponse
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.membership_payments.manage');

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', Rule::in(['CASH', 'BANK_TRANSFER', 'CARD', 'PAYPAL', 'CRYPTO', 'OTHER'])],
            'transaction_reference' => ['nullable', 'string', 'max:190'],
            'received_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $service->recordPayment($membership, $data, $admin);

        return back()->with('success', 'Membership payment recorded and balance reconciled.');
    }

    public function reverse(
        Request $request,
        TradingFloorMembership $membership,
        TradingFloorMembershipPayment $payment,
        TradingFloorMembershipService $service
    ): RedirectResponse {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.membership_payments.manage');
        $data = $request->validate(['reversal_reason' => ['required', 'string', 'max:2000']]);

        $service->reversePayment($membership, $payment, $admin, $data['reversal_reason']);

        return back()->with('success', 'Membership payment reversed and balance recalculated.');
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);
        return $admin;
    }

    private function requirePermission(AdminUser $admin, string $permission): void
    {
        abort_unless($admin->isSuperAdmin() || $admin->hasPermission($permission), 403);
    }
}
