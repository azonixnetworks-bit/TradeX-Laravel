<?php

namespace App\Http\Controllers\Admin\TradingFloor;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\TradingFloorClient;
use App\Models\TradingFloorDesk;
use App\Models\TradingFloorFloor;
use App\Models\TradingFloorMembership;
use App\Models\TradingFloorPackage;
use App\Services\Admin\TradingFloor\TradingFloorMembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TradingFloorMembershipController extends Controller
{
    public function index(Request $request): View
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.memberships.view');

        $search = trim((string) $request->query('search', ''));
        $status = strtoupper(trim((string) $request->query('status', '')));
        $view = strtoupper(trim((string) $request->query('view', '')));

        abort_unless(in_array($view, ['', 'EXPIRING', 'OVERDUE_PAYMENT', 'AUTO_RENEW'], true), 422, 'Unsupported membership view.');

        $query = TradingFloorMembership::query()->with(['client', 'package', 'floor', 'desk']);

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('membership_number', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($clientQuery) use ($search): void {
                        $clientQuery->where('client_code', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($status !== '') {
            abort_unless(in_array($status, [
                TradingFloorMembership::STATUS_PENDING,
                TradingFloorMembership::STATUS_ACTIVE,
                TradingFloorMembership::STATUS_EXPIRED,
                TradingFloorMembership::STATUS_SUSPENDED,
                TradingFloorMembership::STATUS_CANCELLED,
            ], true), 422, 'Unsupported membership status.');
            $query->where('status', $status);
        }

        if ($view === 'EXPIRING') {
            $query->whereIn('status', [TradingFloorMembership::STATUS_ACTIVE, TradingFloorMembership::STATUS_SUSPENDED])
                ->whereBetween('expires_at', [today()->toDateString(), today()->addDays(7)->toDateString()]);
        } elseif ($view === 'OVERDUE_PAYMENT') {
            $query->whereIn('status', [TradingFloorMembership::STATUS_PENDING, TradingFloorMembership::STATUS_ACTIVE, TradingFloorMembership::STATUS_SUSPENDED])
                ->where('balance_amount', '>', 0)
                ->whereDate('due_date', '<', today()->toDateString());
        } elseif ($view === 'AUTO_RENEW') {
            $query->whereIn('status', [TradingFloorMembership::STATUS_ACTIVE, TradingFloorMembership::STATUS_SUSPENDED])
                ->where('auto_renew', true);
        }

        $memberships = $query->latest('created_at')->paginate(30)->withQueryString();
        $stats = [
            'active' => TradingFloorMembership::query()->where('status', TradingFloorMembership::STATUS_ACTIVE)->count(),
            'expiring_7' => TradingFloorMembership::query()
                ->whereIn('status', [TradingFloorMembership::STATUS_ACTIVE, TradingFloorMembership::STATUS_SUSPENDED])
                ->whereBetween('expires_at', [today()->toDateString(), today()->addDays(7)->toDateString()])
                ->count(),
            'overdue_payment' => TradingFloorMembership::query()
                ->whereIn('status', [TradingFloorMembership::STATUS_PENDING, TradingFloorMembership::STATUS_ACTIVE, TradingFloorMembership::STATUS_SUSPENDED])
                ->where('balance_amount', '>', 0)
                ->whereDate('due_date', '<', today()->toDateString())
                ->count(),
            'auto_renew' => TradingFloorMembership::query()
                ->whereIn('status', [TradingFloorMembership::STATUS_ACTIVE, TradingFloorMembership::STATUS_SUSPENDED])
                ->where('auto_renew', true)
                ->count(),
        ];

        return view('admin.trading_floor.memberships.index', compact('memberships', 'search', 'status', 'view', 'stats'));
    }

    public function create(Request $request): View
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.memberships.manage');

        $clientUuid = trim((string) $request->query('client', ''));
        $clients = TradingFloorClient::query()->where('status', TradingFloorClient::STATUS_ACTIVE)->orderBy('full_name')->get(['id', 'uuid', 'client_code', 'full_name']);
        $packages = TradingFloorPackage::query()->where('status', TradingFloorPackage::STATUS_ACTIVE)->orderBy('sort_order')->orderBy('name')->get();
        $floors = TradingFloorFloor::query()->where('status', TradingFloorFloor::STATUS_ACTIVE)->orderBy('sort_order')->orderBy('name')->get();
        $desks = TradingFloorDesk::query()->where('status', TradingFloorDesk::STATUS_ACTIVE)->with('floor:id,uuid,code,name')->orderBy('code')->get();
        $occupiedDeskIds = TradingFloorMembership::query()
            ->whereNotNull('trading_floor_desk_id')
            ->whereIn('status', [TradingFloorMembership::STATUS_ACTIVE, TradingFloorMembership::STATUS_SUSPENDED, TradingFloorMembership::STATUS_PENDING])
            ->pluck('trading_floor_desk_id')->all();

        return view('admin.trading_floor.memberships.create', compact('clients', 'packages', 'floors', 'desks', 'occupiedDeskIds', 'clientUuid'));
    }

    public function store(Request $request, TradingFloorMembershipService $service): RedirectResponse
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.memberships.manage');

        $data = $request->validate([
            'trading_floor_client_id' => ['required', 'integer', 'exists:trading_floor_clients,id'],
            'trading_floor_package_id' => ['required', 'integer', 'exists:trading_floor_packages,id'],
            'trading_floor_floor_id' => ['required', 'integer', 'exists:trading_floor_floors,id'],
            'trading_floor_desk_id' => ['nullable', 'integer', 'exists:trading_floor_desks,id'],
            'starts_at' => ['required', 'date'],
            'expires_at' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in([TradingFloorMembership::STATUS_PENDING, TradingFloorMembership::STATUS_ACTIVE])],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'auto_renew' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $membership = $service->create($data, $admin);

        return redirect()->route('admin.trading_floor.memberships.show', $membership)
            ->with('success', 'Trading Floor membership created successfully.');
    }

    public function show(TradingFloorMembership $membership): View
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.memberships.view');
        $membership->load([
            'client', 'package', 'floor', 'desk', 'renewedFrom',
            'renewals' => fn ($query) => $query->latest('created_at'),
            'payments' => fn ($query) => $query->with(['receivedByAdmin:id,name', 'reversedByAdmin:id,name'])->latest('received_at'),
            'changes' => fn ($query) => $query->with('changedByAdmin:id,name')->latest('occurred_at'),
        ]);
        $packages = TradingFloorPackage::query()->where('status', TradingFloorPackage::STATUS_ACTIVE)->orderBy('sort_order')->orderBy('name')->get();
        $floors = TradingFloorFloor::query()->where('status', TradingFloorFloor::STATUS_ACTIVE)->orderBy('sort_order')->orderBy('name')->get();
        $desks = TradingFloorDesk::query()->where('status', TradingFloorDesk::STATUS_ACTIVE)->with('floor:id,uuid,code,name')->orderBy('code')->get();

        return view('admin.trading_floor.memberships.show', compact('membership', 'packages', 'floors', 'desks'));
    }

    public function activate(TradingFloorMembership $membership, TradingFloorMembershipService $service): RedirectResponse
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.memberships.manage');
        $service->activate($membership, $admin);
        return back()->with('success', 'Membership activated and client floor/desk/package allocation synchronized.');
    }

    public function cancel(Request $request, TradingFloorMembership $membership, TradingFloorMembershipService $service): RedirectResponse
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.memberships.manage');
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $service->cancel($membership, $admin, $data['reason']);
        return back()->with('success', 'Membership cancelled and active allocation released.');
    }

    public function renew(Request $request, TradingFloorMembership $membership, TradingFloorMembershipService $service): RedirectResponse
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.memberships.manage');
        $data = $request->validate([
            'trading_floor_package_id' => ['nullable', 'integer', 'exists:trading_floor_packages,id'],
            'trading_floor_floor_id' => ['nullable', 'integer', 'exists:trading_floor_floors,id'],
            'trading_floor_desk_id' => ['nullable', 'integer', 'exists:trading_floor_desks,id'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'auto_renew' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $renewal = $service->renew($membership, $data, $admin);
        return redirect()->route('admin.trading_floor.memberships.show', $renewal)
            ->with('success', 'Renewal membership created as pending. Review payment and activate when ready.');
    }

    public function changePackage(Request $request, TradingFloorMembership $membership, TradingFloorMembershipService $service): RedirectResponse
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.memberships.manage');
        $data = $request->validate([
            'trading_floor_package_id' => ['required', 'integer', 'exists:trading_floor_packages,id'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $package = TradingFloorPackage::query()->findOrFail((int) $data['trading_floor_package_id']);
        $service->changePackage($membership, $package, $admin, $data['reason']);
        return back()->with('success', 'Membership package updated with a new price snapshot.');
    }

    public function transfer(Request $request, TradingFloorMembership $membership, TradingFloorMembershipService $service): RedirectResponse
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.memberships.manage');
        $data = $request->validate([
            'trading_floor_floor_id' => ['required', 'integer', 'exists:trading_floor_floors,id'],
            'trading_floor_desk_id' => ['nullable', 'integer', 'exists:trading_floor_desks,id'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $floor = TradingFloorFloor::query()->findOrFail((int) $data['trading_floor_floor_id']);
        $desk = filled($data['trading_floor_desk_id'] ?? null)
            ? TradingFloorDesk::query()->findOrFail((int) $data['trading_floor_desk_id'])
            : null;
        $service->transfer($membership, $floor, $desk, $admin, $data['reason']);
        return back()->with('success', 'Membership floor/desk allocation transferred successfully.');
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);
        return $admin;
    }

    private function requirePermission(AdminUser $admin, string $permission): void
    {
        abort_unless($admin->isSuperAdmin() || $admin->hasPermission($permission), 403);
    }
}
