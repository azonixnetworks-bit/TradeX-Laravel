<?php

namespace App\Http\Controllers\Admin\EconomicNews;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EconomicNews\StoreEconomicEventUpdateRequest;
use App\Models\AdminUser;
use App\Models\EconomicEvent;
use App\Services\Admin\EconomicNews\AdminEconomicNewsService;
use Illuminate\Http\RedirectResponse;

class EconomicEventUpdateController extends Controller
{
    public function store(
        StoreEconomicEventUpdateRequest $request,
        EconomicEvent $economicEvent,
        AdminEconomicNewsService $service
    ): RedirectResponse {
        $service->createLiveUpdate(
            $economicEvent,
            $this->manager(),
            $request->validated(),
            $request
        );

        return redirect()
            ->route('admin.economic_news.events.edit', $economicEvent)
            ->with('success', 'Economic news live update recorded and audited.');
    }

    private function manager(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);
        abort_unless(
            $admin->isSuperAdmin() || $admin->hasPermission('economic_news.live_updates.manage'),
            403
        );

        return $admin;
    }
}
