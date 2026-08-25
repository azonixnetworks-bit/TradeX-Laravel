<?php

namespace App\Http\Controllers\Admin\Affiliates;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Affiliates\UpdateAffiliateStageRequest;
use App\Models\AdminUser;
use App\Models\AffiliateStage;
use App\Services\Admin\Affiliates\AdminAffiliateService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AffiliateStageController extends Controller
{
    public function index(
        AdminAffiliateService $service
    ): View {
        $admin = $this->authenticatedAdmin();
        $this->requirePermission($admin, 'affiliates.view');

        return view('admin.affiliates.stages', [
            'stages' => $service->stages(),
            'canManage' => $admin->hasPermission('affiliate_stages.manage'),
        ]);
    }

    public function update(
        UpdateAffiliateStageRequest $request,
        AffiliateStage $stage,
        AdminAffiliateService $service
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();
        $this->requirePermission($admin, 'affiliate_stages.manage');

        try {
            $service->updateStage(
                $stage,
                $request->validated(),
                $admin,
                $request
            );
        } catch (DomainException $exception) {
            return back()
                ->withErrors([
                    'standard_commission_percent' => $exception->getMessage(),
                ])
                ->withInput();
        }

        return redirect()
            ->route('admin.affiliates.stages.index')
            ->with('success', 'Affiliate stage commercial settings updated. Existing earned commissions were not recalculated.');
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
