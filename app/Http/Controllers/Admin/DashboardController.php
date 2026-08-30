<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PaymentMoney;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class DashboardController extends Controller
{
    public function index(
        Request $request,
        PaymentMoney $money
    ): View {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);
        $admin->load('roles');

        $stats = [
            'admins' => AdminUser::count(),

            'active_admins' => AdminUser::where(
                'is_active',
                true
            )->count(),

            'roles' => AdminRole::where(
                'is_active',
                true
            )->count(),

            'permissions' => AdminPermission::count(),

            'audit_events_today' => AuditLog::whereDate(
                'created_at',
                today()
            )->count(),
        ];

        $activityTabs = $this->activityTabs($admin);
        $activityType = $this->activityType(
            $request,
            $activityTabs
        );
        $perPage = $this->perPage($request);
        $activityRecords = $activityType !== null
            ? $this->activityRecords($activityType, $perPage)
            : null;

        $systemTimezone = trim((string) config(
            'tradex_reporting.audit_timezone',
            config('app.timezone', 'UTC')
        ));

        if ($systemTimezone === '') {
            $systemTimezone = 'UTC';
        }

        try {
            $systemTime = now($systemTimezone);
        } catch (Throwable) {
            $systemTimezone = 'UTC';
            $systemTime = now('UTC');
        }

        return view('admin.dashboard.index', [
            'admin' => $admin,
            'stats' => $stats,
            'money' => $money,
            'activityTabs' => $activityTabs,
            'activityType' => $activityType,
            'activityRecords' => $activityRecords,
            'perPage' => $perPage,
            'systemTimezone' => $systemTimezone,
            'systemTimeIso' => $systemTime->toIso8601String(),
            'systemTimeDisplay' => $systemTime->format('d M Y H:i:s'),
        ]);
    }

    private function activityTabs(AdminUser $admin): array
    {
        $tabs = [];

        if ($admin->hasPermission('users.view')) {
            $tabs['customers'] = 'Recent Customers';
        }

        if ($admin->hasPermission('payments.view')) {
            $tabs['payments'] = 'Recent Payments';
        }

        if ($admin->hasPermission('audit.view')) {
            $tabs['audit'] = 'Audit Trail';
        }

        return $tabs;
    }

    private function activityType(
        Request $request,
        array $activityTabs
    ): ?string {
        if ($activityTabs === []) {
            return null;
        }

        $requested = strtolower(trim((string) $request->query(
            'activity',
            ''
        )));

        if (array_key_exists($requested, $activityTabs)) {
            return $requested;
        }

        return array_key_first($activityTabs);
    }

    private function perPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 10);

        return in_array($perPage, [5, 10, 20, 50], true)
            ? $perPage
            : 10;
    }

    private function activityRecords(
        string $activityType,
        int $perPage
    ): LengthAwarePaginator {
        return match ($activityType) {
            'customers' => User::query()
                ->select([
                    'id',
                    'name',
                    'email',
                    'whatsapp_number',
                    'country_code',
                    'account_type',
                    'status',
                    'created_at',
                ])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate($perPage)
                ->withQueryString(),

            'payments' => Payment::query()
                ->select([
                    'id',
                    'uuid',
                    'payment_order_id',
                    'user_id',
                    'provider',
                    'currency',
                    'gross_amount_minor',
                    'status',
                    'cleared_at',
                    'created_at',
                ])
                ->with([
                    'user:id,name,email',
                    'order:id,uuid',
                ])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate($perPage)
                ->withQueryString(),

            'audit' => AuditLog::query()
                ->select([
                    'id',
                    'action',
                    'module',
                    'actor_type',
                    'actor_id',
                    'ip_address',
                    'created_at',
                ])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate($perPage)
                ->withQueryString(),

            default => throw new \LogicException(
                'Unsupported dashboard activity type.'
            ),
        };
    }
}
