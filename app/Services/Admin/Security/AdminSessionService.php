<?php

namespace App\Services\Admin\Security;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminSessionService
{
    public const SESSION_KEY = 'tradex_admin_session_uuid';

    public function ensureTracked(
        Request $request,
        AdminUser $admin
    ): AdminSession {
        $session = $request->session();
        $uuid = (string) $session->get(self::SESSION_KEY, '');

        if ($uuid !== '') {
            $tracked = AdminSession::query()
                ->where('uuid', $uuid)
                ->where('admin_user_id', $admin->getKey())
                ->first();

            if ($tracked) {
                $this->touch($request, $tracked);

                return $tracked->fresh();
            }
        }

        return DB::transaction(function () use ($request, $admin, $session): AdminSession {
            if ($admin->isSuperAdmin()) {
                AdminSession::query()
                    ->where('admin_user_id', $admin->getKey())
                    ->whereNull('revoked_at')
                    ->whereNull('logged_out_at')
                    ->lockForUpdate()
                    ->update([
                        'revoked_at' => now(),
                        'revoked_reason' => 'NEW_SUPER_ADMIN_LOGIN',
                        'updated_at' => now(),
                    ]);
            }

            $agent = (string) ($request->userAgent() ?? '');
            $device = $this->parseUserAgent($agent);

            $tracked = AdminSession::query()->create([
                'uuid' => (string) Str::uuid(),
                'admin_user_id' => $admin->getKey(),
                'session_id' => $session->getId(),
                'device_name' => $device['device_name'],
                'device_type' => $device['device_type'],
                'browser' => $device['browser'],
                'platform' => $device['platform'],
                'ip_address' => $request->ip(),
                'user_agent' => $agent !== '' ? $agent : null,
                'login_at' => now(),
                'last_seen_at' => now(),
            ]);

            $session->put(self::SESSION_KEY, $tracked->uuid);

            AuditLog::record(
                actorType: 'ADMIN',
                actorId: $admin->getKey(),
                action: 'ADMIN_DEVICE_SESSION_CREATED',
                module: 'AUTH',
                newValues: [
                    'session_uuid' => $tracked->uuid,
                    'device_name' => $tracked->device_name,
                    'device_type' => $tracked->device_type,
                    'browser' => $tracked->browser,
                    'platform' => $tracked->platform,
                    'super_admin_single_session' => $admin->isSuperAdmin(),
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            return $tracked;
        }, 3);
    }

    public function inventory(array $filters): LengthAwarePaginator
    {
        $query = AdminSession::query()
            ->with([
                'admin.roles' => fn ($query) => $query
                    ->select('admin_roles.id', 'admin_roles.name', 'admin_roles.code')
                    ->orderBy('admin_roles.name'),
            ]);

        $search = trim((string) ($filters['admin_search'] ?? ''));
        $status = strtoupper(trim((string) ($filters['admin_status'] ?? '')));

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('ip_address', 'like', '%'.$search.'%')
                    ->orWhere('device_name', 'like', '%'.$search.'%')
                    ->orWhere('browser', 'like', '%'.$search.'%')
                    ->orWhere('platform', 'like', '%'.$search.'%')
                    ->orWhereHas('admin', function ($adminQuery) use ($search): void {
                        $adminQuery
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%');
                    });
            });
        }

        if ($status === 'ACTIVE') {
            $query->whereNull('revoked_at')->whereNull('logged_out_at');
        } elseif ($status === 'REVOKED') {
            $query->whereNotNull('revoked_at');
        } elseif ($status === 'LOGGED_OUT') {
            $query->whereNull('revoked_at')->whereNotNull('logged_out_at');
        }

        $perPage = (int) ($filters['admin_per_page'] ?? 20);
        $perPage = in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 20;

        return $query
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'admin_page')
            ->withQueryString();
    }

    public function revoke(
        AdminSession $session,
        AdminUser $actor,
        Request $request
    ): bool {
        if (! $session->isActive()) {
            return false;
        }

        $currentUuid = (string) $request->session()->get(self::SESSION_KEY, '');

        if ($currentUuid !== '' && hash_equals($currentUuid, (string) $session->uuid)) {
            return false;
        }

        $session->forceFill([
            'revoked_at' => now(),
            'revoked_reason' => 'FORCE_LOGOUT_BY_ADMIN',
        ])->save();

        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $actor->getKey(),
            action: 'ADMIN_SESSION_FORCE_REVOKED',
            module: 'SECURITY',
            newValues: [
                'target_admin_id' => $session->admin_user_id,
                'session_uuid' => $session->uuid,
                'ip_address' => $session->ip_address,
                'device_name' => $session->device_name,
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return true;
    }

    public function markLoggedOut(Request $request, ?AdminUser $admin): void
    {
        if (! $admin) {
            return;
        }

        $uuid = (string) $request->session()->get(self::SESSION_KEY, '');

        if ($uuid === '') {
            return;
        }

        AdminSession::query()
            ->where('uuid', $uuid)
            ->where('admin_user_id', $admin->getKey())
            ->whereNull('logged_out_at')
            ->update([
                'logged_out_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function touch(Request $request, AdminSession $session): void
    {
        if (
            $session->last_seen_at !== null
            && $session->last_seen_at->gt(now()->subSeconds(45))
            && $session->session_id === $request->session()->getId()
        ) {
            return;
        }

        $session->forceFill([
            'session_id' => $request->session()->getId(),
            'ip_address' => $request->ip(),
            'last_seen_at' => now(),
        ])->save();
    }

    private function parseUserAgent(string $agent): array
    {
        $lower = Str::lower($agent);

        $browser = match (true) {
            str_contains($lower, 'edg/') => 'Microsoft Edge',
            str_contains($lower, 'opr/') || str_contains($lower, 'opera') => 'Opera',
            str_contains($lower, 'firefox/') => 'Firefox',
            str_contains($lower, 'chrome/') || str_contains($lower, 'crios/') => 'Chrome',
            str_contains($lower, 'safari/') => 'Safari',
            default => 'Unknown',
        };

        $platform = match (true) {
            str_contains($lower, 'windows') => 'Windows',
            str_contains($lower, 'android') => 'Android',
            str_contains($lower, 'iphone') || str_contains($lower, 'ipad') || str_contains($lower, 'ios') => 'iOS',
            str_contains($lower, 'macintosh') || str_contains($lower, 'mac os') => 'macOS',
            str_contains($lower, 'linux') => 'Linux',
            default => 'Unknown',
        };

        $deviceType = match (true) {
            str_contains($lower, 'ipad') || str_contains($lower, 'tablet') => 'Tablet',
            str_contains($lower, 'mobile') || str_contains($lower, 'iphone') || str_contains($lower, 'android') => 'Mobile',
            default => 'Desktop',
        };

        $deviceName = trim($platform.' '.$deviceType);

        return [
            'browser' => $browser,
            'platform' => $platform,
            'device_type' => $deviceType,
            'device_name' => $deviceName,
        ];
    }
}
