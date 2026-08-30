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

class StripeAdminLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_stripe_admin_ledger_is_read_only_provider_scoped_and_hides_provider_references(): void
    {
        $admin = $this->adminWithViewPermission();
        $user = User::factory()->create([
            'uuid' => Str::uuid()->toString(),
            'status' => 'ACTIVE',
            'account_type' => 'PREMIUM',
        ]);

        $stripeOrder = PaymentOrder::query()->create([
            'user_id' => $user->id,
            'product_code' => PaymentOrder::PRODUCT_EDUCATION_PREMIUM,
            'provider' => PaymentOrder::PROVIDER_STRIPE,
            'provider_reference' => 'cs_test_private_session_reference',
            'country_code' => 'LK',
            'currency' => 'USD',
            'amount_minor' => 7900,
            'status' => PaymentOrder::STATUS_CLEARED,
            'client_idempotency_key_hash' => hash('sha256', 'stripe-admin-ledger'),
            'cleared_at' => now(),
        ]);

        Payment::query()->create([
            'payment_order_id' => $stripeOrder->id,
            'user_id' => $user->id,
            'provider' => PaymentOrder::PROVIDER_STRIPE,
            'currency' => 'USD',
            'gross_amount_minor' => 7900,
            'processor_fee_minor' => 250,
            'net_eligible_revenue_minor' => 7650,
            'provider_capture_reference_hash' => hash('sha256', 'STRIPE-SECRET-REFERENCE'),
            'provider_capture_reference' => 'STRIPE-SECRET-REFERENCE',
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
            'client_idempotency_key_hash' => hash('sha256', 'paypal-not-stripe-ledger'),
            'cleared_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.payments.stripe.index'))
            ->assertOk()
            ->assertSeeText('Stripe Ledger')
            ->assertSee($stripeOrder->uuid)
            ->assertDontSee($paypalOrder->uuid)
            ->assertDontSee('cs_test_private_session_reference')
            ->assertDontSee('STRIPE-SECRET-REFERENCE')
            ->assertSeeText('Admins cannot manually approve or settle Stripe transactions.');

        $mutationRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with(
                $route->getName() ?? '',
                'admin.payments.stripe.'
            ))
            ->filter(fn ($route) => array_intersect(
                $route->methods(),
                ['POST', 'PUT', 'PATCH', 'DELETE']
            ))
            ->count();

        $this->assertSame(0, $mutationRoutes);
    }

    public function test_payments_dashboard_surfaces_stripe_button_count_and_orders(): void
    {
        $admin = $this->adminWithViewPermission();
        $user = User::factory()->create([
            'uuid' => Str::uuid()->toString(),
            'status' => 'ACTIVE',
        ]);

        $stripeOrder = $this->order(
            $user,
            PaymentOrder::PROVIDER_STRIPE,
            'stripe-dashboard'
        );

        $this->actingAs($admin, 'admin')
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->assertSeeText('Stripe')
            ->assertSeeText('Stripe Orders')
            ->assertSee(route('admin.payments.stripe.index'), false)
            ->assertSee($stripeOrder->uuid);
    }

    public function test_stripe_ledger_requires_payments_view_permission(): void
    {
        $admin = $this->adminWithPermissions([]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.payments.stripe.index'))
            ->assertForbidden();
    }

    private function order(User $user, string $provider, string $idempotency): PaymentOrder
    {
        return PaymentOrder::query()->create([
            'user_id' => $user->id,
            'product_code' => PaymentOrder::PRODUCT_EDUCATION_PREMIUM,
            'provider' => $provider,
            'country_code' => 'LK',
            'currency' => 'USD',
            'amount_minor' => 7900,
            'status' => PaymentOrder::STATUS_CREATED,
            'client_idempotency_key_hash' => hash('sha256', $idempotency),
        ]);
    }

    private function adminWithViewPermission(): AdminUser
    {
        return $this->adminWithPermissions(['payments.view']);
    }

    private function adminWithPermissions(array $codes): AdminUser
    {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Stripe Admin Test',
            'email' => 'stripe-admin-'.Str::lower(Str::random(10)).'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        $role = AdminRole::query()->create([
            'code' => 'STRIPE_'.strtoupper(Str::random(8)),
            'name' => 'Stripe Test Role',
            'is_system' => false,
            'is_active' => true,
        ]);

        $permissionIds = [];

        foreach ($codes as $code) {
            $permission = AdminPermission::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $code,
                    'module' => 'PAYMENTS',
                ]
            );
            $permissionIds[] = $permission->id;
        }

        $role->permissions()->sync($permissionIds);
        $admin->roles()->sync([$role->id]);

        return $admin->fresh();
    }
}
