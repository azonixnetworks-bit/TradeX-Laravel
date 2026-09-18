<?php

namespace App\Http\Controllers\Admin\TradingFloor;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\TradingFloorAccessAttempt;
use App\Models\TradingFloorAccessLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TradingFloorAccessMonitorController extends Controller
{
    public function index(Request $request): View
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.view');

        $decision = strtoupper(trim((string) $request->query('decision', '')));
        $search = trim((string) $request->query('search', ''));
        $date = trim((string) $request->query('date', now()->toDateString()));

        validator([
            'decision' => $decision === '' ? null : $decision,
            'date' => $date,
        ], [
            'decision' => ['nullable', Rule::in(['GRANTED', 'WARNING', 'DENIED'])],
            'date' => ['required', 'date'],
        ])->validate();

        $query = TradingFloorAccessAttempt::query()
            ->with([
                'client:id,uuid,client_code,full_name,floor_code,desk_code',
                'device:id,uuid,device_code,name,floor_code',
            ])
            ->whereDate('occurred_at', $date);

        if ($decision !== '') {
            $query->where('decision', $decision);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('device_code', 'like', "%{$search}%")
                    ->orWhere('reason_code', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($clientQuery) use ($search): void {
                        $clientQuery
                            ->where('client_code', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%");
                    });
            });
        }

        $attempts = $query
            ->latest('occurred_at')
            ->paginate(50)
            ->withQueryString();

        $lastSuccessfulAccessIds = DB::table('trading_floor_access_logs')
            ->selectRaw('MAX(id)')
            ->whereDate('accessed_at', now()->toDateString())
            ->whereIn('decision', ['GRANTED', 'WARNING'])
            ->groupBy('trading_floor_client_id');

        $stats = [
            'present_now' => TradingFloorAccessLog::query()
                ->whereIn('id', $lastSuccessfulAccessIds)
                ->where('direction', TradingFloorAccessLog::DIRECTION_ENTRY)
                ->count(),
            'entries' => TradingFloorAccessAttempt::query()
                ->whereDate('occurred_at', $date)
                ->where('direction', TradingFloorAccessLog::DIRECTION_ENTRY)
                ->whereIn('decision', ['GRANTED', 'WARNING'])
                ->count(),
            'exits' => TradingFloorAccessAttempt::query()
                ->whereDate('occurred_at', $date)
                ->where('direction', TradingFloorAccessLog::DIRECTION_EXIT)
                ->whereIn('decision', ['GRANTED', 'WARNING'])
                ->count(),
            'warnings' => TradingFloorAccessAttempt::query()
                ->whereDate('occurred_at', $date)
                ->where('decision', 'WARNING')
                ->count(),
            'denied' => TradingFloorAccessAttempt::query()
                ->whereDate('occurred_at', $date)
                ->where('decision', 'DENIED')
                ->count(),
        ];

        return view('admin.trading_floor.access.monitor', compact(
            'attempts',
            'stats',
            'decision',
            'search',
            'date'
        ));
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
