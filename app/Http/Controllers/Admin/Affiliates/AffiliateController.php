<?php

namespace App\Http\Controllers\Admin\Affiliates;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Affiliates\ApproveSpecialCommissionRequest;
use App\Models\AdminUser;
use App\Models\User;
use App\Services\Admin\Affiliates\AdminAffiliateService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AffiliateController extends Controller
{
    public function index(
        Request $request,
        AdminAffiliateService $service
    ): View {
        $admin = $this->authenticatedAdmin();
        $this->requirePermission($admin, 'affiliates.view');

        $data = $service->dashboard(
            $request->only([
                'search',
                'stage',
                'special',
                'sort',
                'per_page',
            ])
        );

        $data['permissions'] = [
            'view_tree' => $admin->hasPermission('affiliates.view_tree'),
            'manage' => $admin->hasPermission('affiliates.manage'),
            'manage_stages' => $admin->hasPermission('affiliate_stages.manage'),
            'view_commissions' => $admin->hasPermission('commissions.view'),
        ];

        return view('admin.affiliates.index', $data);
    }

    public function show(
        User $affiliate,
        AdminAffiliateService $service
    ): View {
        $admin = $this->authenticatedAdmin();
        $this->requirePermission($admin, 'affiliates.view');

        $data = $service->affiliateDetail($affiliate);
        $data['affiliate'] = $affiliate;
        $data['permissions'] = [
            'view_tree' => $admin->hasPermission('affiliates.view_tree'),
            'manage' => $admin->hasPermission('affiliates.manage'),
            'view_commissions' => $admin->hasPermission('commissions.view'),
        ];

        return view('admin.affiliates.show', $data);
    }

    public function approveSpecialCommission(
        ApproveSpecialCommissionRequest $request,
        User $affiliate,
        AdminAffiliateService $service
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();
        $this->requirePermission($admin, 'affiliates.manage');

        $validated = $request->validated();

        try {
            $service->approveSpecialCommission(
                $affiliate,
                (float) $validated['rate_percent'],
                $admin,
                (string) $validated['reason']
            );
        } catch (DomainException $exception) {
            return back()
                ->withErrors([
                    'rate_percent' => $exception->getMessage(),
                ])
                ->withInput();
        }

        return redirect()
            ->route('admin.affiliates.show', $affiliate)
            ->with('success', 'Special affiliate commission approved successfully.');
    }

    public function clearSpecialCommission(
        Request $request,
        User $affiliate,
        AdminAffiliateService $service
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();
        $this->requirePermission($admin, 'affiliates.manage');

        try {
            $service->clearSpecialCommission(
                $affiliate,
                $admin,
                $request
            );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'special_commission' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('admin.affiliates.show', $affiliate)
            ->with('success', 'Special affiliate commission cleared. Standard stage rates now apply to future sales.');
    }

    private function authenticatedAdmin(): AdminUser
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
