<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Services\Admin\Finance\AdminFinanceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceDashboardController extends Controller
{
    public function index(
        Request $request,
        AdminFinanceService $service
    ): View {
        $admin = $this->admin();
        $this->requirePermission($admin, 'finance.view');

        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $data = $service->dashboard(
            $validated['month'] ?? null
        );

        $data['permissions'] = [
            'reports' => $admin->hasPermission('finance.reports'),
            'reconciliation' => $admin->hasPermission('finance.reconciliation'),
        ];

        return view('admin.finance.dashboard', $data);
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
