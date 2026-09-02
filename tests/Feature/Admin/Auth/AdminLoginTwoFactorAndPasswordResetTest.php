<?php

namespace Tests\Feature\Admin\Auth;

use App\Models\AdminUser;
use App\Notifications\AdminResetPasswordNotification;
use App\Services\Auth\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminLoginTwoFactorAndPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_admin_totp_is_required_even_when_global_rollout_flag_is_false(): void
    {
        config()->set('tradex_admin_hardening.totp.required', false);

        $secret = 'JBSWY3DPEHPK3PXP';
        $admin = $this->adminWithConfirmedTotp($secret);

        $this->post(route('admin.login.submit'), [
            'email' => $admin->email,
            'password' => 'ExistingAdmin#Password2026!',
        ])
            ->assertRedirect(route('admin.totp.challenge'))
            ->assertSessionHas(
                'tradex_admin_pending_login_id',
                $admin->getKey()
            );

        $this->assertGuest('admin');
    }

    public function test_valid_totp_completes_admin_login_after_password(): void
    {
        config()->set('tradex_admin_hardening.totp.required', false);

        $secret = 'JBSWY3DPEHPK3PXP';
        $admin = $this->adminWithConfirmedTotp($secret);

        $this->post(route('admin.login.submit'), [
            'email' => $admin->email,
            'password' => 'ExistingAdmin#Password2026!',
        ])->assertRedirect(route('admin.totp.challenge'));

        $code = app(TotpService::class)->codeAt($secret, time());

        $this->post(route('admin.totp.challenge.submit'), [
            'code' => $code,
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertNotNull($admin->fresh()->last_login_at);
    }

    public function test_forgot_password_uses_admin_broker_and_reset_preserves_totp(): void
    {
        Notification::fake();

        $secret = 'JBSWY3DPEHPK3PXP';
        $admin = $this->adminWithConfirmedTotp($secret);

        $this->post(route('admin.password.email'), [
            'email' => $admin->email,
        ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $token = null;

        Notification::assertSentTo(
            $admin,
            AdminResetPasswordNotification::class,
            function (AdminResetPasswordNotification $notification) use (&$token): bool {
                $token = $notification->token;

                return is_string($token) && $token !== '';
            }
        );

        $this->assertNotNull($token);
        $this->assertDatabaseHas('admin_password_reset_tokens', [
            'email' => $admin->email,
        ]);

        $newPassword = 'NewTradeX#AdminPassword2026!';

        $this->post(route('admin.password.update'), [
            'token' => $token,
            'email' => $admin->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertRedirect(route('admin.login'));

        $fresh = $admin->fresh();

        $this->assertTrue(Hash::check($newPassword, $fresh->password));
        $this->assertTrue($fresh->hasConfirmedTotp());
        $this->assertSame($secret, $fresh->totp_secret);
        $this->assertDatabaseMissing('admin_password_reset_tokens', [
            'email' => $admin->email,
        ]);

        $this->post(route('admin.login.submit'), [
            'email' => $admin->email,
            'password' => $newPassword,
        ])->assertRedirect(route('admin.totp.challenge'));

        $this->assertGuest('admin');
    }

    public function test_forgot_password_does_not_enumerate_unknown_admin_email(): void
    {
        Notification::fake();

        $this->post(route('admin.password.email'), [
            'email' => 'unknown-admin@example.test',
        ])
            ->assertRedirect()
            ->assertSessionHas(
                'status',
                'If an active admin account matches that email, a password reset link has been sent.'
            );

        Notification::assertNothingSent();
        $this->assertDatabaseCount('admin_password_reset_tokens', 0);
    }

    private function adminWithConfirmedTotp(string $secret): AdminUser
    {
        return AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Admin Auth Hardening Test',
            'email' => 'admin-auth-'.Str::lower(Str::random(10)).'@example.test',
            'email_verified_at' => now(),
            'password' => 'ExistingAdmin#Password2026!',
            'is_active' => true,
            'failed_login_attempts' => 0,
            'totp_secret' => $secret,
            'totp_confirmed_at' => now(),
            'totp_last_used_step' => null,
        ]);
    }
}
