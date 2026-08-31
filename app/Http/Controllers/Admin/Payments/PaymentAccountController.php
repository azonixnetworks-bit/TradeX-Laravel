<?php

namespace App\Http\Controllers\Admin\Payments;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Services\Admin\Payments\AdminPaymentService;
use App\Services\Admin\Payments\PaymentReceivingAccountService;
use Illuminate\Http\Response;

class PaymentAccountController extends Controller
{
    public function index(
        AdminPaymentService $service,
        PaymentReceivingAccountService $receivingAccounts
    ): Response {
        $admin = auth('admin')->user();

        abort_unless($admin instanceof AdminUser, 401);
        abort_unless(
            $admin->isSuperAdmin()
            || $admin->hasPermission('payment_accounts.manage'),
            403
        );

        return response()->view(
            'admin.payments.accounts',
            [
                'accounts' => $service->configuredAccounts(),
                'receivingAccounts' => $receivingAccounts->forAdmin(),
            ],
            200,
            [
                'Cache-Control' => 'private, no-store, no-cache, max-age=0, must-revalidate',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
                'Referrer-Policy' => 'no-referrer',
                'X-Frame-Options' => 'DENY',
                'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            ]
        );
    }
}
