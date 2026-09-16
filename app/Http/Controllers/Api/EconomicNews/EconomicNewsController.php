<?php

namespace App\Http\Controllers\Api\EconomicNews;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\EconomicNews\EconomicNewsIndexRequest;
use App\Models\EconomicEvent;
use App\Models\User;
use App\Services\EconomicNews\EconomicNewsApiService;
use Illuminate\Http\JsonResponse;

class EconomicNewsController extends Controller
{
    public function index(EconomicNewsIndexRequest $request, EconomicNewsApiService $service): JsonResponse
    {
        return $this->eventList($request, $service, null, false, 'ALL');
    }

    public function highImpact(EconomicNewsIndexRequest $request, EconomicNewsApiService $service): JsonResponse
    {
        return $this->eventList($request, $service, EconomicEvent::IMPACT_HIGH, false, 'HIGH');
    }

    public function mediumImpact(EconomicNewsIndexRequest $request, EconomicNewsApiService $service): JsonResponse
    {
        return $this->eventList($request, $service, EconomicEvent::IMPACT_MEDIUM, false, 'MEDIUM');
    }

    public function calendar(EconomicNewsIndexRequest $request, EconomicNewsApiService $service): JsonResponse
    {
        return $this->eventList($request, $service, null, true, 'CALENDAR');
    }

    public function live(EconomicNewsIndexRequest $request, EconomicNewsApiService $service): JsonResponse
    {
        $this->user($request);
        $filters = $request->validated();
        $paginator = $service->liveUpdates($filters);

        return response()->json([
            'success' => true,
            'data' => [
                'live_updates' => $paginator->getCollection()
                    ->map(fn ($update): array => $service->liveUpdate($update))
                    ->values(),
                'filters' => $this->filters($filters),
            ],
            'meta' => $this->pagination($paginator),
        ]);
    }

    public function show(EconomicEvent $economicEvent, EconomicNewsApiService $service): JsonResponse
    {
        $user = request()->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');

        $event = $service->publishedEvent($economicEvent);

        return response()->json([
            'success' => true,
            'data' => [
                'event' => $service->detail($event),
            ],
        ]);
    }

    private function eventList(
        EconomicNewsIndexRequest $request,
        EconomicNewsApiService $service,
        ?string $forcedImpact,
        bool $calendarWindow,
        string $mode
    ): JsonResponse {
        $this->user($request);
        $filters = $request->validated();
        $paginator = $service->events($filters, $forcedImpact, $calendarWindow);

        return response()->json([
            'success' => true,
            'data' => [
                'events' => $paginator->getCollection()
                    ->map(fn (EconomicEvent $event): array => $service->summary($event))
                    ->values(),
                'mode' => $mode,
                'filters' => $this->filters($filters),
            ],
            'meta' => $this->pagination($paginator),
        ]);
    }

    private function user(EconomicNewsIndexRequest $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');

        return $user;
    }

    /** @return array<string, mixed> */
    private function filters(array $filters): array
    {
        return [
            'impact' => $filters['impact'] ?? null,
            'currency' => $filters['currency'] ?? null,
            'symbol' => $filters['symbol'] ?? null,
            'status' => $filters['status'] ?? null,
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
        ];
    }

    /** @return array<string, int> */
    private function pagination($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}
