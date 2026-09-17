<?php

namespace App\Http\Controllers\Admin\TradingFloor;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\TradingFloorAccessAttempt;
use App\Models\TradingFloorAccessLog;
use App\Models\TradingFloorClient;
use App\Models\TradingFloorCommissionInvoice;
use App\Models\TradingFloorCommissionPayment;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TradingFloorReportController extends Controller
{
    public function index(Request $request): View
    {
        $admin = $this->admin();
        $this->requireReportsPermission($admin);
        [$from, $to] = $this->range($request);

        $latestSuccessfulIds = DB::table('trading_floor_access_logs')
            ->selectRaw('MAX(id)')
            ->whereDate('accessed_at', now()->toDateString())
            ->whereIn('decision', ['GRANTED', 'WARNING'])
            ->groupBy('trading_floor_client_id');

        $presentNow = TradingFloorAccessLog::query()
            ->with('client:id,uuid,client_code,full_name,floor_code,desk_code')
            ->whereIn('id', $latestSuccessfulIds)
            ->where('direction', TradingFloorAccessLog::DIRECTION_ENTRY)
            ->latest('accessed_at')
            ->get();

        $attendanceBase = TradingFloorAccessAttempt::query()
            ->whereBetween('occurred_at', [$from, $to]);

        $stats = [
            'active_clients' => TradingFloorClient::query()
                ->where('status', TradingFloorClient::STATUS_ACTIVE)
                ->count(),
            'present_now' => $presentNow->count(),
            'entries' => (clone $attendanceBase)
                ->where('direction', TradingFloorAccessLog::DIRECTION_ENTRY)
                ->whereIn('decision', ['GRANTED', 'WARNING'])
                ->count(),
            'exits' => (clone $attendanceBase)
                ->where('direction', TradingFloorAccessLog::DIRECTION_EXIT)
                ->whereIn('decision', ['GRANTED', 'WARNING'])
                ->count(),
            'warnings' => (clone $attendanceBase)->where('decision', 'WARNING')->count(),
            'denied' => (clone $attendanceBase)->where('decision', 'DENIED')->count(),
            'issued_by_currency' => TradingFloorCommissionInvoice::query()
                ->selectRaw('currency, SUM(commission_amount) as total')
                ->whereBetween('created_at', [$from, $to])
                ->groupBy('currency')
                ->pluck('total', 'currency')
                ->all(),
            'received_by_currency' => TradingFloorCommissionPayment::query()
                ->selectRaw('currency, SUM(amount) as total')
                ->where('status', TradingFloorCommissionPayment::STATUS_RECEIVED)
                ->whereBetween('received_at', [$from, $to])
                ->groupBy('currency')
                ->pluck('total', 'currency')
                ->all(),
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
        ];

        $recentAccess = TradingFloorAccessAttempt::query()
            ->with('client:id,uuid,client_code,full_name')
            ->whereBetween('occurred_at', [$from, $to])
            ->latest('occurred_at')
            ->limit(50)
            ->get();

        $recentPayments = TradingFloorCommissionPayment::query()
            ->with(['invoice.client:id,uuid,client_code,full_name'])
            ->whereBetween('received_at', [$from, $to])
            ->latest('received_at')
            ->limit(50)
            ->get();

        $overdueInvoices = TradingFloorCommissionInvoice::query()
            ->with('client:id,uuid,client_code,full_name,mobile,whatsapp_number,email')
            ->where('status', TradingFloorCommissionInvoice::STATUS_OVERDUE)
            ->orderBy('due_date')
            ->limit(50)
            ->get();

        return view('admin.trading_floor.reports.index', [
            'from' => $from,
            'to' => $to,
            'stats' => $stats,
            'presentNow' => $presentNow,
            'recentAccess' => $recentAccess,
            'recentPayments' => $recentPayments,
            'overdueInvoices' => $overdueInvoices,
        ]);
    }

    public function clientStatement(Request $request, TradingFloorClient $client): View
    {
        $admin = $this->admin();
        $this->requireReportsPermission($admin);
        [$from, $to] = $this->range($request, 365);

        $invoices = TradingFloorCommissionInvoice::query()
            ->with([
                'payments' => fn ($query) => $query->orderBy('received_at'),
            ])
            ->where('trading_floor_client_id', $client->id)
            ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('due_date')
            ->get();

        $summary = [
            'invoiced_by_currency' => $invoices
                ->groupBy('currency')
                ->map(fn ($items) => round((float) $items->sum('commission_amount'), 2))
                ->all(),
            'paid_by_currency' => $invoices
                ->groupBy('currency')
                ->map(fn ($items) => round((float) $items->sum('paid_amount'), 2))
                ->all(),
            'balance_by_currency' => $invoices
                ->groupBy('currency')
                ->map(fn ($items) => round((float) $items->sum('balance_amount'), 2))
                ->all(),
        ];

        return view('admin.trading_floor.reports.statement', compact(
            'client',
            'invoices',
            'summary',
            'from',
            'to'
        ));
    }

    public function attendanceCsv(Request $request): StreamedResponse
    {
        $admin = $this->admin();
        $this->requireReportsPermission($admin);
        [$from, $to] = $this->range($request);

        $filename = 'trading-floor-attendance-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($from, $to): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Time', 'Customer Code', 'Customer Name', 'Device', 'Method', 'Direction', 'Decision', 'Reason Code']);

            TradingFloorAccessAttempt::query()
                ->with('client:id,client_code,full_name')
                ->whereBetween('occurred_at', [$from, $to])
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($handle): void {
                    foreach ($rows as $row) {
                        fputcsv($handle, [
                            $row->occurred_at?->format('Y-m-d H:i:s'),
                            $row->client?->client_code,
                            $row->client?->full_name,
                            $row->device_code,
                            $row->credential_type,
                            $row->direction,
                            $row->decision,
                            $row->reason_code,
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store, no-cache, max-age=0, must-revalidate',
        ]);
    }

    public function financeCsv(Request $request): StreamedResponse
    {
        $admin = $this->admin();
        $this->requireReportsPermission($admin);
        [$from, $to] = $this->range($request);

        $filename = 'trading-floor-finance-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($from, $to): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Invoice', 'Customer Code', 'Customer Name', 'Period Start', 'Period End', 'Due Date',
                'Currency', 'Commission Amount', 'Paid Amount', 'Balance', 'Status',
            ]);

            TradingFloorCommissionInvoice::query()
                ->with('client:id,client_code,full_name')
                ->whereBetween('created_at', [$from, $to])
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($handle): void {
                    foreach ($rows as $invoice) {
                        fputcsv($handle, [
                            $invoice->invoice_number,
                            $invoice->client?->client_code,
                            $invoice->client?->full_name,
                            $invoice->period_start?->format('Y-m-d'),
                            $invoice->period_end?->format('Y-m-d'),
                            $invoice->due_date?->format('Y-m-d'),
                            $invoice->currency,
                            $invoice->commission_amount,
                            $invoice->paid_amount,
                            $invoice->balance_amount,
                            $invoice->status,
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store, no-cache, max-age=0, must-revalidate',
        ]);
    }

    /** @return array{0:CarbonImmutable,1:CarbonImmutable} */
    private function range(Request $request, int $defaultDays = 30): array
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
            : $to->subDays($defaultDays - 1)->startOfDay();

        abort_if($from->greaterThan($to), 422, 'Report start date must be before or equal to end date.');
        abort_if($from->diffInDays($to) > 366, 422, 'Trading Floor report range cannot exceed 366 days.');

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
