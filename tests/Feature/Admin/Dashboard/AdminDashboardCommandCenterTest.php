<?php

namespace Tests\Feature\Admin\Dashboard;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\PaymentOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDashboardCommandCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_access_cards_link_to_their_real_admin_modules_and_system_clock_is_live_ready(): void
    {
        $admin = $this->adminWithPermissions([
            'dashboard.view',
            'users.view',
            'education.view',
            'trade_ideas.view',
            'payments.view',
            'payouts.view',
            'our_channel.view',
            'audit.view',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.customers.dashboard'), false)
            ->assertSee(route('admin.education.courses.index'), false)
            ->assertSee(route('admin.trade_ideas.index'), false)
            ->assertSee(route('admin.daily_trade_ideas.index'), false)
            ->assertSee(route('admin.payments.index'), false)
            ->assertSee(route('admin.payouts.index'), false)
            ->assertSee(route('admin.our_channel.index'), false)
            ->assertSee('data-system-clock', false)
            ->assertSee('dashboard-command-center.js', false)
            ->assertSeeText('System Time');
    }

    public function test_recent_customers_support_per_view_pagination_and_detail_links(): void
    {
        $admin = $this->adminWithPermissions([
            'dashboard.view',
            'users.view',
        ]);

        foreach (range(1, 7) as $index) {
            User::factory()->create([
                'name' => sprintf('Recent Customer %02d', $index),
                'email' => sprintf('recent-customer-%02d@tradex.test', $index),
                'whatsapp_number' => '+9477000'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'status' => 'ACTIVE',
                'account_type' => 'FREE',
                'country_code' => 'LK',
            ]);
        }

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard', [
                'activity' => 'customers',
                'per_page' => 5,
            ]))
            ->assertOk()
            ->assertSeeText('Recent Customers')
            ->assertSeeText('Per View');

        $records = $response->viewData('activityRecords');

        $this->assertSame('customers', $response->viewData('activityType'));
        $this->assertSame(5, $records->perPage());
        $this->assertSame(7, $records->total());
        $this->assertCount(5, $records->items());

        $latest = User::query()->latest('id')->firstOrFail();
        $response
            ->assertSeeText($latest->name)
            ->assertSee(route('admin.users.show', $latest), false);
    }

    public function test_recent_payments_show_safe_payment_details_without_provider_capture_references(): void
    {
        $admin = $this->adminWithPermissions([
            'dashboard.view',
            'payments.view',
        ]);

        $user = User::factory()->create([
            'name' => 'Recent Payment Customer',
            'email' => 'recent-payment@tradex.test',
            'status' => 'ACTIVE',
        ]);

        $order = PaymentOrder::query()->create([
            'user_id' => $user->id,
            'product_code' => PaymentOrder::PRODUCT_EDUCATION_PREMIUM,
            'provider' => PaymentOrder::PROVIDER_STRIPE,
            'country_code' => 'LK',
            'currency' => 'USD',
            'amount_minor' => 7900,
            'status' => PaymentOrder::STATUS_CLEARED,
            'client_idempotency_key_hash' => hash('sha256', 'dashboard-recent-payment'),
            'cleared_at' => now(),
        ]);

        Payment::query()->create([
            'payment_order_id' => $order->id,
            'user_id' => $user->id,
            'provider' => PaymentOrder::PROVIDER_STRIPE,
            'currency' => 'USD',
            'gross_amount_minor' => 7900,
            'processor_fee_minor' => 250,
            'net_eligible_revenue_minor' => 7650,
            'provider_capture_reference_hash' => hash('sha256', 'DASHBOARD-PRIVATE-CAPTURE'),
            'provider_capture_reference' => 'DASHBOARD-PRIVATE-CAPTURE',
            'status' => Payment::STATUS_CLEARED,
            'affiliate_processing_status' => Payment::AFFILIATE_NOT_APPLICABLE,
            'cleared_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard', [
                'activity' => 'payments',
                'per_page' => 10,
            ]))
            ->assertOk()
            ->assertSeeText('Recent Payment Customer')
            ->assertSeeText('STRIPE')
            ->assertSee($order->uuid)
            ->assertSee(route('admin.payments.show', $order), false)
            ->assertDontSee('DASHBOARD-PRIVATE-CAPTURE');
    }

    public function test_audit_tab_shows_current_metadata_but_not_audit_payload_values(): void
    {
        $admin = $this->adminWithPermissions([
            'dashboard.view',
            'audit.view',
        ]);

        $audit = AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->id,
            action: 'DASHBOARD_AUDIT_TEST',
            module: 'DASHBOARD',
            targetType: 'TEST',
            targetId: 123,
            newValues: [
                'token' => 'DASHBOARD-AUDIT-PRIVATE-VALUE',
            ],
            ipAddress: '127.0.0.1',
            userAgent: 'Dashboard Test'
        );

        $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard', [
                'activity' => 'audit',
                'per_page' => 5,
            ]))
            ->assertOk()
            ->assertSeeText('DASHBOARD_AUDIT_TEST')
            ->assertSeeText('DASHBOARD')
            ->assertSeeText('127.0.0.1')
            ->assertSee(route('admin.audit.show', $audit), false)
            ->assertDontSee('DASHBOARD-AUDIT-PRIVATE-VALUE');
    }

    public function test_recent_activity_data_is_not_queried_or_rendered_without_module_permissions(): void
    {
        $admin = $this->adminWithPermissions([
            'dashboard.view',
        ]);

        User::factory()->create([
            'name' => 'Permission Hidden Customer',
            'email' => 'permission-hidden@tradex.test',
        ]);

        AuditLog::record(
            actorType: 'SYSTEM',
            actorId: null,
            action: 'PERMISSION_HIDDEN_AUDIT',
            module: 'SECURITY'
        );

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard', [
                'activity' => 'audit',
            ]))
            ->assertOk()
            ->assertSeeText('No recent-activity modules are available for this administrator role.')
            ->assertDontSee('Permission Hidden Customer')
            ->assertDontSee('PERMISSION_HIDDEN_AUDIT');

        $this->assertSame([], $response->viewData('activityTabs'));
        $this->assertNull($response->viewData('activityType'));
        $this->assertNull($response->viewData('activityRecords'));
    }

    private function adminWithPermissions(array $codes): AdminUser
    {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Dashboard Command Admin',
            'email' => 'dashboard-command-'.Str::lower(Str::random(10)).'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        $role = AdminRole::query()->create([
            'code' => 'DASH_'.strtoupper(Str::random(8)),
            'name' => 'Dashboard Command Role',
            'is_system' => false,
            'is_active' => true,
        ]);

        $permissionIds = [];

        foreach ($codes as $code) {
            $permission = AdminPermission::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $code,
                    'module' => 'DASHBOARD',
                ]
            );

            $permissionIds[] = $permission->id;
        }

        $role->permissions()->sync($permissionIds);
        $admin->roles()->sync([$role->id]);

        return $admin->fresh();
    }
}
