<?php

namespace App\Http\Controllers\Admin\Payments;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Services\Admin\Payments\AdminPaymentService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayPalController extends Controller
{
    public function index(
        Request $request,
        AdminPaymentService $service
    ): View {
        $admin = $this->admin();
        abort_unless($admin->hasPermission('payments.view'), 403);

        $data = $service->paypalOrders($request->only([
            'search',
            'status',
            'country',
            'from',
            'to',
            'per_page',
        ]));

        return view('admin.payments.paypal', $data);
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }
}
