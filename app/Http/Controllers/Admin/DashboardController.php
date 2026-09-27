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
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
        abort_unless($admin->hasPermission('dashboard.view'), 403);

        $admin->load('roles');

        [$systemTimezone, $systemTime] = $this->systemClock();

        $canViewAdmins = $admin->hasPermission('admins.view');
        $canViewRoles = $admin->hasPermission('roles.view');
        $canViewAudit = $admin->hasPermission('audit.view');

        $auditDayStartUtc = $systemTime
            ->startOfDay()
            ->utc();
        $auditDayEndUtc = $systemTime
            ->endOfDay()
            ->utc();

        $stats = $this->dashboardStats(
            $canViewAdmins,
            $canViewRoles,
            $canViewAudit,
            $auditDayStartUtc,
            $auditDayEndUtc
        );

        $activityTabs = $this->activityTabs($admin);
        $activityType = $this->activityType(
            $request,
            $activityTabs
        );
        $perPage = $this->perPage($request);
        $activityRecords = $activityType !== null
            ? $this->activityRecords($activityType, $perPage)
            : null;

        return view('admin.dashboard.index', [
            'admin' => $admin,
            'adminDeviceSummary' => $admin->isSuperAdmin()
                ? app(\App\Services\Admin\Security\AdminDeviceSessionService::class)->summary()
                : null,
            'stats' => $stats,
            'money' => $money,
            'activityTabs' => $activityTabs,
            'activityType' => $activityType,
            'activityRecords' => $activityRecords,
            'activityTimestampDisplays' => $this->activityTimestampDisplays(
                $activityType,
                $activityRecords,
                $systemTimezone
            ),
            'perPage' => $perPage,
            'systemTimezone' => $systemTimezone,
            'systemTimeIso' => $systemTime->toIso8601String(),
            'systemTimeDisplay' => $systemTime->format('d M Y H:i:s'),
            'systemDateDisplay' => $systemTime->format('d M Y'),
            'lastLoginDisplay' => $admin->last_login_at !== null
                ? $this->formatUtcAttribute(
                    $admin,
                    'last_login_at',
                    $systemTimezone,
                    'd M Y H:i'
                )
                : 'First Session',
        ]);
    }

    private function dashboardStats(
        bool $canViewAdmins,
        bool $canViewRoles,
        bool $canViewAudit,
        CarbonImmutable $auditDayStartUtc,
        CarbonImmutable $auditDayEndUtc
    ): array {
        $ttlSeconds = max(
            0,
            (int) config(
                'tradex.performance.admin_stats_cache_seconds',
                30
            )
        );

        $remember = static function (
            string $key,
            callable $resolver
        ) use ($ttlSeconds): mixed {
            if ($ttlSeconds === 0) {
                return $resolver();
            }

            return Cache::remember(
                $key,
                now()->addSeconds($ttlSeconds),
                $resolver
            );
        };

        return [
            'admins' => $canViewAdmins
                ? $remember(
                    'tradex:admin-dashboard:stats:admins',
                    static fn (): int =>
                        AdminUser::query()->count()
                )
                : null,

            'active_admins' => $canViewAdmins
                ? $remember(
                    'tradex:admin-dashboard:stats:active-admins',
                    static fn (): int =>
                        AdminUser::query()
                            ->where('is_active', true)
                            ->count()
                )
                : null,

            'roles' => $canViewRoles
                ? $remember(
                    'tradex:admin-dashboard:stats:active-roles',
                    static fn (): int =>
                        AdminRole::query()
                            ->where('is_active', true)
                            ->count()
                )
                : null,

            'permissions' => $canViewRoles
                ? $remember(
                    'tradex:admin-dashboard:stats:permissions',
                    static fn (): int =>
                        AdminPermission::query()->count()
                )
                : null,

            'audit_events_today' => $canViewAudit
                ? $remember(
                    'tradex:admin-dashboard:stats:audit-today:'
                        .$auditDayStartUtc->format('Ymd'),
                    static fn (): int =>
                        AuditLog::query()
                            ->whereBetween(
                                'created_at',
                                [
                                    $auditDayStartUtc,
                                    $auditDayEndUtc,
                                ]
                            )
                            ->count()
                )
                : null,
        ];
    }

    /**
     * Resolve the configured IANA timezone once for the whole dashboard.
     * Persisted application timestamps remain canonical UTC; this timezone is
     * presentation-only and automatically follows DST rules where applicable.
     * Fixed-offset labels are intentionally rejected.
     *
     * @return array{0: string, 1: CarbonImmutable}
     */
    private function systemClock(): array
    {
        $systemTimezone = trim((string) config(
            'tradex_reporting.audit_timezone',
            config('app.timezone', 'UTC')
        ));

        if (
            $systemTimezone === ''
            || (
                $systemTimezone !== 'UTC'
                && ! in_array(
                    $systemTimezone,
                    DateTimeZone::listIdentifiers(),
                    true
                )
            )
        ) {
            $systemTimezone = 'UTC';
        }

        try {
            return [
                $systemTimezone,
                CarbonImmutable::now($systemTimezone),
            ];
        } catch (Throwable) {
            return [
                'UTC',
                CarbonImmutable::now('UTC'),
            ];
        }
    }

    /**
     * Database datetime values are stored as UTC. Read the raw value so the
     * conversion never depends on Laravel's application/display timezone.
     */
    private function formatUtcAttribute(
        Model $model,
        string $attribute,
        string $timezone,
        string $format
    ): string {
        $raw = $model->getRawOriginal($attribute);

        if ($raw === null || trim((string) $raw) === '') {
            return '—';
        }

        try {
            return CarbonImmutable::parse((string) $raw, 'UTC')
                ->setTimezone($timezone)
                ->format($format);
        } catch (Throwable) {
            return '—';
        }
    }

    private function activityTimestampDisplays(
        ?string $activityType,
        ?LengthAwarePaginator $activityRecords,
        string $timezone
    ): array {
        if ($activityType === null || $activityRecords === null) {
            return [];
        }

        [$attribute, $format] = match ($activityType) {
            'customers' => ['created_at', 'd M Y H:i'],
            'payments' => ['cleared_at', 'd M Y H:i'],
            'audit' => ['created_at', 'd M Y H:i:s'],
            default => [null, null],
        };

        if ($attribute === null || $format === null) {
            return [];
        }

        $timestamps = [];

        foreach ($activityRecords as $record) {
            if (! $record instanceof Model) {
                continue;
            }

            $timestamps[(string) $record->getKey()] = $this->formatUtcAttribute(
                $record,
                $attribute,
                $timezone,
                $format
            );
        }

        return $timestamps;
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
