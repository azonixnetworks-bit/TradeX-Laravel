<?php

namespace Tests\Feature\Admin\Payments;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\Payment;
use App\Models\PaymentOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class NowPaymentsAdminLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_nowpayments_admin_ledger_is_read_only_and_provider_scoped(): void
    {
        $admin = $this->adminWithViewPermission();
        $user = User::factory()->create([
            'uuid' => Str::uuid()->toString(),
            'status' => 'ACTIVE',
            'account_type' => 'PREMIUM',
        ]);

        $nowOrder = PaymentOrder::query()->create([
            'user_id' => $user->id,
            'product_code' => PaymentOrder::PRODUCT_EDUCATION_PREMIUM,
            'provider' => PaymentOrder::PROVIDER_NOWPAYMENTS,
            'country_code' => 'LK',
            'currency' => 'USD',
            'amount_minor' => 7900,
            'status' => PaymentOrder::STATUS_CLEARED,
            'client_idempotency_key_hash' => hash('sha256', 'nowpayments-admin-ledger'),
            'cleared_at' => now(),
        ]);

        Payment::query()->create([
            'payment_order_id' => $nowOrder->id,
            'user_id' => $user->id,
            'provider' => PaymentOrder::PROVIDER_NOWPAYMENTS,
            'currency' => 'USD',
            'gross_amount_minor' => 7900,
            'processor_fee_minor' => null,
            'net_eligible_revenue_minor' => null,
            'provider_capture_reference_hash' => hash('sha256', 'NOWPAYMENTS-SECRET-REFERENCE'),
            'provider_capture_reference' => 'NOWPAYMENTS-SECRET-REFERENCE',
            'status' => Payment::STATUS_CLEARED,
            'affiliate_processing_status' => Payment::AFFILIATE_NOT_APPLICABLE,
            'cleared_at' => now(),
        ]);

        $paypalOrder = PaymentOrder::query()->create([
            'user_id' => $user->id,
            'product_code' => PaymentOrder::PRODUCT_EDUCATION_PREMIUM,
            'provider' => PaymentOrder::PROVIDER_PAYPAL,
            'country_code' => 'LK',
            'currency' => 'USD',
            'amount_minor' => 7900,
            'status' => PaymentOrder::STATUS_CLEARED,
            'client_idempotency_key_hash' => hash('sha256', 'paypal-not-nowpayments-ledger'),
            'cleared_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.payments.nowpayments.index'))
            ->assertOk()
            ->assertSeeText('NOWPayments Ledger')
            ->assertSee($nowOrder->uuid)
            ->assertDontSee($paypalOrder->uuid)
            ->assertDontSee('NOWPAYMENTS-SECRET-REFERENCE')
            ->assertSeeText('Admins cannot manually approve or settle NOWPayments transactions.');

        $mutationRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with(
                $route->getName() ?? '',
                'admin.payments.nowpayments.'
            ))
            ->filter(fn ($route) => array_intersect(
                $route->methods(),
                ['POST', 'PUT', 'PATCH', 'DELETE']
            ))
            ->count();

        $this->assertSame(0, $mutationRoutes);
    }

    private function adminWithViewPermission(): AdminUser
    {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'NOWPayments Admin Test',
            'email' => 'nowpayments-admin-'.Str::lower(Str::random(10)).'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        $role = AdminRole::query()->create([
            'code' => 'NOWPAY_'.strtoupper(Str::random(8)),
            'name' => 'NOWPayments Test Role',
            'is_system' => false,
            'is_active' => true,
        ]);

        $permission = AdminPermission::query()->firstOrCreate(
            ['code' => 'payments.view'],
            [
                'name' => 'payments.view',
                'module' => 'PAYMENTS',
            ]
        );

        $admin->roles()->attach($role->id);
        $role->permissions()->attach($permission->id);

        return $admin->fresh();
    }
}
