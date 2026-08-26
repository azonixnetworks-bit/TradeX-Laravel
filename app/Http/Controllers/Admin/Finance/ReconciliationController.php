<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Services\Admin\Finance\AdminFinanceService;
use Illuminate\View\View;

class ReconciliationController extends Controller
{
    public function index(
        AdminFinanceService $service
    ): View {
        $admin = $this->admin();
        $this->requirePermission($admin, 'finance.view');
        $this->requirePermission($admin, 'finance.reconciliation');

        return view(
            'admin.finance.reconciliation',
            $service->reconciliation()
        );
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }

    private function requirePermission(
        AdminUser $admin,
        string $permission
    ): void {
        abort_unless($admin->hasPermission($permission), 403);
    }
}
