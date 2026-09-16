<?php

namespace App\Http\Controllers\Admin\EconomicNews;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\EconomicEvent;
use App\Models\EconomicEventUpdate;
use App\Models\EconomicNewsSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;

class EconomicNewsDashboardController extends Controller
{
    public function index(): Response
    {
        return $this->render('overview');
    }

    public function highImpact(): Response
    {
        return $this->render('high');
    }

    public function mediumImpact(): Response
    {
        return $this->render('medium');
    }

    public function live(): Response
    {
        return $this->render('live');
    }

    public function calendar(): Response
    {
        return $this->render('calendar');
    }

    private function render(string $mode): Response
    {
        $this->viewer();

        $now = now();
        $eventsQuery = EconomicEvent::query()
            ->with([
                'source:id,uuid,code,name',
                'pairs',
                'activeOverride',
            ])
            ->withCount('updates');

        if ($mode === 'high') {
            $this->applyEffectiveImpactFilter($eventsQuery, EconomicEvent::IMPACT_HIGH);
        } elseif ($mode === 'medium') {
            $this->applyEffectiveImpactFilter($eventsQuery, EconomicEvent::IMPACT_MEDIUM);
        } elseif ($mode === 'calendar') {
            $eventsQuery
                ->whereBetween('scheduled_at_utc', [
                    $now->copy()->subDays(7)->startOfDay(),
                    $now->copy()->addDays(60)->endOfDay(),
                ]);
        }

        $events = $mode === 'live'
            ? null
            : $eventsQuery
                ->orderByRaw('scheduled_at_utc >= ? desc', [$now])
                ->orderBy('scheduled_at_utc')
                ->paginate(30)
                ->withQueryString();

        $liveUpdates = $mode === 'live'
            ? EconomicEventUpdate::query()
                ->with([
                    'source:id,uuid,code,name',
                    'event' => fn ($query) => $query->with([
                        'pairs',
                        'activeOverride',
                    ]),
                ])
                ->latest('published_at')
                ->paginate(40)
                ->withQueryString()
            : EconomicEventUpdate::query()
                ->with(['event:id,uuid,title,impact,scheduled_at_utc'])
                ->where('is_published', true)
                ->latest('published_at')
                ->limit(8)
                ->get();

        return response()->view(
            'admin.economic_news.index',
            [
                'mode' => $mode,
                'page' => $this->pageMeta($mode),
                'stats' => [
                    'high_upcoming' => EconomicEvent::query()
                        ->where('scheduled_at_utc', '>=', $now)
                        ->where('impact', EconomicEvent::IMPACT_HIGH)
                        ->count(),
                    'medium_upcoming' => EconomicEvent::query()
                        ->where('scheduled_at_utc', '>=', $now)
                        ->where('impact', EconomicEvent::IMPACT_MEDIUM)
                        ->count(),
                    'live_events' => EconomicEvent::query()
                        ->where('status', EconomicEvent::STATUS_LIVE)
                        ->count(),
                    'published_today' => EconomicEvent::query()
                        ->where('is_published', true)
                        ->whereDate('published_at', $now->toDateString())
                        ->count(),
                    'enabled_sources' => EconomicNewsSource::query()
                        ->where('is_enabled', true)
                        ->count(),
                ],
                'events' => $events,
                'liveUpdates' => $liveUpdates,
                'generatedAt' => $now,
            ],
            200,
            $this->noStoreHeaders()
        );
    }

    private function applyEffectiveImpactFilter(Builder $query, string $impact): void
    {
        $query->where(function (Builder $query) use ($impact): void {
            $query
                ->whereHas('activeOverride', function (Builder $override) use ($impact): void {
                    $override->where('impact', $impact);
                })
                ->orWhere(function (Builder $event) use ($impact): void {
                    $event
                        ->where('impact', $impact)
                        ->where(function (Builder $effective): void {
                            $effective
                                ->whereDoesntHave('activeOverride')
                                ->orWhereHas('activeOverride', function (Builder $override): void {
                                    $override->whereNull('impact');
                                });
                        });
                });
        });
    }

    /** @return array{eyebrow:string,title:string,highlight:string,description:string} */
    private function pageMeta(string $mode): array
    {
        return match ($mode) {
            'high' => [
                'eyebrow' => 'ECONOMIC NEWS · HIGH IMPACT',
                'title' => 'High Impact',
                'highlight' => 'News',
                'description' => 'Priority macroeconomic events with effective directional bias, confidence, volatility and affected-pair mapping.',
            ],
            'medium' => [
                'eyebrow' => 'ECONOMIC NEWS · MEDIUM IMPACT',
                'title' => 'Medium Impact',
                'highlight' => 'News',
                'description' => 'Medium-impact releases tracked with the same intelligence and pair-direction model used by the high-impact feed.',
            ],
            'live' => [
                'eyebrow' => 'ECONOMIC NEWS · LIVE',
                'title' => 'Live',
                'highlight' => 'Updates',
                'description' => 'Chronological release, revision and analysis updates. Published items are preserved as an intelligence timeline.',
            ],
            'calendar' => [
                'eyebrow' => 'ECONOMIC NEWS · CALENDAR',
                'title' => 'Economic',
                'highlight' => 'Calendar',
                'description' => 'UTC-normalized economic calendar covering recent releases and the upcoming sixty-day planning window.',
            ],
            default => [
                'eyebrow' => 'ADMIN-29 · ECONOMIC NEWS INTELLIGENCE',
                'title' => 'Economic News',
                'highlight' => 'Intelligence',
                'description' => 'Central control plane for economic events, directional intelligence, affected pairs, live updates and source health.',
            ],
        };
    }

    private function viewer(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);
        abort_unless(
            $admin->isSuperAdmin() || $admin->hasPermission('economic_news.view'),
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
