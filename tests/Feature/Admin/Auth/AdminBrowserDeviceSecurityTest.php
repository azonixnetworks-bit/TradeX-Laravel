<?php

namespace Tests\Feature\Admin\Auth;

use App\Jobs\SendAdminDeviceAlert;
use App\Models\AdminDevice;
use App\Models\AdminDeviceAlert;
use App\Models\AdminDeviceSession;
use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Services\Admin\Security\AdminDeviceSecurityService;
use App\Services\Admin\Security\AdminDeviceSessionService;
use App\Services\Auth\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminBrowserDeviceSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const DESKTOP = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/140.0';

    private const MOBILE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Mobile Safari';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'tradex_admin_devices.enabled' => true,
            'tradex_admin_devices.admin_desktop_limit' => 1,
            'tradex_admin_devices.admin_mobile_limit' => 1,
            'tradex_admin_hardening.totp.required' => false,
        ]);
        Queue::fake();
        Http::preventStrayRequests();
    }

    public function test_one_mobile_and_one_desktop_are_allowed_and_same_browser_can_return(): void
    {
        $admin = $this->admin(true);
        $desktop = $this->request('a');
        $mobile = $this->request('b', self::MOBILE);
        $this->security()->bind($admin, $desktop);
        $this->security()->bind($admin, $mobile);
        $this->security()->bind($admin, $desktop);
        $this->assertDatabaseCount('admin_devices', 2);
        $this->assertTrue($this->security()->validSession($admin, $desktop));
        $this->assertTrue($this->security()->validSession($admin, $mobile));
        $this->assertDatabaseCount('admin_device_incidents', 0);
    }

    public function test_extra_desktop_is_blocked_even_when_mobile_slot_is_empty_and_existing_session_survives(): void
    {
        $admin = $this->admin(true);
        $approved = $this->request('a');
        $this->security()->bind($admin, $approved);
        $this->deny($admin, $this->request('c'));
        $this->assertTrue($this->security()->validSession($admin, $approved));
        $this->assertDatabaseHas('admin_devices', ['status' => 'BLOCKED', 'active_slot' => null]);
        $this->assertDatabaseHas('admin_device_incidents', ['reason' => 'DEVICE_SLOT_OCCUPIED']);
        $this->assertDatabaseHas('admin_device_alerts', ['channel' => 'email', 'recipient' => $admin->email]);
        $this->assertDatabaseHas('admin_device_alerts', ['channel' => 'whatsapp', 'failure_code' => 'RECIPIENT_NOT_CONFIGURED']);
        Queue::assertPushed(SendAdminDeviceAlert::class, 1);
    }

    public function test_third_device_is_blocked_for_delegated_admin_too(): void
    {
        $admin = $this->admin();
        $this->security()->bind($admin, $this->request('a'));
        $this->security()->bind($admin, $this->request('b', self::MOBILE));
        $this->deny($admin, $this->request('c', self::MOBILE));
        $this->assertSame(2, AdminDevice::where('status', 'ACTIVE')->count());
    }

    public function test_production_policy_allows_configured_super_admin_five_devices_in_any_mix(): void
    {
        $admin = $this->admin(true);
        config([
            'tradex.super_admin.email' => $admin->email,
            'tradex_admin_devices.super_admin_total_limit' => 5,
        ]);

        foreach (['a', 'b', 'c', 'd', 'e'] as $token) {
            $this->security()->bind($admin, $this->request($token));
        }

        $this->deny($admin, $this->request('f', self::MOBILE));
        $this->assertSame(5, AdminDevice::where('status', 'ACTIVE')->count());
        $this->assertDatabaseHas('admin_device_incidents', ['reason' => 'DEVICE_LIMIT_REACHED']);
    }

    public function test_production_policy_allows_other_admins_two_desktops_and_one_mobile(): void
    {
        config([
            'tradex_admin_devices.admin_desktop_limit' => 2,
            'tradex_admin_devices.admin_mobile_limit' => 1,
        ]);
        $admin = $this->admin();

        $this->security()->bind($admin, $this->request('a'));
        $this->security()->bind($admin, $this->request('b'));
        $this->security()->bind($admin, $this->request('c', self::MOBILE));
        $this->deny($admin, $this->request('d'));

        $this->assertSame(3, AdminDevice::where('status', 'ACTIVE')->count());
        $this->assertDatabaseHas('admin_device_incidents', ['reason' => 'DEVICE_LIMIT_REACHED']);
    }

    public function test_revoke_and_reapprove_cannot_resurrect_old_sessions(): void
    {
        $admin = $this->admin(true);
        $original = $this->request('a');
        $this->security()->bind($admin, $original);
        $device = AdminDevice::firstOrFail();
        $this->security()->manage($admin, $admin, $device, 'revoke', 'Lost old browser', $original);
        $this->assertFalse($this->security()->validSession($admin, $original));
        $this->deny($admin, $this->request('a'));
        $this->security()->manage($admin, $admin, $device, 'approve', 'Recovered browser', $original);
        $this->assertFalse($this->security()->validSession($admin, $original));
        $newSession = $this->request('a');
        $this->security()->bind($admin, $newSession);
        $this->assertTrue($this->security()->validSession($admin, $newSession));
    }

    public function test_blocked_device_requires_explicit_super_admin_approval_after_slot_is_freed(): void
    {
        $admin = $this->admin(true);
        $this->security()->bind($admin, $this->request('a'));
        $this->deny($admin, $this->request('b'));
        $original = AdminDevice::where('status', 'ACTIVE')->firstOrFail();
        $blocked = AdminDevice::where('status', 'BLOCKED')->firstOrFail();
        try {
            $this->security()->manage($admin, $admin, $blocked, 'approve', 'Replace browser', $this->request('a'));
            $this->fail('Occupied slot must not be overwritten.');
        } catch (ValidationException) {
            $this->assertSame('BLOCKED', $blocked->fresh()->status);
        }
        $this->security()->manage($admin, $admin, $original, 'revoke', 'Replace old browser', $this->request('a'));
        $this->deny($admin, $this->request('b'));
        $this->security()->manage($admin, $admin, $blocked, 'approve', 'Approve replacement', $this->request('a'));
        $request = $this->request('b');
        $this->security()->bind($admin, $request);
        $this->assertTrue($this->security()->validSession($admin, $request));
    }

    public function test_real_totp_login_denies_an_extra_device_after_correct_password_and_code(): void
    {
        $admin = $this->admin(true);
        $secret = 'JBSWY3DPEHPK3PXP';
        $admin->forceFill(['totp_secret' => $secret, 'totp_confirmed_at' => now()])->save();
        $this->security()->bind($admin, $this->request('a'));
        $this->withCookie(AdminDeviceSecurityService::COOKIE, str_repeat('b', 64))->withHeader('User-Agent', self::DESKTOP);
        $this->post(route('admin.login.submit'), ['email' => $admin->email, 'password' => 'StrongAdmin#2026!'])
            ->assertRedirect(route('admin.totp.challenge'));
        $this->assertDatabaseCount('admin_devices', 1);
        $this->post(route('admin.totp.challenge.submit'), ['code' => app(TotpService::class)->codeAt($secret, now()->timestamp)])
            ->assertRedirect(route('admin.login'))->assertSessionHasErrors('device');
        $this->assertGuest('admin');
        $this->assertDatabaseHas('admin_devices', ['status' => 'BLOCKED']);
        $this->withCookie(config('session.cookie'), session()->getId())
            ->get(route('admin.login'))->assertSee('Contact Super Admin');
    }

    public function test_invalid_password_never_claims_a_device_or_sends_alerts(): void
    {
        $admin = $this->admin();
        $this->withCookie(AdminDeviceSecurityService::COOKIE, str_repeat('a', 64));
        $this->post(route('admin.login.submit'), ['email' => $admin->email, 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('admin_devices', 0);
        Queue::assertNothingPushed();
    }

    public function test_successful_totp_login_binds_cookie_and_revocation_ends_the_existing_session(): void
    {
        $admin = $this->admin(true);
        $secret = 'JBSWY3DPEHPK3PXP';
        $admin->forceFill(['totp_secret' => $secret, 'totp_confirmed_at' => now()])->save();
        $this->withCookie(AdminDeviceSecurityService::COOKIE, str_repeat('d', 64))->withHeader('User-Agent', self::MOBILE);
        $this->post(route('admin.login.submit'), ['email' => $admin->email, 'password' => 'StrongAdmin#2026!'])
            ->assertRedirect(route('admin.totp.challenge'));
        $this->assertDatabaseCount('admin_devices', 0);
        $this->post(route('admin.totp.challenge.submit'), ['code' => app(TotpService::class)->codeAt($secret, now()->timestamp)])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'admin');
        $device = AdminDevice::firstOrFail();
        $this->assertSame('MOBILE', $device->active_slot);
        $this->withCookie(config('session.cookie'), session()->getId())->get('/admin')
            ->assertRedirect(route('admin.dashboard'));
        $this->security()->manage($admin, $admin, $device, 'revoke', 'Security review', $this->request('d', self::MOBILE));
        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    public function test_invalid_totp_does_not_register_or_alert_for_a_device(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['totp_secret' => 'JBSWY3DPEHPK3PXP', 'totp_confirmed_at' => now()])->save();
        $this->withCookie(AdminDeviceSecurityService::COOKIE, str_repeat('a', 64));
        $this->post(route('admin.login.submit'), ['email' => $admin->email, 'password' => 'StrongAdmin#2026!']);
        $this->post(route('admin.totp.challenge.submit'), ['code' => 'invalid'])->assertSessionHasErrors('code');
        $this->assertDatabaseCount('admin_devices', 0);
        Queue::assertNothingPushed();
    }

    public function test_password_only_development_login_still_enforces_device_binding(): void
    {
        $admin = $this->admin();
        $this->withCookie(AdminDeviceSecurityService::COOKIE, str_repeat('a', 64))->withHeader('User-Agent', self::DESKTOP);
        $this->post(route('admin.login.submit'), ['email' => $admin->email, 'password' => 'StrongAdmin#2026!'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertDatabaseHas('admin_devices', ['admin_user_id' => $admin->id, 'active_slot' => 'DESKTOP']);
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_required_totp_enrollment_registers_device_only_after_confirmation(): void
    {
        config(['tradex_admin_hardening.totp.required' => true]);
        $admin = $this->admin(true);
        $this->withCookie(AdminDeviceSecurityService::COOKIE, str_repeat('e', 64))->withHeader('User-Agent', self::DESKTOP);
        $this->post(route('admin.login.submit'), ['email' => $admin->email, 'password' => 'StrongAdmin#2026!'])
            ->assertRedirect(route('admin.account.totp.settings'));
        $this->assertDatabaseCount('admin_devices', 0);
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->get(route('admin.administration.admins.index'))->assertRedirect(route('admin.account.totp.settings'));
        $this->post(route('admin.account.totp.setup'), ['current_password' => 'StrongAdmin#2026!'])->assertRedirect();
        $secret = $admin->fresh()->totp_secret;
        $this->post(route('admin.account.totp.confirm'), ['current_password' => 'StrongAdmin#2026!', 'code' => app(TotpService::class)->codeAt($secret, now()->timestamp)])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertDatabaseHas('admin_devices', ['admin_user_id' => $admin->id, 'active_slot' => 'DESKTOP']);
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_server_recovery_revokes_only_named_super_admin_device_and_records_audit(): void
    {
        $admin = $this->admin(true);
        $this->security()->bind($admin, $this->request('a'));
        $device = AdminDevice::firstOrFail();
        $this->artisan('tradex:admin-device-revoke', ['email' => $admin->email, 'device' => $device->id, '--reason' => 'Lost approved browser'])->assertSuccessful();
        $this->assertSame('REVOKED', $device->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ADMIN_DEVICE_SERVER_RECOVERY_REVOKE', 'actor_type' => 'SYSTEM']);
        $this->artisan('tradex:admin-device-revoke', ['email' => $this->admin()->email, 'device' => $device->id, '--reason' => 'Unauthorized recovery'])->assertFailed();
    }

    public function test_provider_failure_is_recorded_without_leaking_provider_details_and_cannot_unblock_device(): void
    {
        $admin = $this->admin(true);
        config([
            'tradex_admin_devices.whatsapp_recipients' => ['+94771234567'],
            'tradex_admin_devices.whatsapp_template' => 'tradex_security_alert',
            'tradex_registration.delivery.meta.graph_version' => 'v26.0',
            'tradex_registration.delivery.meta.phone_number_id' => '12345',
            'tradex_registration.delivery.meta.access_token' => 'test-secret',
        ]);
        $this->security()->bind($admin, $this->request('a'));
        $this->deny($admin, $this->request('b'));
        $alert = AdminDeviceAlert::where('channel', 'whatsapp')->firstOrFail();
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => 'sensitive provider details'], 400)]);
        try {
            (new SendAdminDeviceAlert($alert->id))->handle();
            $this->fail('Provider rejection must fail delivery.');
        } catch (\RuntimeException $exception) {
            $this->assertStringNotContainsString('sensitive', $exception->getMessage());
        }
        $this->assertSame('FAILED', $alert->fresh()->status);
        $this->assertDatabaseHas('admin_devices', ['status' => 'BLOCKED']);
        $this->assertSame(1, AdminDevice::where('status', 'ACTIVE')->count());
    }

    public function test_queued_email_is_not_sent_after_recipient_loses_super_admin_role(): void
    {
        $admin = $this->admin(true);
        $this->security()->bind($admin, $this->request('a'));
        $this->deny($admin, $this->request('b'));
        $alert = AdminDeviceAlert::where('channel', 'email')->firstOrFail();
        $admin->roles()->detach();
        Mail::shouldReceive('raw')->never();
        (new SendAdminDeviceAlert($alert->id))->handle();
        $this->assertSame('SKIPPED', $alert->fresh()->status);
    }

    public function test_session_without_bound_device_cannot_bypass_gate_and_login_sets_httponly_cookie(): void
    {
        $admin = $this->admin(true);
        $this->actingAs($admin, 'admin')->get(route('admin.administration.admins.index'))->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
        $response = $this->get(route('admin.login'))->assertCookie(AdminDeviceSecurityService::COOKIE);
        $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === AdminDeviceSecurityService::COOKIE);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertSame('/admin', $cookie->getPath());
    }

    public function test_only_super_admin_can_manage_and_device_owner_cannot_be_swapped(): void
    {
        config(['tradex_admin_devices.enabled' => false]);
        $super = $this->admin(true);
        $owner = $this->admin();
        $device = AdminDevice::create(['admin_user_id' => $owner->id, 'token_hash' => hash('sha256', 'test'), 'device_slot' => 'DESKTOP', 'active_slot' => 'DESKTOP', 'status' => 'ACTIVE']);
        $session = AdminDeviceSession::create([
            'admin_user_id' => $owner->id,
            'admin_device_id' => $device->id,
            'session_hash' => hash('sha256', 'live-session'),
            'session_version' => $device->session_version,
            'last_seen_at' => now(),
            'expires_at' => now()->addHour(),
        ]);
        $this->actingAs($owner, 'admin')->get(route('admin.administration.admins.devices.index', $owner))->assertForbidden();
        $this->patch(route('admin.administration.admins.devices.update', [$owner, $device]), ['action' => 'revoke', 'reason' => 'Not allowed'])->assertForbidden();
        $this->actingAs($super, 'admin')->patch(route('admin.administration.admins.devices.update', [$super, $device]), ['action' => 'revoke', 'reason' => 'Wrong owner'])->assertNotFound();
        $this->get(route('admin.administration.admins.devices.index', $owner))->assertOk()->assertSee('Revoke device')->assertSee('DESKTOP');
        $this->patch(route('admin.administration.admins.devices.update', [$owner, $device]), ['action' => 'revoke', 'reason' => 'Lost browser'])
            ->assertRedirect()
            ->assertSessionHas('success');
        $device->refresh();
        $this->assertSame('REVOKED', $device->status);
        $this->assertNull($device->active_slot);
        $this->assertNotNull($device->revoked_at);
        $this->assertSame(2, $device->session_version);
        $this->assertNotNull($session->fresh()->ended_at);
        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'ADMIN',
            'actor_id' => $super->id,
            'action' => 'ADMIN_DEVICE_REVOKE',
            'module' => 'SECURITY',
        ]);
    }

    public function test_super_admin_can_delete_any_owned_device_record_and_linked_sessions(): void
    {
        config(['tradex_admin_devices.enabled' => false]);
        $super = $this->admin(true);
        $owner = $this->admin();
        $device = AdminDevice::create([
            'admin_user_id' => $owner->id,
            'token_hash' => hash('sha256', 'delete-test'),
            'device_slot' => 'DESKTOP',
            'active_slot' => 'DESKTOP',
            'status' => 'ACTIVE',
        ]);
        AdminDeviceSession::create([
            'admin_user_id' => $owner->id,
            'admin_device_id' => $device->id,
            'session_hash' => hash('sha256', 'delete-session'),
            'session_version' => $device->session_version,
            'last_seen_at' => now(),
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($owner, 'admin')
            ->delete(route('admin.administration.admins.devices.destroy', [$owner, $device]))
            ->assertForbidden();

        $this->actingAs($super, 'admin')
            ->delete(route('admin.administration.admins.devices.destroy', [$super, $device]))
            ->assertNotFound();

        $this->delete(route('admin.administration.admins.devices.destroy', [$owner, $device]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('admin_devices', ['id' => $device->id]);
        $this->assertDatabaseMissing('admin_device_sessions', ['admin_device_id' => $device->id]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'ADMIN',
            'actor_id' => $super->id,
            'action' => 'ADMIN_DEVICE_DELETE',
            'module' => 'SECURITY',
            'target_id' => $device->id,
        ]);
    }

    public function test_notifications_send_to_super_admin_email_and_configured_whatsapp_with_device_details(): void
    {
        $admin = $this->admin(true);
        config([
            'tradex_admin_devices.whatsapp_recipients' => ['+94771234567'],
            'tradex_admin_devices.whatsapp_template' => 'tradex_security_alert',
            'tradex_registration.delivery.meta.graph_version' => 'v26.0',
            'tradex_registration.delivery.meta.phone_number_id' => '12345',
            'tradex_registration.delivery.meta.access_token' => 'test-secret',
        ]);
        $this->security()->bind($admin, $this->request('a'));
        $this->deny($admin, $this->request('b'));
        Mail::shouldReceive('raw')->once()->withArgs(fn ($body, $callback) => str_contains($body, $admin->email) && str_contains($body, '203.0.113.10') && ! str_contains($body, str_repeat('b', 64)));
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]])]);
        foreach (AdminDeviceAlert::all() as $alert) {
            (new SendAdminDeviceAlert($alert->id))->handle();
        }
        $this->assertSame(2, AdminDeviceAlert::where('status', 'ACCEPTED')->count());
        Http::assertSent(fn ($request) => $request['to'] === '94771234567' && $request['template']['name'] === 'tradex_security_alert' && str_contains($request['template']['components'][0]['parameters'][0]['text'], self::DESKTOP));
    }

    public function test_cookie_category_change_cannot_claim_a_second_slot(): void
    {
        $admin = $this->admin();
        $this->security()->bind($admin, $this->request('a'));
        $this->deny($admin, $this->request('a', self::MOBILE));
        $this->assertDatabaseCount('admin_devices', 1);
        $this->assertDatabaseHas('admin_device_incidents', ['reason' => 'DEVICE_CATEGORY_CHANGED']);
    }

    public function test_dashboard_counts_devices_separately_from_sessions_and_excludes_expired_sessions(): void
    {
        $admin = $this->admin(true);
        $first = $this->request('a');
        $second = $this->request('a');
        $tracker = app(AdminDeviceSessionService::class);
        foreach ([$first, $second] as $request) {
            $this->security()->bind($admin, $request);
            $tracker->touch($admin, $request);
        }
        $summary = $tracker->summary()->first();
        $this->assertSame(1, $summary->approved_devices_count);
        $this->assertSame(1, $summary->signed_in_devices_count);
        $this->assertSame(2, $summary->signed_in_sessions_count);
        $this->travel(31)->minutes();
        $summary = $tracker->summary()->first();
        $this->assertSame(1, $summary->approved_devices_count);
        $this->assertSame(0, $summary->signed_in_sessions_count);
    }

    public function test_logout_ends_session_but_preserves_registered_device_slot(): void
    {
        $admin = $this->admin(true);
        $this->withCookie(AdminDeviceSecurityService::COOKIE, str_repeat('a', 64))->withHeader('User-Agent', self::DESKTOP);
        $this->post(route('admin.login.submit'), ['email' => $admin->email, 'password' => 'StrongAdmin#2026!'])->assertRedirect();
        $this->assertSame(1, AdminDeviceSession::live()->count());
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Admin Devices &amp; Sessions', false)->assertSee('Manage / Delete Devices');
        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        $this->assertSame(0, AdminDeviceSession::live()->count());
        $this->assertSame(1, AdminDevice::where('status', 'ACTIVE')->count());
    }

    public function test_delegated_dashboard_cannot_see_other_admin_devices(): void
    {
        config(['tradex_admin_devices.enabled' => false]);
        $admin = $this->admin();
        $role = AdminRole::create(['code' => 'DASH_TEST', 'name' => 'Dashboard', 'is_active' => true]);
        $permission = AdminPermission::firstOrCreate(['code' => 'dashboard.view'], ['name' => 'Dashboard', 'module' => 'DASHBOARD']);
        $role->permissions()->attach($permission);
        $admin->roles()->attach($role);
        $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertOk()->assertDontSee('View Devices')->assertViewHas('adminDeviceSummary', null);
    }

    private function admin(bool $super = false): AdminUser
    {
        $admin = AdminUser::create(['uuid' => (string) Str::uuid(), 'name' => 'Device Test', 'email' => Str::uuid().'@example.test', 'password' => 'StrongAdmin#2026!', 'is_active' => true]);
        if ($super) {
            $role = AdminRole::firstOrCreate(['code' => 'SUPER_ADMIN'], ['name' => 'Super Admin', 'is_active' => true, 'is_system' => true]);
            $admin->roles()->attach($role);
        }

        return $admin;
    }

    private function request(string $token, string $agent = self::DESKTOP): Request
    {
        $request = Request::create('/admin/login', 'POST', [], [AdminDeviceSecurityService::COOKIE => str_repeat($token, 64)], [], ['REMOTE_ADDR' => '203.0.113.10', 'HTTP_USER_AGENT' => $agent]);
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);

        return $request;
    }

    private function security(): AdminDeviceSecurityService
    {
        return app(AdminDeviceSecurityService::class);
    }

    private function deny(AdminUser $admin, Request $request): void
    {
        try {
            $this->security()->bind($admin, $request);
            $this->fail('Extra device must be denied.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Contact Super Admin', $exception->errors()['device'][0]);
        }
    }
}
