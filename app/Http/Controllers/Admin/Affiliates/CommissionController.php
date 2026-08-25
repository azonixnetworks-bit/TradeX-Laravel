<?php

namespace App\Http\Controllers\Admin\Affiliates;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Services\Admin\Affiliates\AdminAffiliateService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommissionController extends Controller
{
    public function index(
        Request $request,
        AdminAffiliateService $service
    ): View {
        $admin = $this->authenticatedAdmin();
        $this->requirePermission($admin, 'commissions.view');

        $data = $service->commissionLedger(
            $request->only([
                'search',
                'rate_source',
                'from',
                'to',
                'per_page',
            ])
        );
        $data['canManage'] = $admin->hasPermission('commissions.manage');

        return view('admin.affiliates.commissions', $data);
    }

    private function authenticatedAdmin(): AdminUser
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
