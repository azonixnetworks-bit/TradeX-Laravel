<?php

namespace App\Http\Controllers\Admin\CopyTrading;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CopyTrading\StoreCopyTradeBillingCalendarDayRequest;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\CopyTradeBillingCalendarDay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CopyTradeBillingCalendarController extends Controller
{
    public function index(Request $request): View
    {
        $admin = $this->admin();

        abort_unless(
            $admin->isSuperAdmin()
            || $admin->hasPermission(
                'copy_trading.billing.view'
            ),
            403
        );

        return view(
            'admin.copy_trading.billing.calendar',
            [
                'days' =>
                    CopyTradeBillingCalendarDay::query()
                        ->where(
                            'market_category',
                            'FOREX'
                        )
                        ->orderByDesc(
                            'calendar_date'
                        )
                        ->paginate(40)
                        ->withQueryString(),
                'canManage' =>
                    $admin->isSuperAdmin()
                    || $admin->hasPermission(
                        'copy_trading.billing.calendar.manage'
                    ),
            ]
        );
    }

    public function store(
        StoreCopyTradeBillingCalendarDayRequest $request
    ): RedirectResponse {
        $admin = $this->admin();
        $data = $request->validated();

        $existing =
            CopyTradeBillingCalendarDay::query()
                ->where(
                    'market_category',
                    'FOREX'
                )
                ->where(
                    'calendar_date',
                    $data['calendar_date']
                )
                ->first();

        if (
            $existing
            && $existing->locked_at !== null
        ) {
            return back()->withErrors([
                'calendar' =>
                    'This official billing calendar day is locked by billing evidence and cannot be changed.',
            ]);
        }

        $day = CopyTradeBillingCalendarDay::query()
            ->updateOrCreate(
                [
                    'market_category' =>
                        'FOREX',
                    'calendar_date' =>
                        $data['calendar_date'],
                ],
                [
                    'is_billable_day' =>
                        (bool) $data[
                            'is_billable_day'
                        ],
                    'source' =>
                        $data['source'],
                    'source_reference' =>
                        $data[
                            'source_reference'
                        ] ?? null,
                    'created_by_admin_id' =>
                        $admin->getKey(),
                ]
            );

        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->getKey(),
            action:
                'COPY_TRADE_FOREX_BILLING_CALENDAR_RECORDED',
            module: 'COPY_TRADING',
            targetType:
                'COPY_TRADE_BILLING_CALENDAR_DAY',
            targetId:
                $day->getKey(),
            newValues: [
                'calendar_date' =>
                    $day->calendar_date
                        ?->toDateString(),
                'is_billable_day' =>
                    (bool) $day
                        ->is_billable_day,
                'source' =>
                    $day->source,
                'source_reference_recorded' =>
                    filled(
                        $data[
                            'source_reference'
                        ] ?? null
                    ),
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return back()->with(
            'success',
            'Forex official billing calendar day saved.'
        );
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless(
            $admin instanceof AdminUser,
            401
        );

        return $admin;
    }
}
