<?php

namespace App\Http\Controllers\Admin\TradingFloor;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\TradingFloorReminder;
use App\Services\Admin\TradingFloor\TradingFloorReminderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TradingFloorReminderController extends Controller
{
    public function index(Request $request): View
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.reports.view');

        $status = strtoupper(trim((string) $request->query('status', '')));
        $channel = strtoupper(trim((string) $request->query('channel', '')));

        validator([
            'status' => $status === '' ? null : $status,
            'channel' => $channel === '' ? null : $channel,
        ], [
            'status' => ['nullable', Rule::in([
                TradingFloorReminder::STATUS_PENDING,
                TradingFloorReminder::STATUS_SENT,
                TradingFloorReminder::STATUS_FAILED,
                TradingFloorReminder::STATUS_SKIPPED,
            ])],
            'channel' => ['nullable', Rule::in(['IN_APP', 'WHATSAPP', 'EMAIL'])],
        ])->validate();

        $query = TradingFloorReminder::query()
            ->with([
                'client:id,uuid,client_code,full_name',
                'invoice:id,uuid,invoice_number',
                'payment:id,uuid,amount,currency',
            ]);

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($channel !== '') {
            $query->where('channel', $channel);
        }

        $reminders = $query
            ->latest('created_at')
            ->paginate(50)
            ->withQueryString();

        $stats = [
            'pending' => TradingFloorReminder::query()->where('status', TradingFloorReminder::STATUS_PENDING)->count(),
            'whatsapp' => TradingFloorReminder::query()->where('channel', 'WHATSAPP')->where('status', TradingFloorReminder::STATUS_PENDING)->count(),
            'email' => TradingFloorReminder::query()->where('channel', 'EMAIL')->where('status', TradingFloorReminder::STATUS_PENDING)->count(),
            'in_app' => TradingFloorReminder::query()->where('channel', 'IN_APP')->where('status', TradingFloorReminder::STATUS_PENDING)->count(),
        ];

        return view('admin.trading_floor.reminders.index', compact(
            'reminders',
            'stats',
            'status',
            'channel'
        ));
    }

    public function generate(TradingFloorReminderService $service): RedirectResponse
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.settings.manage');

        $summary = $service->generate($admin);

        return redirect()
            ->route('admin.trading_floor.reminders.index')
            ->with(
                'success',
                sprintf(
                    'Reminder queue refreshed: %d created, %d already queued, %d skipped.',
                    $summary['created'],
                    $summary['existing'],
                    $summary['skipped']
                )
            );
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
