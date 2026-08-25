<?php

namespace App\Http\Controllers\Admin\Affiliates;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\User;
use App\Services\Admin\Affiliates\AdminAffiliateService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReferralTreeController extends Controller
{
    public function show(
        Request $request,
        User $affiliate,
        AdminAffiliateService $service
    ): View {
        $admin = $this->authenticatedAdmin();
        $this->requirePermission($admin, 'affiliates.view_tree');

        $data = $service->directReferralTree(
            $affiliate,
            $request->only(['search', 'per_page'])
        );
        $data['affiliate'] = $affiliate;

        return view('admin.affiliates.tree', $data);
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
