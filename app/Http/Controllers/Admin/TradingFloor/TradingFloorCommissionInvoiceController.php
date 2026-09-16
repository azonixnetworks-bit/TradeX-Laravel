<?php

namespace App\Http\Controllers\Admin\TradingFloor;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\TradingFloorClient;
use App\Models\TradingFloorCommissionInvoice;
use App\Models\TradingFloorCommissionPayment;
use App\Services\Admin\TradingFloor\TradingFloorCommissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TradingFloorCommissionInvoiceController extends Controller
{
    public function index(Request $request, TradingFloorCommissionService $commissions): View
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.view');

        $commissions->markOverdueOpenInvoices();

        $status = strtoupper(trim((string) $request->query('status', '')));
        $search = trim((string) $request->query('search', ''));
        $allowedStatuses = [
            TradingFloorCommissionInvoice::STATUS_PENDING,
            TradingFloorCommissionInvoice::STATUS_PARTIALLY_PAID,
            TradingFloorCommissionInvoice::STATUS_PAID,
            TradingFloorCommissionInvoice::STATUS_OVERDUE,
            TradingFloorCommissionInvoice::STATUS_WAIVED,
            TradingFloorCommissionInvoice::STATUS_DISPUTED,
        ];

        if ($status !== '' && ! in_array($status, $allowedStatuses, true)) {
            abort(422, 'Unsupported commission invoice status filter.');
        }

        $query = TradingFloorCommissionInvoice::query()
            ->with('client:id,uuid,client_code,full_name');

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($clientQuery) use ($search): void {
                        $clientQuery
                            ->where('client_code', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%");
                    });
            });
        }

        $invoices = $query
            ->latest('due_date')
            ->paginate(30)
            ->withQueryString();

        $stats = [
            'outstanding_by_currency' => TradingFloorCommissionInvoice::query()
                ->selectRaw('currency, SUM(balance_amount) as total')
                ->whereIn('status', [
                    TradingFloorCommissionInvoice::STATUS_PENDING,
                    TradingFloorCommissionInvoice::STATUS_PARTIALLY_PAID,
                    TradingFloorCommissionInvoice::STATUS_OVERDUE,
                ])
                ->groupBy('currency')
                ->pluck('total', 'currency')
                ->all(),
            'overdue_by_currency' => TradingFloorCommissionInvoice::query()
                ->selectRaw('currency, SUM(balance_amount) as total')
                ->where('status', TradingFloorCommissionInvoice::STATUS_OVERDUE)
                ->groupBy('currency')
                ->pluck('total', 'currency')
                ->all(),
            'received_this_month_by_currency' => TradingFloorCommissionPayment::query()
                ->selectRaw('currency, SUM(amount) as total')
                ->where('status', TradingFloorCommissionPayment::STATUS_RECEIVED)
                ->whereBetween('received_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->groupBy('currency')
                ->pluck('total', 'currency')
                ->all(),
            'open_invoices' => TradingFloorCommissionInvoice::query()
                ->whereIn('status', [
                    TradingFloorCommissionInvoice::STATUS_PENDING,
                    TradingFloorCommissionInvoice::STATUS_PARTIALLY_PAID,
                    TradingFloorCommissionInvoice::STATUS_OVERDUE,
                ])
                ->count(),
        ];

        return view('admin.trading_floor.commissions.index', compact(
            'invoices',
            'stats',
            'status',
            'search'
        ));
    }

    public function create(TradingFloorClient $client): View
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.commissions.manage');

        return view('admin.trading_floor.commissions.create', compact('client'));
    }

    public function store(
        Request $request,
        TradingFloorClient $client,
        TradingFloorCommissionService $commissions
    ): RedirectResponse {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.commissions.manage');

        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'due_date' => ['required', 'date'],
            'trading_profit_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999.99'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $duplicate = TradingFloorCommissionInvoice::query()
            ->where('trading_floor_client_id', $client->id)
            ->whereDate('period_start', $data['period_start'])
            ->whereDate('period_end', $data['period_end'])
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'period_start' => 'An invoice already exists for this customer and exact billing period.',
            ]);
        }

        $profit = array_key_exists('trading_profit_amount', $data) && $data['trading_profit_amount'] !== null
            ? (float) $data['trading_profit_amount']
            : null;
        $amount = $commissions->calculateAmount($client, $profit);

        $invoice = DB::transaction(function () use ($client, $admin, $data, $profit, $amount): TradingFloorCommissionInvoice {
            $uuid = (string) Str::uuid();

            return TradingFloorCommissionInvoice::create([
                'uuid' => $uuid,
                'invoice_number' => 'TFC-'.now()->format('Ym').'-'.strtoupper(substr(str_replace('-', '', $uuid), 0, 8)),
                'trading_floor_client_id' => $client->id,
                'period_start' => $data['period_start'],
                'period_end' => $data['period_end'],
                'due_date' => $data['due_date'],
                'commission_type' => $client->commission_type,
                'trading_profit_amount' => $profit,
                'commission_rate' => $client->commission_rate,
                'commission_amount' => $amount,
                'paid_amount' => 0,
                'balance_amount' => $amount,
                'currency' => $client->commission_currency,
                'status' => TradingFloorCommissionInvoice::STATUS_PENDING,
                'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
                'created_by_admin_id' => $admin->id,
                'updated_by_admin_id' => $admin->id,
            ]);
        });

        $invoice = $commissions->reconcileInvoice($invoice);

        return redirect()
            ->route('admin.trading_floor.commissions.show', $invoice)
            ->with('success', 'Commission invoice created from the customer agreement snapshot.');
    }

    public function show(
        TradingFloorCommissionInvoice $invoice,
        TradingFloorCommissionService $commissions
    ): View {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.view');

        $invoice = $commissions->reconcileInvoice($invoice);
        $invoice->load([
            'client',
            'payments' => fn ($query) => $query
                ->with('receivedByAdmin:id,name,email')
                ->latest('received_at'),
        ]);

        return view('admin.trading_floor.commissions.show', compact('invoice'));
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
