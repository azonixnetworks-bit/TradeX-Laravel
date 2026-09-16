<?php

namespace App\Http\Controllers\Admin\TradingFloor;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\TradingFloorAccessLog;
use App\Models\TradingFloorClient;
use App\Models\TradingFloorCommissionInvoice;
use App\Models\TradingFloorCommissionPayment;
use App\Services\Admin\TradingFloor\TradingFloorCommissionService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class TradingFloorDashboardController extends Controller
{
    public function index(TradingFloorCommissionService $commissions): Response
    {
        $this->viewer();
        $commissions->markOverdueOpenInvoices();

        $today = now()->toDateString();

        $commissionDueByCurrency = TradingFloorCommissionInvoice::query()
            ->selectRaw('currency, SUM(balance_amount) as total')
            ->whereIn('status', [
                TradingFloorCommissionInvoice::STATUS_PENDING,
                TradingFloorCommissionInvoice::STATUS_PARTIALLY_PAID,
                TradingFloorCommissionInvoice::STATUS_OVERDUE,
            ])
            ->groupBy('currency')
            ->pluck('total', 'currency')
            ->all();

        $receivedThisMonthByCurrency = TradingFloorCommissionPayment::query()
            ->selectRaw('currency, SUM(amount) as total')
            ->where('status', TradingFloorCommissionPayment::STATUS_RECEIVED)
            ->whereBetween('received_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->groupBy('currency')
            ->pluck('total', 'currency')
            ->all();

        $lastSuccessfulAccessIds = DB::table('trading_floor_access_logs')
            ->selectRaw('MAX(id)')
            ->whereDate('accessed_at', $today)
            ->whereIn('decision', ['GRANTED', 'WARNING'])
            ->groupBy('trading_floor_client_id');

        return response()->view(
            'admin.trading_floor.index',
            [
                'stats' => [
                    'active_clients' => TradingFloorClient::query()
                        ->where('status', TradingFloorClient::STATUS_ACTIVE)
                        ->count(),
                    'present_today' => TradingFloorAccessLog::query()
                        ->whereIn('id', $lastSuccessfulAccessIds)
                        ->where('direction', TradingFloorAccessLog::DIRECTION_ENTRY)
                        ->count(),
                    'commission_due_by_currency' => $commissionDueByCurrency,
                    'received_this_month_by_currency' => $receivedThisMonthByCurrency,
                    'overdue_clients' => TradingFloorCommissionInvoice::query()
                        ->where('status', TradingFloorCommissionInvoice::STATUS_OVERDUE)
                        ->distinct('trading_floor_client_id')
                        ->count('trading_floor_client_id'),
                ],
                'generatedAt' => now(),
            ],
            200,
            [
                'Cache-Control' => 'private, no-store, no-cache, max-age=0, must-revalidate',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'DENY',
                'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            ]
        );
    }

    private function viewer(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless($admin instanceof AdminUser, 401);
        abort_unless(
            $admin->isSuperAdmin() || $admin->hasPermission('trading_floor.view'),
            403
        );

        return $admin;
    }
}
