<?php

namespace App\Http\Controllers\Admin\EconomicNews;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EconomicNews\UpsertEconomicEventRequest;
use App\Models\AdminUser;
use App\Models\EconomicEvent;
use App\Models\EconomicNewsSource;
use App\Services\Admin\EconomicNews\AdminEconomicNewsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EconomicEventController extends Controller
{
    public function create(): Response
    {
        $admin = $this->manager();

        return $this->formResponse(
            $admin,
            new EconomicEvent([
                'impact' => EconomicEvent::IMPACT_MEDIUM,
                'status' => EconomicEvent::STATUS_SCHEDULED,
                'bias' => EconomicEvent::BIAS_NEUTRAL,
                'confidence_score' => 0,
                'volatility' => EconomicEvent::VOLATILITY_MEDIUM,
                'is_published' => false,
                'scheduled_at_utc' => now()->addHour(),
            ]),
            true
        );
    }

    public function store(
        UpsertEconomicEventRequest $request,
        AdminEconomicNewsService $service
    ): RedirectResponse {
        $event = $service->createEvent(
            $this->manager(),
            $request->validated(),
            $request
        );

        return redirect()
            ->route('admin.economic_news.events.edit', $event)
            ->with('success', 'Economic event created securely.');
    }

    public function edit(EconomicEvent $economicEvent): Response
    {
        $admin = $this->manager();
        $economicEvent->load([
            'source',
            'pairs',
            'activeOverride.overriddenByAdmin',
            'overrides.overriddenByAdmin',
            'updates' => fn ($query) => $query->limit(25),
        ]);

        return $this->formResponse($admin, $economicEvent, false);
    }

    public function update(
        UpsertEconomicEventRequest $request,
        EconomicEvent $economicEvent,
        AdminEconomicNewsService $service
    ): RedirectResponse {
        $service->updateEvent(
            $economicEvent,
            $this->manager(),
            $request->validated(),
            $request
        );

        return redirect()
            ->route('admin.economic_news.events.edit', $economicEvent)
            ->with('success', 'Economic event and permitted affected-pair fields updated securely.');
    }

    public function destroy(
        Request $request,
        EconomicEvent $economicEvent,
        AdminEconomicNewsService $service
    ): RedirectResponse {
        $service->deleteEvent($economicEvent, $this->manager(), $request);

        return redirect()
            ->route('admin.economic_news.index')
            ->with('success', 'Unpublished draft economic event deleted.');
    }

    private function formResponse(AdminUser $admin, EconomicEvent $event, bool $creating): Response
    {
        $sources = EconomicNewsSource::query()
            ->orderBy('priority')
            ->orderBy('name')
            ->get();

        return response()->view(
            'admin.economic_news.events.form',
            [
                'event' => $event,
                'creating' => $creating,
                'sources' => $sources,
                'symbolCatalog' => $this->symbolCatalog(),
                'canManagePairs' => $admin->isSuperAdmin() || $admin->hasPermission('economic_news.pairs.manage'),
                'canPublish' => $admin->isSuperAdmin() || $admin->hasPermission('economic_news.publish'),
                'canOverride' => $admin->isSuperAdmin() || $admin->hasPermission('economic_news.overrides.manage'),
                'canLiveUpdate' => $admin->isSuperAdmin() || $admin->hasPermission('economic_news.live_updates.manage'),
            ],
            200,
            $this->noStoreHeaders()
        );
    }

    /** @return array<string, array<int, string>> */
    private function symbolCatalog(): array
    {
        $catalog = [];

        foreach (['FOREX', 'CRYPTO', 'DERIV'] as $market) {
            $rows = (array) config('tradex_screener.'.strtolower($market).'.symbols', []);
            $catalog[$market] = array_values(array_filter(array_map(
                static fn (mixed $row): string => is_array($row) ? (string) ($row['symbol'] ?? '') : '',
                $rows
            )));
        }

        $catalog['STOCKS'] = [];

        return $catalog;
    }

    private function manager(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);
        abort_unless(
            $admin->isSuperAdmin() || $admin->hasPermission('economic_news.events.manage'),
            403
        );

        return $admin;
    }

    /** @return array<string, string> */
    private function noStoreHeaders(): array
    {
        return [
            'Cache-Control' => 'private, no-store, no-cache, max-age=0, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'X-Frame-Options' => 'DENY',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ];
    }
}
