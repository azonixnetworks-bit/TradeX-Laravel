<?php

namespace Tests\Feature\Admin\Payments;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\BankDeposit;
use App\Models\Payment;
use App\Models\PaymentOrder;
use App\Models\User;
use App\Models\UserEntitlement;
use App\Models\UsdtDeposit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_payment_admin(): void
    {
        $this->get(route('admin.payments.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_payment_view_permission_is_required(): void
    {
        $admin = $this->adminWithPermissions([]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.payments.index'))
            ->assertForbidden();
    }

    public function test_view_only_admin_cannot_reach_manual_approval_validation(): void
    {
        $admin = $this->adminWithPermissions(['payments.view']);
        [, $deposit] = $this->pendingBankDeposit();

        $this->actingAs($admin, 'admin')
            ->patch(
                route('admin.payments.bank.approve', $deposit),
                ['reason' => '']
            )
            ->assertForbidden();

        $this->assertDatabaseHas('bank_deposits', [
            'id' => $deposit->id,
            'status' => BankDeposit::STATUS_SUBMITTED,
        ]);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_bank_verifier_can_read_owned_private_proof_with_no_store_headers(): void
    {
        Storage::fake('local');

        $admin = $this->adminWithPermissions(['payments.view', 'payments.verify']);
        [$order, $deposit] = $this->pendingBankDeposit();

        $path = 'payments/bank/'.$order->user->uuid.'/proof.jpg';
        Storage::disk('local')->put($path, 'private-proof');
        $deposit->forceFill([
            'proof_disk' => 'local',
            'proof_path' => $path,
        ])->save();

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.payments.bank.proof', $deposit));

        $response->assertOk();
        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control')
        );
        $this->assertSame(
            'nosniff',
            $response->headers->get('X-Content-Type-Options')
        );
    }

    public function test_bank_approval_clears_order_and_activates_premium_atomically(): void
    {
        $admin = $this->adminWithPermissions(['payments.verify']);
        [$order, $deposit] = $this->pendingBankDeposit();

        $this->actingAs($admin, 'admin')
            ->patch(
                route('admin.payments.bank.approve', $deposit),
                ['reason' => 'Verified against the company bank statement.']
            )
            ->assertRedirect(route('admin.payments.show', $order));

        $order->refresh();
        $deposit->refresh();
        $user = $order->user->fresh();

        $this->assertSame(PaymentOrder::STATUS_CLEARED, $order->status);
        $this->assertSame(BankDeposit::STATUS_APPROVED, $deposit->status);
        $this->assertSame($admin->id, $deposit->reviewed_by_admin_id);
        $this->assertSame('PREMIUM', $user->account_type);
        $this->assertNotNull($user->premium_activated_at);

        $payment = Payment::query()
            ->where('payment_order_id', $order->id)
            ->firstOrFail();

        $this->assertSame(Payment::STATUS_CLEARED, $payment->status);
        $this->assertSame(PaymentOrder::PROVIDER_LOCAL_BANK, $payment->provider);
        $this->assertSame(Payment::AFFILIATE_NOT_APPLICABLE, $payment->affiliate_processing_status);
        $this->assertNull($payment->processor_fee_minor);
        $this->assertNull($payment->net_eligible_revenue_minor);

        $this->assertDatabaseHas('user_entitlements', [
            'user_id' => $user->id,
            'product_code' => PaymentOrder::PRODUCT_EDUCATION_PREMIUM,
            'status' => UserEntitlement::STATUS_ACTIVE,
            'payment_id' => $payment->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'PAYMENT_BANK_DEPOSIT_VERIFIED',
            'module' => 'PAYMENTS',
            'target_id' => $order->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'PAYMENT_CLEARED',
            'module' => 'PAYMENTS',
            'target_id' => $payment->id,
        ]);
    }

    public function test_manual_payment_cannot_be_approved_twice(): void
    {
        $admin = $this->adminWithPermissions(['payments.verify']);
        [$order, $deposit] = $this->pendingBankDeposit();

        $payload = ['reason' => 'Verified against the company bank statement.'];

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.payments.bank.approve', $deposit), $payload)
            ->assertRedirect(route('admin.payments.show', $order));

        $this->actingAs($admin, 'admin')
            ->from(route('admin.payments.show', $order))
            ->patch(route('admin.payments.bank.approve', $deposit), $payload)
            ->assertRedirect(route('admin.payments.show', $order))
            ->assertSessionHasErrors('payment_review');

        $this->assertSame(
            1,
            Payment::query()->where('payment_order_id', $order->id)->count()
        );
    }

    public function test_bank_rejection_never_creates_payment_or_entitlement(): void
    {
        $admin = $this->adminWithPermissions(['payments.reject']);
        [$order, $deposit] = $this->pendingBankDeposit();

        $this->actingAs($admin, 'admin')
            ->patch(
                route('admin.payments.bank.reject', $deposit),
                ['reason' => 'Bank evidence does not match the submitted order.']
            )
            ->assertRedirect(route('admin.payments.show', $order));

        $order->refresh();
        $deposit->refresh();

        $this->assertSame(PaymentOrder::STATUS_FAILED, $order->status);
        $this->assertSame('MANUAL_REVIEW_REJECTED', $order->failure_code);
        $this->assertSame(BankDeposit::STATUS_REJECTED, $deposit->status);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('user_entitlements', 0);
        $this->assertSame('FREE', $order->user->fresh()->account_type);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'PAYMENT_BANK_DEPOSIT_REJECTED',
            'module' => 'PAYMENTS',
            'target_id' => $order->id,
        ]);
    }

    public function test_usdt_approval_is_manual_settlement_without_invented_affiliate_sale(): void
    {
        $admin = $this->adminWithPermissions(['payments.verify']);
        $referrer = $this->user('REFERRER', 'LK', 'PREMIUM');
        [$order, $deposit] = $this->pendingUsdtDeposit($referrer);

        $this->actingAs($admin, 'admin')
            ->patch(
                route('admin.payments.usdt.approve', $deposit),
                ['reason' => 'Verified TRC20 transfer in the external chain explorer.']
            )
            ->assertRedirect(route('admin.payments.show', $order));

        $payment = Payment::query()
            ->where('payment_order_id', $order->id)
            ->firstOrFail();

        $this->assertSame(PaymentOrder::PROVIDER_USDT_TRC20, $payment->provider);
        $this->assertSame(Payment::AFFILIATE_NOT_APPLICABLE, $payment->affiliate_processing_status);
        $this->assertNull($payment->net_eligible_revenue_minor);
        $this->assertDatabaseCount('affiliate_qualified_sales', 0);
        $this->assertSame('PREMIUM', $order->user->fresh()->account_type);
    }

    public function test_usdt_contract_mismatch_is_not_cleared(): void
    {
        $admin = $this->adminWithPermissions(['payments.verify']);
        [$order, $deposit] = $this->pendingUsdtDeposit();

        $deposit->forceFill(['amount_micro' => $order->amount_minor - 1])->save();

        $this->actingAs($admin, 'admin')
            ->from(route('admin.payments.show', $order))
            ->patch(
                route('admin.payments.usdt.approve', $deposit),
                ['reason' => 'Verified TRC20 transfer in the external chain explorer.']
            )
            ->assertRedirect(route('admin.payments.show', $order))
            ->assertSessionHasErrors('payment_review');

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(PaymentOrder::STATUS_PENDING_REVIEW, $order->fresh()->status);
        $this->assertSame(UsdtDeposit::STATUS_SUBMITTED, $deposit->fresh()->status);
    }

    public function test_paypal_admin_surface_is_read_only(): void
    {
        $admin = $this->adminWithPermissions(['payments.view', 'payments.verify']);
        $user = $this->user('PAYPALUSER');

        $order = PaymentOrder::query()->create([
            'user_id' => $user->id,
            'product_code' => PaymentOrder::PRODUCT_EDUCATION_PREMIUM,
            'provider' => PaymentOrder::PROVIDER_PAYPAL,
            'country_code' => 'LK',
            'currency' => 'USD',
            'amount_minor' => 7900,
            'status' => PaymentOrder::STATUS_CLEARED,
            'client_idempotency_key_hash' => hash('sha256', 'paypal-admin-test'),
            'cleared_at' => now(),
        ]);

        Payment::query()->create([
            'payment_order_id' => $order->id,
            'user_id' => $user->id,
            'provider' => PaymentOrder::PROVIDER_PAYPAL,
            'currency' => 'USD',
            'gross_amount_minor' => 7900,
            'processor_fee_minor' => 300,
            'net_eligible_revenue_minor' => 7600,
            'provider_capture_reference_hash' => hash('sha256', 'CAPTURE-ADMIN-TEST'),
            'provider_capture_reference' => 'CAPTURE-ADMIN-TEST',
            'status' => Payment::STATUS_CLEARED,
            'affiliate_processing_status' => Payment::AFFILIATE_NOT_APPLICABLE,
            'cleared_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.payments.paypal.index'))
            ->assertOk()
            ->assertSee('cannot manually approve PayPal payments')
            ->assertDontSee('CAPTURE-ADMIN-TEST');

        $paypalMutationRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->getName() ?? '', 'admin.payments.paypal.'))
            ->filter(fn ($route) => array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']))
            ->count();

        $this->assertSame(0, $paypalMutationRoutes);
    }

    public function test_payment_account_page_never_renders_provider_secrets(): void
    {
        config()->set('tradex_payments.paypal.client_id', 'paypal-client-id-visible-no');
        config()->set('tradex_payments.paypal.client_secret', 'PAYPAL-SUPER-SECRET');
        config()->set('tradex_payments.paypal.webhook_id', 'PAYPAL-WEBHOOK-SECRET');
        config()->set('tradex_payments.local_bank.account_number', '1234567890123456');
        config()->set('tradex_payments.usdt_trc20.wallet_address', 'TABCDEFGHIJKLMNOPQRSTUVWXYZ123456');

        $admin = $this->adminWithPermissions(['payment_accounts.manage']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.payments.accounts.index'))
            ->assertOk()
            ->assertSee('Client ID: Configured')
            ->assertSee('Client Secret: Configured')
            ->assertDontSee('PAYPAL-SUPER-SECRET')
            ->assertDontSee('PAYPAL-WEBHOOK-SECRET')
            ->assertDontSee('1234567890123456')
            ->assertDontSee('TABCDEFGHIJKLMNOPQRSTUVWXYZ123456');
    }

    private function pendingBankDeposit(): array
    {
        $user = $this->user('BANK'.strtoupper(Str::random(6)));

        $order = PaymentOrder::query()->create([
            'user_id' => $user->id,
            'product_code' => PaymentOrder::PRODUCT_EDUCATION_PREMIUM,
            'provider' => PaymentOrder::PROVIDER_LOCAL_BANK,
            'country_code' => 'LK',
            'currency' => 'LKR',
            'amount_minor' => 2500000,
            'status' => PaymentOrder::STATUS_PENDING_REVIEW,
            'client_idempotency_key_hash' => hash('sha256', Str::uuid()->toString()),
        ]);

        $deposit = BankDeposit::query()->create([
            'payment_order_id' => $order->id,
            'user_id' => $user->id,
            'depositor_name' => 'Payment Test Depositor',
            'bank_reference' => 'BANK-REF-'.Str::upper(Str::random(10)),
            'deposit_date' => now()->toDateString(),
            'proof_disk' => 'local',
            'proof_path' => 'payments/bank/'.$user->uuid.'/missing-proof.jpg',
            'status' => BankDeposit::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        return [$order->fresh('user'), $deposit];
    }

    private function pendingUsdtDeposit(?User $referrer = null): array
    {
        $user = $this->user(
            'USDT'.strtoupper(Str::random(6)),
            'IN',
            'FREE',
            $referrer
        );

        $order = PaymentOrder::query()->create([
            'user_id' => $user->id,
            'product_code' => PaymentOrder::PRODUCT_EDUCATION_PREMIUM,
            'provider' => PaymentOrder::PROVIDER_USDT_TRC20,
            'country_code' => 'IN',
            'currency' => 'USDT',
            'amount_minor' => 79000000,
            'status' => PaymentOrder::STATUS_PENDING_REVIEW,
            'client_idempotency_key_hash' => hash('sha256', Str::uuid()->toString()),
        ]);

        $deposit = UsdtDeposit::query()->create([
            'payment_order_id' => $order->id,
            'user_id' => $user->id,
            'network' => UsdtDeposit::NETWORK_TRC20,
            'tx_hash' => strtolower(hash('sha256', Str::uuid()->toString())),
            'sender_address' => 'T'.Str::upper(Str::random(33)),
            'amount_micro' => 79000000,
            'status' => UsdtDeposit::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        return [$order->fresh('user'), $deposit];
    }

    private function adminWithPermissions(array $codes): AdminUser
    {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'ADMIN Payments Test',
            'email' => 'admin-pay-'.Str::lower(Str::random(12)).'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        $role = AdminRole::query()->create([
            'code' => 'PAYTEST_'.strtoupper(Str::random(8)),
            'name' => 'Payments Test Role',
            'is_system' => false,
            'is_active' => true,
        ]);

        $admin->roles()->attach($role->id);

        foreach ($codes as $code) {
            $permission = AdminPermission::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $code,
                    'module' => 'PAYMENTS',
                ]
            );

            $role->permissions()->attach($permission->id);
        }

        return $admin->fresh();
    }

    private function user(
        string $referralCode,
        string $country = 'LK',
        string $accountType = 'FREE',
        ?User $referrer = null
    ): User {
        return User::factory()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'User '.$referralCode,
            'email' => Str::lower($referralCode).'@tradex.test',
            'whatsapp_number' => '+947'.random_int(10000000, 99999999),
            'whatsapp_verified_at' => now(),
            'referral_code' => $referralCode,
            'referred_by_user_id' => $referrer?->id,
            'country_code' => $country,
            'language_code' => 'en',
            'account_type' => $accountType,
            'status' => 'ACTIVE',
            'premium_activated_at' => $accountType === 'PREMIUM' ? now() : null,
        ]);
    }
}
