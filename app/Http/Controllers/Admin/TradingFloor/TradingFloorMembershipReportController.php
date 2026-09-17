<?php

namespace App\Http\Controllers\Admin\TradingFloor;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\TradingFloorDesk;
use App\Models\TradingFloorFloor;
use App\Models\TradingFloorMembership;
use App\Models\TradingFloorMembershipPayment;
use App\Models\TradingFloorPackage;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class TradingFloorMembershipReportController extends Controller
{
    public function index(Request $request): View
    {
        $admin = $this->admin();
        $this->requireReportsPermission($admin);
        [$from, $to] = $this->range($request);

        $stats = [
            'active_memberships' => TradingFloorMembership::query()
                ->where('status', TradingFloorMembership::STATUS_ACTIVE)
                ->count(),
            'expiring_7_days' => TradingFloorMembership::query()
                ->whereIn('status', [
                    TradingFloorMembership::STATUS_ACTIVE,
                    TradingFloorMembership::STATUS_SUSPENDED,
                ])
                ->whereBetween('expires_at', [now()->toDateString(), now()->addDays(7)->toDateString()])
                ->count(),
            'overdue_payments' => TradingFloorMembership::query()
                ->whereIn('status', [
                    TradingFloorMembership::STATUS_PENDING,
                    TradingFloorMembership::STATUS_ACTIVE,
                    TradingFloorMembership::STATUS_SUSPENDED,
                ])
                ->where('balance_amount', '>', 0)
                ->whereDate('due_date', '<', now()->toDateString())
                ->count(),
            'revenue_by_currency' => TradingFloorMembershipPayment::query()
                ->selectRaw('currency, SUM(amount) as total')
                ->where('status', TradingFloorMembershipPayment::STATUS_RECEIVED)
                ->whereBetween('received_at', [$from, $to])
                ->groupBy('currency')
                ->pluck('total', 'currency')
                ->all(),
            'outstanding_by_currency' => TradingFloorMembership::query()
                ->selectRaw('currency, SUM(balance_amount) as total')
                ->whereIn('status', [
                    TradingFloorMembership::STATUS_PENDING,
                    TradingFloorMembership::STATUS_ACTIVE,
                    TradingFloorMembership::STATUS_SUSPENDED,
                ])
                ->where('balance_amount', '>', 0)
                ->groupBy('currency')
                ->pluck('total', 'currency')
                ->all(),
        ];

        $packageRows = $this->packageRevenueRows($from, $to);

        $recentPayments = TradingFloorMembershipPayment::query()
            ->with(['membership.client:id,uuid,client_code,full_name', 'membership.package:id,uuid,code,name'])
            ->whereBetween('received_at', [$from, $to])
            ->latest('received_at')
            ->limit(40)
            ->get();

        return view('admin.trading_floor.memberships.reports.index', compact(
            'from',
            'to',
            'stats',
            'packageRows',
            'recentPayments'
        ));
    }

    public function seatMap(): View
    {
        $admin = $this->admin();
        abort_unless(
            $admin->isSuperAdmin()
            || $admin->hasPermission('trading_floor.resources.view')
            || $admin->hasPermission('trading_floor.memberships.view'),
            403
        );

        $floors = TradingFloorFloor::query()
            ->with(['desks' => fn ($query) => $query->orderBy('code')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $occupancy = TradingFloorMembership::query()
            ->with(['client:id,uuid,client_code,full_name', 'package:id,uuid,code,name'])
            ->whereNotNull('trading_floor_desk_id')
            ->whereIn('status', [
                TradingFloorMembership::STATUS_PENDING,
                TradingFloorMembership::STATUS_ACTIVE,
                TradingFloorMembership::STATUS_SUSPENDED,
            ])
            ->get()
            ->keyBy('trading_floor_desk_id');

        $totalDesks = $floors->sum(fn ($floor) => $floor->desks->count());
        $activeDesks = $floors->sum(fn ($floor) => $floor->desks->where('status', TradingFloorDesk::STATUS_ACTIVE)->count());
        $occupied = $occupancy->count();

        $stats = [
            'total_desks' => $totalDesks,
            'active_desks' => $activeDesks,
            'occupied' => $occupied,
            'available' => max(0, $activeDesks - $occupied),
            'unavailable' => max(0, $totalDesks - $activeDesks),
        ];

        return view('admin.trading_floor.memberships.reports.seat-map', compact(
            'floors',
            'occupancy',
            'stats'
        ));
    }

    public function packageRevenueCsv(Request $request): StreamedResponse
    {
        $admin = $this->admin();
        $this->requireReportsPermission($admin);
        [$from, $to] = $this->range($request);
        $rows = $this->packageRevenueRows($from, $to);

        $filename = 'trading-floor-package-revenue-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Package Code', 'Package Name', 'Currency', 'Memberships Sold', 'Sold Value', 'Received']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['package_code'],
                    $row['package_name'],
                    $row['currency'],
                    $row['memberships_sold'],
                    $row['sold_value'],
                    $row['received'],
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store, no-cache, max-age=0, must-revalidate',
        ]);
    }

    /** @return Collection<int,array<string,mixed>> */
    private function packageRevenueRows(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $sales = TradingFloorMembership::query()
            ->selectRaw('trading_floor_package_id, currency, COUNT(*) as memberships_sold, SUM(price_amount) as sold_value')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('trading_floor_package_id', 'currency')
            ->get();

        $received = TradingFloorMembershipPayment::query()
            ->join('trading_floor_memberships', 'trading_floor_membership_payments.trading_floor_membership_id', '=', 'trading_floor_memberships.id')
            ->selectRaw('trading_floor_memberships.trading_floor_package_id as package_id, trading_floor_membership_payments.currency as currency, SUM(trading_floor_membership_payments.amount) as received')
            ->where('trading_floor_membership_payments.status', TradingFloorMembershipPayment::STATUS_RECEIVED)
            ->whereBetween('trading_floor_membership_payments.received_at', [$from, $to])
            ->groupBy('trading_floor_memberships.trading_floor_package_id', 'trading_floor_membership_payments.currency')
            ->get()
            ->keyBy(fn ($row) => $row->package_id.'|'.$row->currency);

        $packageIds = $sales->pluck('trading_floor_package_id')
            ->merge($received->pluck('package_id'))
            ->filter()
            ->unique()
            ->values();

        $packages = TradingFloorPackage::query()
            ->whereIn('id', $packageIds)
            ->get(['id', 'code', 'name'])
            ->keyBy('id');

        $keys = collect();
        foreach ($sales as $row) {
            $keys->push($row->trading_floor_package_id.'|'.$row->currency);
        }
        foreach ($received as $key => $row) {
            $keys->push($key);
        }

        return $keys->unique()->map(function (string $key) use ($sales, $received, $packages): array {
            [$packageId, $currency] = explode('|', $key, 2);
            $sale = $sales->first(fn ($row) => (string) $row->trading_floor_package_id === (string) $packageId && $row->currency === $currency);
            $payment = $received->get($key);
            $package = $packages->get((int) $packageId);

            return [
                'package_code' => $package?->code ?? 'UNKNOWN',
                'package_name' => $package?->name ?? 'Unknown Package',
                'currency' => $currency,
                'memberships_sold' => (int) ($sale?->memberships_sold ?? 0),
                'sold_value' => round((float) ($sale?->sold_value ?? 0), 2),
                'received' => round((float) ($payment?->received ?? 0), 2),
            ];
        })->sortBy(fn (array $row) => $row['package_name'].'|'.$row['currency'])->values();
    }

    /** @return array{0:CarbonImmutable,1:CarbonImmutable} */
    private function range(Request $request): array
    {
        $data = validator([
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ], [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ])->validate();

        $to = filled($data['to'] ?? null)
            ? CarbonImmutable::parse((string) $data['to'])->endOfDay()
            : CarbonImmutable::now()->endOfDay();
        $from = filled($data['from'] ?? null)
            ? CarbonImmutable::parse((string) $data['from'])->startOfDay()
            : $to->subDays(29)->startOfDay();

        abort_if($from->greaterThan($to), 422, 'Report start date must be before or equal to end date.');
        abort_if($from->diffInDays($to) > 366, 422, 'Membership report range cannot exceed 366 days.');

        return [$from, $to];
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }

    private function requireReportsPermission(AdminUser $admin): void
    {
        abort_unless(
            $admin->isSuperAdmin() || $admin->hasPermission('trading_floor.reports.view'),
            403
        );
    }
}
