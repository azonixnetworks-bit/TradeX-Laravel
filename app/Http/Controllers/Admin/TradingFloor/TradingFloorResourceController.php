<?php

namespace App\Http\Controllers\Admin\TradingFloor;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\TradingFloorDesk;
use App\Models\TradingFloorFloor;
use App\Models\TradingFloorMembership;
use App\Models\TradingFloorPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TradingFloorResourceController extends Controller
{
    public function index(): View
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.resources.view');

        $openStatuses = [
            TradingFloorMembership::STATUS_PENDING,
            TradingFloorMembership::STATUS_ACTIVE,
            TradingFloorMembership::STATUS_SUSPENDED,
        ];

        $occupiedDeskIds = TradingFloorMembership::query()
            ->whereNotNull('trading_floor_desk_id')
            ->whereIn('status', [TradingFloorMembership::STATUS_ACTIVE, TradingFloorMembership::STATUS_SUSPENDED])
            ->pluck('trading_floor_desk_id');

        $stats = [
            'active_floors' => TradingFloorFloor::query()->where('status', TradingFloorFloor::STATUS_ACTIVE)->count(),
            'active_desks' => TradingFloorDesk::query()->where('status', TradingFloorDesk::STATUS_ACTIVE)->count(),
            'occupied_desks' => $occupiedDeskIds->unique()->count(),
            'available_desks' => TradingFloorDesk::query()->where('status', TradingFloorDesk::STATUS_ACTIVE)->whereNotIn('id', $occupiedDeskIds)->count(),
            'active_packages' => TradingFloorPackage::query()->where('status', TradingFloorPackage::STATUS_ACTIVE)->count(),
            'open_memberships' => TradingFloorMembership::query()->whereIn('status', $openStatuses)->count(),
            'expiring_7_days' => TradingFloorMembership::query()
                ->where('status', TradingFloorMembership::STATUS_ACTIVE)
                ->whereBetween('expires_at', [now()->toDateString(), now()->addDays(7)->toDateString()])
                ->count(),
        ];

        $recentMemberships = TradingFloorMembership::query()
            ->with(['client:id,uuid,client_code,full_name', 'package:id,uuid,code,name', 'floor:id,uuid,code,name', 'desk:id,uuid,code'])
            ->latest('created_at')
            ->limit(10)
            ->get();

        return view('admin.trading_floor.resources.index', compact('stats', 'recentMemberships'));
    }

    public function floors(): View
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.resources.view');

        $floors = TradingFloorFloor::query()
            ->withCount('desks')
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        return view('admin.trading_floor.resources.floors', compact('floors'));
    }

    public function storeFloor(Request $request): RedirectResponse
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.floors.manage');

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:trading_floor_floors,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'status' => ['required', Rule::in([TradingFloorFloor::STATUS_ACTIVE, TradingFloorFloor::STATUS_INACTIVE])],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        TradingFloorFloor::create([
            ...$data,
            'code' => strtoupper(trim($data['code'])),
            'created_by_admin_id' => $admin->id,
            'updated_by_admin_id' => $admin->id,
        ]);

        return back()->with('success', 'Trading floor created.');
    }

    public function updateFloor(Request $request, TradingFloorFloor $floor): RedirectResponse
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.floors.manage');

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('trading_floor_floors', 'code')->ignore($floor->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'status' => ['required', Rule::in([TradingFloorFloor::STATUS_ACTIVE, TradingFloorFloor::STATUS_INACTIVE])],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $floor->update([
            ...$data,
            'code' => strtoupper(trim($data['code'])),
            'updated_by_admin_id' => $admin->id,
        ]);

        return back()->with('success', 'Trading floor updated.');
    }

    public function desks(Request $request): View
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.resources.view');

        $floorUuid = trim((string) $request->query('floor', ''));
        $query = TradingFloorDesk::query()->with('floor:id,uuid,code,name');

        if ($floorUuid !== '') {
            $query->whereHas('floor', fn ($builder) => $builder->where('uuid', $floorUuid));
        }

        $desks = $query->orderBy('trading_floor_floor_id')->orderBy('code')->paginate(50)->withQueryString();
        $floors = TradingFloorFloor::query()->orderBy('sort_order')->orderBy('code')->get();
        $occupiedDeskIds = TradingFloorMembership::query()
            ->whereNotNull('trading_floor_desk_id')
            ->whereIn('status', [TradingFloorMembership::STATUS_ACTIVE, TradingFloorMembership::STATUS_SUSPENDED])
            ->pluck('trading_floor_desk_id')
            ->all();

        return view('admin.trading_floor.resources.desks', compact('desks', 'floors', 'floorUuid', 'occupiedDeskIds'));
    }

    public function storeDesk(Request $request): RedirectResponse
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.desks.manage');

        $data = $request->validate([
            'trading_floor_floor_id' => ['required', 'integer', 'exists:trading_floor_floors,id'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['nullable', 'string', 'max:255'],
            'zone' => ['nullable', 'string', 'max:80'],
            'status' => ['required', Rule::in([TradingFloorDesk::STATUS_ACTIVE, TradingFloorDesk::STATUS_MAINTENANCE, TradingFloorDesk::STATUS_INACTIVE])],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $exists = TradingFloorDesk::query()
            ->where('trading_floor_floor_id', $data['trading_floor_floor_id'])
            ->where('code', strtoupper(trim($data['code'])))
            ->exists();
        abort_if($exists, 422, 'Desk code already exists on this floor.');

        TradingFloorDesk::create([
            ...$data,
            'code' => strtoupper(trim($data['code'])),
            'created_by_admin_id' => $admin->id,
            'updated_by_admin_id' => $admin->id,
        ]);

        return back()->with('success', 'Desk created.');
    }

    public function updateDesk(Request $request, TradingFloorDesk $desk): RedirectResponse
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.desks.manage');

        $data = $request->validate([
            'trading_floor_floor_id' => ['required', 'integer', 'exists:trading_floor_floors,id'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['nullable', 'string', 'max:255'],
            'zone' => ['nullable', 'string', 'max:80'],
            'status' => ['required', Rule::in([TradingFloorDesk::STATUS_ACTIVE, TradingFloorDesk::STATUS_MAINTENANCE, TradingFloorDesk::STATUS_INACTIVE])],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $exists = TradingFloorDesk::query()
            ->where('id', '<>', $desk->id)
            ->where('trading_floor_floor_id', $data['trading_floor_floor_id'])
            ->where('code', strtoupper(trim($data['code'])))
            ->exists();
        abort_if($exists, 422, 'Desk code already exists on this floor.');

        $desk->update([
            ...$data,
            'code' => strtoupper(trim($data['code'])),
            'updated_by_admin_id' => $admin->id,
        ]);

        return back()->with('success', 'Desk updated.');
    }

    public function packages(): View
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.resources.view');

        $packages = TradingFloorPackage::query()->orderBy('sort_order')->orderBy('code')->get();

        return view('admin.trading_floor.resources.packages', compact('packages'));
    }

    public function storePackage(Request $request): RedirectResponse
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.packages.manage');
        $data = $this->packageData($request);

        TradingFloorPackage::create([
            ...$data,
            'created_by_admin_id' => $admin->id,
            'updated_by_admin_id' => $admin->id,
        ]);

        return back()->with('success', 'Trading floor package created.');
    }

    public function updatePackage(Request $request, TradingFloorPackage $package): RedirectResponse
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.packages.manage');
        $data = $this->packageData($request, $package);

        $package->update([...$data, 'updated_by_admin_id' => $admin->id]);

        return back()->with('success', 'Trading floor package updated. Existing membership snapshots remain unchanged.');
    }

    /** @return array<string,mixed> */
    private function packageData(Request $request, ?TradingFloorPackage $package = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:80', Rule::unique('trading_floor_packages', 'code')->ignore($package?->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'currency' => ['required', 'string', 'size:3'],
            'price_amount' => ['required', 'numeric', 'min:0'],
            'billing_cycle' => ['required', Rule::in(['WEEKLY', 'MONTHLY', 'QUARTERLY', 'YEARLY'])],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'commission_type' => ['nullable', Rule::in(['PERCENTAGE', 'FIXED', 'HYBRID'])],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fixed_commission_amount' => ['nullable', 'numeric', 'min:0'],
            'features_text' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in([TradingFloorPackage::STATUS_ACTIVE, TradingFloorPackage::STATUS_INACTIVE])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $features = collect(preg_split('/\r\n|\r|\n/', (string) ($data['features_text'] ?? '')))
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();
        unset($data['features_text']);

        return [
            ...$data,
            'code' => strtoupper(trim($data['code'])),
            'currency' => strtoupper(trim($data['currency'])),
            'billing_cycle' => strtoupper($data['billing_cycle']),
            'commission_type' => filled($data['commission_type'] ?? null) ? strtoupper((string) $data['commission_type']) : null,
            'features' => $features,
        ];
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
