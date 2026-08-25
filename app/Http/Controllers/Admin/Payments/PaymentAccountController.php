<?php

namespace App\Http\Controllers\Admin\Payments;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Services\Admin\Payments\AdminPaymentService;
use Illuminate\View\View;

class PaymentAccountController extends Controller
{
    public function index(AdminPaymentService $service): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);
        abort_unless($admin->hasPermission('payment_accounts.manage'), 403);

        return view('admin.payments.accounts', [
            'accounts' => $service->configuredAccounts(),
        ]);
    }
}
