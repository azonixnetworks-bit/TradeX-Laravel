<?php

namespace App\Http\Controllers\Admin\EconomicNews;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EconomicNews\StoreEconomicEventOverrideRequest;
use App\Models\AdminUser;
use App\Models\EconomicEvent;
use App\Models\EconomicEventOverride;
use App\Services\Admin\EconomicNews\AdminEconomicNewsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EconomicEventOverrideController extends Controller
{
    public function store(
        StoreEconomicEventOverrideRequest $request,
        EconomicEvent $economicEvent,
        AdminEconomicNewsService $service
    ): RedirectResponse {
        $service->createOverride(
            $economicEvent,
            $this->manager(),
            $request->validated(),
            $request
        );

        return redirect()
            ->route('admin.economic_news.events.edit', $economicEvent)
            ->with('success', 'Manual intelligence override activated and audited.');
    }

    public function revoke(
        Request $request,
        EconomicEvent $economicEvent,
        EconomicEventOverride $economicEventOverride,
        AdminEconomicNewsService $service
    ): RedirectResponse {
        $service->revokeOverride(
            $economicEvent,
            $economicEventOverride,
            $this->manager(),
            $request
        );

        return redirect()
            ->route('admin.economic_news.events.edit', $economicEvent)
            ->with('success', 'Manual override revoked. Source intelligence is effective again.');
    }

    private function manager(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);
        abort_unless(
            $admin->isSuperAdmin() || $admin->hasPermission('economic_news.overrides.manage'),
            403
        );

        return $admin;
    }
}
