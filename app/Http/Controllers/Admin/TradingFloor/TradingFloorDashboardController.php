<?php

namespace App\Http\Controllers\Admin\TradingFloor;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\TradingFloorAccessLog;
use App\Models\TradingFloorClient;
use App\Models\TradingFloorCommissionInvoice;
use Illuminate\Http\Response;

class TradingFloorDashboardController extends Controller
{
    public function index(): Response
    {
        $this->viewer();

        $today = now()->toDateString();

        return response()->view(
            'admin.trading_floor.index',
            [
                'stats' => [
                    'active_clients' => TradingFloorClient::query()->where('status', TradingFloorClient::STATUS_ACTIVE)->count(),
                    'present_today' => TradingFloorAccessLog::query()
                        ->whereDate('accessed_at', $today)
                        ->where('direction', TradingFloorAccessLog::DIRECTION_ENTRY)
                        ->distinct('trading_floor_client_id')
                        ->count('trading_floor_client_id'),
                    'commission_due' => TradingFloorCommissionInvoice::query()
                        ->whereIn('status', [
                            TradingFloorCommissionInvoice::STATUS_PENDING,
                            TradingFloorCommissionInvoice::STATUS_PARTIALLY_PAID,
                            TradingFloorCommissionInvoice::STATUS_OVERDUE,
                        ])
                        ->sum('balance_amount'),
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
