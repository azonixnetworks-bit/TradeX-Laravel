<?php

namespace App\Http\Controllers\Admin\TradingFloor;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\TradingFloorClient;
use App\Models\TradingFloorCommissionInvoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TradingFloorClientController extends Controller
{
    public function index(Request $request): View
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.view');

        $search = trim((string) $request->query('search', ''));
        $status = strtoupper(trim((string) $request->query('status', '')));

        $query = TradingFloorClient::query()->withCount([
            'accessCredentials',
            'commissionInvoices',
        ]);

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('client_code', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('whatsapp_number', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('identity_number', 'like', "%{$search}%");
            });
        }

        if ($status !== '') {
            abort_unless(in_array($status, [
                TradingFloorClient::STATUS_ACTIVE,
                TradingFloorClient::STATUS_INACTIVE,
                TradingFloorClient::STATUS_SUSPENDED,
            ], true), 422, 'Unsupported trading floor client status.');

            $query->where('status', $status);
        }

        $clients = $query
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.trading_floor.clients.index', compact('clients', 'search', 'status'));
    }

    public function create(): View
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.clients.manage');

        return view('admin.trading_floor.clients.form', [
            'client' => new TradingFloorClient(),
            'mode' => 'create',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.clients.manage');

        $data = $this->validatedClientData($request);

        $client = DB::transaction(function () use ($data, $admin): TradingFloorClient {
            $uuid = (string) Str::uuid();

            return TradingFloorClient::create([
                ...$data,
                'uuid' => $uuid,
                'client_code' => 'TXF-'.strtoupper(substr(str_replace('-', '', $uuid), 0, 8)),
                'created_by_admin_id' => $admin->id,
                'updated_by_admin_id' => $admin->id,
            ]);
        });

        return redirect()
            ->route('admin.trading_floor.clients.show', $client)
            ->with('success', 'Trading floor customer registered successfully. Access credentials can now be assigned.');
    }

    public function show(TradingFloorClient $client): View
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.view');

        $client->load([
            'accessCredentials' => fn ($query) => $query->latest('created_at'),
            'accessLogs' => fn ($query) => $query->latest('accessed_at')->limit(20),
            'commissionInvoices' => fn ($query) => $query->with('payments')->latest('due_date')->limit(20),
        ]);

        $outstandingByCurrency = TradingFloorCommissionInvoice::query()
            ->selectRaw('currency, SUM(balance_amount) as total')
            ->where('trading_floor_client_id', $client->id)
            ->whereIn('status', [
                TradingFloorCommissionInvoice::STATUS_PENDING,
                TradingFloorCommissionInvoice::STATUS_PARTIALLY_PAID,
                TradingFloorCommissionInvoice::STATUS_OVERDUE,
            ])
            ->groupBy('currency')
            ->pluck('total', 'currency')
            ->all();

        return view('admin.trading_floor.clients.show', compact('client', 'outstandingByCurrency'));
    }

    public function edit(TradingFloorClient $client): View
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.clients.manage');

        return view('admin.trading_floor.clients.form', [
            'client' => $client,
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, TradingFloorClient $client): RedirectResponse
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.clients.manage');

        $data = $this->validatedClientData($request);

        DB::transaction(function () use ($client, $data, $admin): void {
            $client->fill($data);
            $client->updated_by_admin_id = $admin->id;
            $client->save();
        });

        return redirect()
            ->route('admin.trading_floor.clients.show', $client)
            ->with('success', 'Trading floor customer profile and commission settings updated.');
    }

    /** @return array<string, mixed> */
    private function validatedClientData(Request $request): array
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:40'],
            'whatsapp_number' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'identity_type' => ['nullable', Rule::in(['NIC', 'PASSPORT', 'OTHER'])],
            'identity_number' => ['nullable', 'string', 'max:120'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'address' => ['nullable', 'string', 'max:2000'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:40'],
            'joined_at' => ['nullable', 'date'],
            'floor_code' => ['nullable', 'string', 'max:50'],
            'desk_code' => ['nullable', 'string', 'max:50'],
            'package_code' => ['nullable', 'string', 'max:80'],
            'commission_type' => ['required', Rule::in(['PERCENTAGE', 'FIXED', 'HYBRID'])],
            'commission_rate' => [
                Rule::requiredIf(fn () => in_array(strtoupper((string) $request->input('commission_type')), ['PERCENTAGE', 'HYBRID'], true)),
                'nullable', 'numeric', 'min:0', 'max:100',
            ],
            'fixed_commission_amount' => [
                Rule::requiredIf(fn () => in_array(strtoupper((string) $request->input('commission_type')), ['FIXED', 'HYBRID'], true)),
                'nullable', 'numeric', 'min:0',
            ],
            'commission_currency' => ['required', 'string', 'size:3'],
            'billing_cycle' => ['required', Rule::in(['WEEKLY', 'MONTHLY', 'QUARTERLY'])],
            'grace_days' => ['required', 'integer', 'min:0', 'max:365'],
            'status' => ['required', Rule::in([
                TradingFloorClient::STATUS_ACTIVE,
                TradingFloorClient::STATUS_INACTIVE,
                TradingFloorClient::STATUS_SUSPENDED,
            ])],
        ]);

        foreach ([
            'identity_type', 'commission_type', 'commission_currency', 'billing_cycle', 'status',
        ] as $field) {
            if (isset($data[$field]) && $data[$field] !== null) {
                $data[$field] = strtoupper(trim((string) $data[$field]));
            }
        }

        foreach ([
            'full_name', 'mobile', 'whatsapp_number', 'email', 'identity_number', 'address',
            'emergency_contact_name', 'emergency_contact_phone', 'floor_code', 'desk_code', 'package_code',
        ] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        return $data;
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
