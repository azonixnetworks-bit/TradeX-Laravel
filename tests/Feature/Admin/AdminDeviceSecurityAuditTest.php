<?php

namespace Tests\Feature\Admin;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
use Throwable;

class AdminDeviceSecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_revoke_writes_expected_audit_log(): void
    {
        $admin = $this->createAdminWithPermissions([
            'users.devices.revoke',
        ]);

        $user = $this->createUser();

        $deviceId = $this->createDevice(
            user: $user,
            slot: 'MOBILE',
            platform: 'ANDROID',
            deviceName: 'Audit Test Mobile'
        );

        $tokenId = $this->createRefreshToken(
            user: $user,
            deviceId: $deviceId,
            expiresAt: now()->addDay()
        );

        $reason = 'Device revoke audit verification test.';

        $response = $this
            ->withServerVariables([
                'REMOTE_ADDR' => '203.0.113.10',
                'HTTP_USER_AGENT' => 'TradeX Audit Security Test',
            ])
            ->actingAs($admin, 'admin')
            ->patch(
                route(
                    'admin.users.devices.revoke',
                    [
                        'user' => $user,
                        'device' => $deviceId,
                    ]
                ),
                [
                    'reason' => $reason,
                ]
            );

        $response->assertRedirect(
            route('admin.users.show', $user)
        );

        $audit = AuditLog::query()
            ->where('action', 'DEVICE_REVOKED')
            ->where('target_type', 'USER_DEVICE')
            ->where('target_id', $deviceId)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('ADMIN', $audit->actor_type);
        $this->assertSame($admin->id, $audit->actor_id);
        $this->assertSame('DEVICE_SECURITY', $audit->module);
        $this->assertSame('USER_DEVICE', $audit->target_type);
        $this->assertSame($deviceId, $audit->target_id);
        $this->assertSame($reason, $audit->new_values['reason'] ?? null);
        $this->assertSame('MOBILE', $audit->new_values['slot'] ?? null);
        $this->assertSame('ANDROID', $audit->new_values['platform'] ?? null);
        $this->assertSame('203.0.113.10', $audit->ip_address);
        $this->assertSame('TradeX Audit Security Test', $audit->user_agent);

        $this->assertDatabaseHas(
            'user_devices',
            [
                'id' => $deviceId,
                'is_active' => 0,
                'is_trusted' => 0,
                'revoked_by_admin_id' => $admin->id,
                'revoke_reason' => $reason,
            ]
        );

        $this->assertDatabaseHas(
            'user_refresh_tokens',
            [
                'id' => $tokenId,
                'revoke_reason' => 'DEVICE_REVOKED: ' . $reason,
            ]
        );

        $this->assertNotNull(
            DB::table('user_refresh_tokens')
                ->where('id', $tokenId)
                ->value('revoked_at')
        );
    }

    public function test_device_slots_reset_writes_expected_audit_log(): void
    {
        $admin = $this->createAdminWithPermissions([
            'users.devices.reset',
        ]);

        $user = $this->createUser();

        $mobileDeviceId = $this->createDevice(
            user: $user,
            slot: 'MOBILE',
            platform: 'ANDROID',
            deviceName: 'Audit Reset Mobile'
        );

        $desktopDeviceId = $this->createDevice(
            user: $user,
            slot: 'DESKTOP',
            platform: 'WINDOWS',
            deviceName: 'Audit Reset Desktop'
        );

        $this->createRefreshToken(
            user: $user,
            deviceId: $mobileDeviceId,
            expiresAt: now()->addDay()
        );

        $this->createRefreshToken(
            user: $user,
            deviceId: $desktopDeviceId,
            expiresAt: now()->addDay()
        );

        $reason = 'Device slot reset audit verification test.';

        $response = $this
            ->actingAs($admin, 'admin')
            ->post(
                route('admin.users.devices.reset', $user),
                [
                    'reason' => $reason,
                ]
            );

        $response->assertRedirect(
            route('admin.users.show', $user)
        );

        $audit = AuditLog::query()
            ->where('action', 'DEVICE_SLOTS_RESET')
            ->where('target_type', 'USER')
            ->where('target_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('ADMIN', $audit->actor_type);
        $this->assertSame($admin->id, $audit->actor_id);
        $this->assertSame('DEVICE_SECURITY', $audit->module);
        $this->assertSame(
            2,
            $audit->new_values['revoked_device_count'] ?? null
        );
        $this->assertSame($reason, $audit->new_values['reason'] ?? null);

        $this->assertDatabaseHas(
            'user_devices',
            [
                'id' => $mobileDeviceId,
                'is_active' => 0,
                'is_trusted' => 0,
            ]
        );

        $this->assertDatabaseHas(
            'user_devices',
            [
                'id' => $desktopDeviceId,
                'is_active' => 0,
                'is_trusted' => 0,
            ]
        );
    }

    public function test_session_revoke_writes_active_and_total_token_counts_to_audit(): void
    {
        $admin = $this->createAdminWithPermissions([
            'users.sessions.revoke',
        ]);

        $user = $this->createUser();

        $deviceId = $this->createDevice(
            user: $user,
            slot: 'MOBILE',
            platform: 'ANDROID',
            deviceName: 'Audit Session Mobile'
        );

        $activeTokenId = $this->createRefreshToken(
            user: $user,
            deviceId: $deviceId,
            expiresAt: now()->addHour()
        );

        $expiredTokenId = $this->createRefreshToken(
            user: $user,
            deviceId: $deviceId,
            expiresAt: now()->subHour()
        );

        $reason = 'Session revoke audit verification test.';

        $response = $this
            ->actingAs($admin, 'admin')
            ->post(
                route('admin.users.sessions.revoke', $user),
                [
                    'reason' => $reason,
                ]
            );

        $response->assertRedirect(
            route('admin.users.show', $user)
        );

        $response->assertSessionHas(
            'success',
            '1 active refresh session(s) revoked successfully.'
        );

        $audit = AuditLog::query()
            ->where('action', 'USER_SESSIONS_REVOKED')
            ->where('target_type', 'USER')
            ->where('target_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('ADMIN', $audit->actor_type);
        $this->assertSame($admin->id, $audit->actor_id);
        $this->assertSame('DEVICE_SECURITY', $audit->module);
        $this->assertSame(
            1,
            $audit->new_values['revoked_sessions'] ?? null
        );
        $this->assertSame(
            2,
            $audit->new_values['revoked_refresh_tokens_total'] ?? null
        );
        $this->assertSame($reason, $audit->new_values['reason'] ?? null);

        foreach ([$activeTokenId, $expiredTokenId] as $tokenId) {
            $this->assertNotNull(
                DB::table('user_refresh_tokens')
                    ->where('id', $tokenId)
                    ->value('revoked_at')
            );
        }

        $this->assertDatabaseHas(
            'user_devices',
            [
                'id' => $deviceId,
                'is_active' => 1,
                'is_trusted' => 1,
                'revoked_at' => null,
            ]
        );
    }

    public function test_device_revoke_rolls_back_when_audit_insert_fails(): void
    {
        $admin = $this->createAdminWithPermissions([
            'users.devices.revoke',
        ]);

        $user = $this->createUser();

        $deviceId = $this->createDevice(
            user: $user,
            slot: 'MOBILE',
            platform: 'ANDROID',
            deviceName: 'Rollback Test Mobile'
        );

        $tokenId = $this->createRefreshToken(
            user: $user,
            deviceId: $deviceId,
            expiresAt: now()->addDay()
        );

        $this->createAuditFailureTrigger(
            triggerName: 'fail_device_revoke_audit',
            action: 'DEVICE_REVOKED'
        );

        $caught = null;

        $this->withoutExceptionHandling();

        try {
            $this
                ->actingAs($admin, 'admin')
                ->patch(
                    route(
                        'admin.users.devices.revoke',
                        [
                            'user' => $user,
                            'device' => $deviceId,
                        ]
                    ),
                    [
                        'reason' => 'Forced transactional rollback verification.',
                    ]
                );
        } catch (Throwable $exception) {
            $caught = $exception;
        } finally {
            DB::statement(
                'DROP TRIGGER IF EXISTS fail_device_revoke_audit'
            );
        }

        $this->assertNotNull(
            $caught,
            'The forced audit failure did not occur.'
        );

        $this->assertDatabaseHas(
            'user_devices',
            [
                'id' => $deviceId,
                'is_active' => 1,
                'is_trusted' => 1,
                'revoked_at' => null,
                'revoked_by_admin_id' => null,
                'revoke_reason' => null,
            ]
        );

        $this->assertDatabaseHas(
            'user_refresh_tokens',
            [
                'id' => $tokenId,
                'revoked_at' => null,
                'revoke_reason' => null,
            ]
        );

        $this->assertDatabaseMissing(
            'audit_logs',
            [
                'action' => 'DEVICE_REVOKED',
                'target_id' => $deviceId,
            ]
        );
    }

    public function test_session_revocation_rolls_back_when_audit_insert_fails(): void
    {
        $admin = $this->createAdminWithPermissions([
            'users.sessions.revoke',
        ]);

        $user = $this->createUser();

        $deviceId = $this->createDevice(
            user: $user,
            slot: 'MOBILE',
            platform: 'ANDROID',
            deviceName: 'Session Rollback Mobile'
        );

        $tokenId = $this->createRefreshToken(
            user: $user,
            deviceId: $deviceId,
            expiresAt: now()->addDay()
        );

        $this->createAuditFailureTrigger(
            triggerName: 'fail_session_revoke_audit',
            action: 'USER_SESSIONS_REVOKED'
        );

        $caught = null;

        $this->withoutExceptionHandling();

        try {
            $this
                ->actingAs($admin, 'admin')
                ->post(
                    route('admin.users.sessions.revoke', $user),
                    [
                        'reason' => 'Forced session rollback verification.',
                    ]
                );
        } catch (Throwable $exception) {
            $caught = $exception;
        } finally {
            DB::statement(
                'DROP TRIGGER IF EXISTS fail_session_revoke_audit'
            );
        }

        $this->assertNotNull(
            $caught,
            'The forced session-audit failure did not occur.'
        );

        $this->assertDatabaseHas(
            'user_refresh_tokens',
            [
                'id' => $tokenId,
                'revoked_at' => null,
                'revoke_reason' => null,
            ]
        );

        $this->assertDatabaseHas(
            'user_devices',
            [
                'id' => $deviceId,
                'is_active' => 1,
                'is_trusted' => 1,
                'revoked_at' => null,
            ]
        );

        $this->assertDatabaseMissing(
            'audit_logs',
            [
                'action' => 'USER_SESSIONS_REVOKED',
                'target_id' => $user->id,
            ]
        );
    }

    private function createAdminWithPermissions(
        array $permissionCodes
    ): AdminUser {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Audit Security Test Admin',
            'email' => 'audit-security-'
                . Str::lower(Str::random(12))
                . '@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        $role = AdminRole::query()->create([
            'code' => 'AUDIT_SECURITY_'
                . strtoupper(Str::random(10)),
            'name' => 'Audit Security Test Role',
            'description' => 'Automated ADMIN-02E audit transaction role.',
            'is_system' => false,
            'is_active' => true,
        ]);

        $admin->roles()->attach($role->id);

        foreach ($permissionCodes as $permissionCode) {
            $permission = AdminPermission::query()->create([
                'code' => $permissionCode,
                'name' => $permissionCode,
                'module' => 'USERS',
                'description' => 'ADMIN-02E audit transaction test permission.',
            ]);

            $role->permissions()->attach($permission->id);
        }

        return $admin->fresh();
    }

    private function createUser(): User
    {
        return User::factory()->create([
            'uuid' => Str::uuid()->toString(),
            'whatsapp_number' => '+9477'
                . random_int(1000000, 9999999),
            'whatsapp_verified_at' => now(),
            'referral_code' => 'AUD'
                . strtoupper(Str::random(8)),
            'country_code' => 'LK',
            'language_code' => 'EN',
            'account_type' => 'FREE',
            'status' => 'ACTIVE',
        ]);
    }

    private function createDevice(
        User $user,
        string $slot,
        string $platform,
        string $deviceName
    ): int {
        $now = now();

        return DB::table('user_devices')->insertGetId([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'device_slot' => $slot,
            'platform' => $platform,
            'device_name' => $deviceName,
            'device_model' => 'TradeX Audit Test Device',
            'os_version' => '1.0',
            'app_version' => '1.0',
            'installation_id_hash' => hash(
                'sha256',
                Str::uuid()->toString()
            ),
            'device_public_key' => null,
            'is_trusted' => 1,
            'is_active' => 1,
            'registered_at' => $now,
            'trusted_at' => $now,
            'last_seen_at' => $now,
            'last_login_at' => $now,
            'last_login_ip' => '127.0.0.1',
            'revoked_at' => null,
            'revoked_by_admin_id' => null,
            'revoke_reason' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function createRefreshToken(
        User $user,
        int $deviceId,
        $expiresAt
    ): int {
        $now = now();

        return DB::table('user_refresh_tokens')->insertGetId([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'user_device_id' => $deviceId,
            'token_hash' => hash(
                'sha256',
                Str::random(64)
            ),
            'family_uuid' => Str::uuid()->toString(),
            'expires_at' => $expiresAt,
            'last_used_at' => null,
            'revoked_at' => null,
            'revoke_reason' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function createAuditFailureTrigger(
        string $triggerName,
        string $action
    ): void {
        $allowedTriggers = [
            'fail_device_revoke_audit',
            'fail_session_revoke_audit',
        ];

        if (! in_array($triggerName, $allowedTriggers, true)) {
            $this->fail(
                'Unsupported audit failure trigger.'
            );
        }

        $escapedAction = str_replace(
            "'",
            "''",
            $action
        );

        DB::statement(
            "
            CREATE TRIGGER {$triggerName}
            BEFORE INSERT ON audit_logs
            WHEN NEW.action = '{$escapedAction}'
            BEGIN
                SELECT RAISE(
                    ABORT,
                    'Forced audit transaction failure'
                );
            END
            "
        );
    }
}