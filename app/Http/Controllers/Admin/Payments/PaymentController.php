<?php

namespace App\Http\Controllers\Admin\Payments;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\PaymentOrder;
use App\Services\Admin\Payments\AdminPaymentService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(
        Request $request,
        AdminPaymentService $service
    ): View {
        $admin = $this->admin();
        $this->requirePermission($admin, 'payments.view');

        $data = $service->dashboard($request->only([
            'search',
            'provider',
            'status',
            'country',
            'from',
            'to',
            'per_page',
        ]));

        $data['stats']['nowpayments'] = PaymentOrder::query()
            ->where('provider', PaymentOrder::PROVIDER_NOWPAYMENTS)
            ->count();

        $data['permissions'] = $this->permissions($admin);

        return view('admin.payments.index', $data);
    }

    public function show(
        PaymentOrder $paymentOrder,
        AdminPaymentService $service
    ): View {
        $admin = $this->admin();
        $this->requirePermission($admin, 'payments.view');

        $data = $service->orderDetail($paymentOrder);
        $data['permissions'] = $this->permissions($admin);

        return view('admin.payments.show', $data);
    }

    private function permissions(AdminUser $admin): array
    {
        return [
            'verify' => $admin->hasPermission('payments.verify'),
            'reject' => $admin->hasPermission('payments.reject'),
            'reconcile' => $admin->hasPermission('payments.reconcile'),
            'accounts' => $admin->hasPermission('payment_accounts.manage'),
        ];
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }

    private function requirePermission(AdminUser $admin, string $permission): void
    {
        abort_unless($admin->hasPermission($permission), 403);
    }
}
