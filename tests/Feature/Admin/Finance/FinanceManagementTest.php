<?php

namespace Tests\Feature\Admin\Finance;

use App\Mail\DailySalesReportMail;
use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\AffiliateCommissionAdjustment;
use App\Models\Payment;
use App\Models\PaymentAdjustment;
use App\Models\PaymentOrder;
use App\Models\PayoutRequest;
use App\Services\Admin\Finance\AdminFinanceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesPayoutLedger;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class FinanceManagementTest extends TestCase
{
    use CreatesPayoutLedger;
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'tradex_finance.timezone',
            'Asia/Colombo'
        );
    }

    public function test_guest_is_redirected_from_finance_dashboard(): void
    {
        $this->get(route('admin.finance.dashboard'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_finance_view_permission_is_required(): void
    {
        $admin = $this->adminWithPermissions([]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.finance.dashboard'))
            ->assertForbidden();
    }

    public function test_finance_reports_permission_is_required_for_daily_sales(): void
    {
        $admin = $this->adminWithPermissions([
            'finance.view',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.finance.daily'))
            ->assertForbidden();
    }

    public function test_finance_reconciliation_permission_is_required(): void
    {
        $admin = $this->adminWithPermissions([
            'finance.view',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.finance.reconciliation'))
            ->assertForbidden();
    }

    public function test_daily_sales_keeps_currencies_separate_and_never_invents_manual_fee(): void
    {
        $admin = $this->adminWithPermissions([
            'finance.view',
            'finance.reports',
        ]);

        $date = '2026-08-26';

        $this->payment(
            PaymentOrder::PROVIDER_PAYPAL,
            'USD',
            7900,
            300,
            $date.' 10:00:00'
        );

        $this->payment(
            PaymentOrder::PROVIDER_LOCAL_BANK,
            'LKR',
            2500000,
            null,
            $date.' 11:00:00'
        );

        $response = $this->actingAs($admin, 'admin')
            ->get(
                route(
                    'admin.finance.daily',
                    ['date' => $date]
                )
            )
            ->assertOk();

        $response
            ->assertSee('USD 79.00')
            ->assertSee('LKR 25000.00')
            ->assertSee('Unknown / not recorded');

        $report = app(AdminFinanceService::class)
            ->dailyReport($date);

        $this->assertSame(
            7900,
            $report['summary']['currencies']['USD']['gross_minor']
        );

        $this->assertSame(
            300,
            $report['summary']['currencies']['USD']['fees_minor']
        );

        $this->assertSame(
            2500000,
            $report['summary']['currencies']['LKR']['gross_minor']
        );

        $this->assertSame(
            1,
            $report['summary']['currencies']['LKR']['unknown_fee_payments_count']
        );
    }

    public function test_reversal_is_recognized_on_adjustment_date_not_original_sale_date(): void
    {
        $payment = $this->payment(
            PaymentOrder::PROVIDER_PAYPAL,
            'USD',
            7900,
            300,
            '2026-08-25 10:00:00'
        );

        $adjustment = PaymentAdjustment::query()->create([
            'payment_id' => $payment->id,
            'type' => PaymentAdjustment::TYPE_PAYMENT_REVERSAL,
            'amount_minor' => -7900,
            'currency' => 'USD',
            'reason' => 'Mandatory processor reversal.',
            'provider_event_id' => 'evt-finance-reversal',
        ]);

        $this->moveTimestamp(
            $adjustment,
            '2026-08-26 12:00:00'
        );

        $service = app(AdminFinanceService::class);

        $saleDay = $service->dailyReport(
            '2026-08-25'
        );

        $reversalDay = $service->dailyReport(
            '2026-08-26'
        );

        $this->assertSame(
            7900,
            $saleDay['summary']['currencies']['USD']['gross_minor']
        );

        $this->assertSame(
            0,
            $saleDay['summary']['currencies']['USD']['adjustments_minor']
        );

        $this->assertSame(
            -7900,
            $reversalDay['summary']['currencies']['USD']['adjustments_minor']
        );
    }

    public function test_usd_contribution_subtracts_accrued_commission_but_not_paid_payout_again(): void
    {
        $this->payment(
            PaymentOrder::PROVIDER_PAYPAL,
            'USD',
            7900,
            300,
            '2026-08-26 10:00:00'
        );

        $affiliate = $this->createApiUser([
            'account_type' => 'PREMIUM',
        ]);

        $commission = $this->seedPayoutCommission(
            $affiliate,
            1500
        );

        $commission->forceFill([
            'earned_at' => $this->dbTime(
                '2026-08-26 13:00:00'
            ),
        ])->save();

        PayoutRequest::query()->create([
            'user_id' => $affiliate->id,
            'method' => PayoutRequest::METHOD_PAYPAL,
            'status' => PayoutRequest::STATUS_PAID,
            'amount_cents' => 1500,
            'currency' => 'USD',
            'destination_snapshot' => [
                'method' => 'PAYPAL',
                'email' => 'finance@example.test',
            ],
            'destination_fingerprint' => str_repeat('a', 64),
            'idempotency_key_hash' => hash(
                'sha256',
                (string) Str::uuid()
            ),
            'provider_reference' => 'settled-finance-test',
            'requested_at' => $this->dbTime('2026-08-26 14:00:00'),
            'approved_at' => $this->dbTime('2026-08-26 14:10:00'),
            'paid_at' => $this->dbTime('2026-08-26 14:30:00'),
        ]);

        $dashboard = app(AdminFinanceService::class)
            ->dashboard('2026-08');

        $this->assertSame(
            6100,
            $dashboard['known_usd_contribution_cents']
        );

        $this->assertSame(
            1500,
            $dashboard['monthly']['affiliate']['cash_payouts_paid_cents']
        );
    }

    public function test_commission_reversal_adjustment_reduces_accrued_expense_on_its_date(): void
    {
        $affiliate = $this->createApiUser([
            'account_type' => 'PREMIUM',
        ]);

        $commission = $this->seedPayoutCommission(
            $affiliate,
            1500
        );

        $commission->forceFill([
            'earned_at' => $this->dbTime(
                '2026-08-25 10:00:00'
            ),
        ])->save();

        $adjustment = $this->seedPayoutAdjustment(
            $commission,
            $affiliate,
            -500
        );

        $this->moveTimestamp(
            $adjustment,
            '2026-08-26 10:00:00'
        );

        $report = app(AdminFinanceService::class)
            ->dailyReport('2026-08-26');

        $this->assertSame(
            -500,
            $report['summary']['affiliate']['commission_adjustments_cents']
        );
    }

    public function test_daily_csv_does_not_expose_provider_capture_reference(): void
    {
        $admin = $this->adminWithPermissions([
            'finance.view',
            'finance.reports',
        ]);

        $payment = $this->payment(
            PaymentOrder::PROVIDER_PAYPAL,
            'USD',
            7900,
            300,
            '2026-08-26 10:00:00'
        );

        $payment->forceFill([
            'provider_capture_reference' => 'SENSITIVE-CAPTURE-REFERENCE',
        ])->save();

        $this->actingAs($admin, 'admin')
            ->get(
                route(
                    'admin.finance.daily.export',
                    ['date' => '2026-08-26']
                )
            )
            ->assertOk()
            ->assertDontSee(
                'SENSITIVE-CAPTURE-REFERENCE'
            );
    }

    public function test_reconciliation_detects_missing_payment_and_payout_allocation_mismatch(): void
    {
        $admin = $this->adminWithPermissions([
            'finance.view',
            'finance.reconciliation',
        ]);

        $user = $this->createApiUser();

        PaymentOrder::query()->create([
            'user_id' => $user->id,
            'product_code' => PaymentOrder::PRODUCT_EDUCATION_PREMIUM,
            'provider' => PaymentOrder::PROVIDER_PAYPAL,
            'country_code' => 'LK',
            'currency' => 'USD',
            'amount_minor' => 7900,
            'status' => PaymentOrder::STATUS_CLEARED,
            'client_idempotency_key_hash' => hash(
                'sha256',
                (string) Str::uuid()
            ),
            'cleared_at' => now(),
        ]);

        PayoutRequest::query()->create([
            'user_id' => $user->id,
            'method' => PayoutRequest::METHOD_PAYPAL,
            'status' => PayoutRequest::STATUS_APPROVED,
            'amount_cents' => 1500,
            'currency' => 'USD',
            'destination_snapshot' => [
                'method' => 'PAYPAL',
                'email' => 'payout@example.test',
            ],
            'destination_fingerprint' => str_repeat('b', 64),
            'idempotency_key_hash' => hash(
                'sha256',
                (string) Str::uuid()
            ),
            'requested_at' => now(),
            'approved_at' => now(),
            'reviewed_by_admin_id' => $admin->id,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.finance.reconciliation'))
            ->assertOk()
            ->assertSee('CLEARED_ORDER_MISSING_PAYMENT')
            ->assertSee('PAYOUT_ALLOCATION_MISMATCH');
    }

    public function test_daily_owner_report_command_sends_configured_email(): void
    {
        Mail::fake();

        config()->set(
            'tradex_finance.daily_report.owner_email',
            'owner@tradex.test'
        );

        $this->payment(
            PaymentOrder::PROVIDER_PAYPAL,
            'USD',
            7900,
            300,
            '2026-08-26 10:00:00'
        );

        $this->artisan(
            'tradex:finance:daily-sales-report',
            ['--date' => '2026-08-26']
        )->assertSuccessful();

        Mail::assertSent(
            DailySalesReportMail::class,
            fn (DailySalesReportMail $mail): bool =>
                $mail->reportDate === '2026-08-26'
        );
    }

    public function test_finance_dashboard_does_not_render_sensitive_payment_reference(): void
    {
        $admin = $this->adminWithPermissions([
            'finance.view',
        ]);

        $payment = $this->payment(
            PaymentOrder::PROVIDER_PAYPAL,
            'USD',
            7900,
            300,
            '2026-08-26 10:00:00'
        );

        $payment->forceFill([
            'provider_capture_reference' =>
                'FINANCE-SHOULD-NOT-RENDER',
        ])->save();

        $this->actingAs($admin, 'admin')
            ->get(
                route(
                    'admin.finance.dashboard',
                    ['month' => '2026-08']
                )
            )
            ->assertOk()
            ->assertDontSee(
                'FINANCE-SHOULD-NOT-RENDER'
            );
    }

    private function payment(
        string $provider,
        string $currency,
        int $amountMinor,
        ?int $feeMinor,
        string $localDateTime
    ): Payment {
        $user = $this->createApiUser();

        $order = PaymentOrder::query()->create([
            'user_id' => $user->id,
            'product_code' => PaymentOrder::PRODUCT_EDUCATION_PREMIUM,
            'provider' => $provider,
            'country_code' => 'LK',
            'currency' => $currency,
            'amount_minor' => $amountMinor,
            'status' => PaymentOrder::STATUS_CLEARED,
            'client_idempotency_key_hash' => hash(
                'sha256',
                (string) Str::uuid()
            ),
            'cleared_at' => $this->dbTime(
                $localDateTime
            ),
        ]);

        return Payment::query()->create([
            'payment_order_id' => $order->id,
            'user_id' => $user->id,
            'provider' => $provider,
            'currency' => $currency,
            'gross_amount_minor' => $amountMinor,
            'processor_fee_minor' => $feeMinor,
            'net_eligible_revenue_minor' =>
                $provider === PaymentOrder::PROVIDER_PAYPAL
                    ? max(0, $amountMinor - (int) ($feeMinor ?? 0))
                    : null,
            'provider_capture_reference_hash' => hash(
                'sha256',
                'finance-'.Str::uuid()
            ),
            'provider_capture_reference' =>
                'finance-'.Str::uuid(),
            'status' => Payment::STATUS_CLEARED,
            'affiliate_processing_status' =>
                Payment::AFFILIATE_NOT_APPLICABLE,
            'cleared_at' => $this->dbTime(
                $localDateTime
            ),
        ]);
    }

    private function moveTimestamp(
        $model,
        string $localDateTime
    ): void {
        $model->timestamps = false;

        $model->forceFill([
            'created_at' => $this->dbTime(
                $localDateTime
            ),
            'updated_at' => $this->dbTime(
                $localDateTime
            ),
        ])->save();

        $model->timestamps = true;
    }

    private function dbTime(
        string $localDateTime
    ): CarbonImmutable {
        return CarbonImmutable::parse(
            $localDateTime,
            'Asia/Colombo'
        )->timezone(
            (string) config(
                'app.timezone',
                'UTC'
            )
        );
    }

    private function adminWithPermissions(
        array $codes
    ): AdminUser {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'ADMIN Finance Test',
            'email' => 'admin-finance-'
                .Str::lower(Str::random(12))
                .'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
        ]);

        $role = AdminRole::query()->create([
            'code' => 'FINANCE_TEST_'
                .Str::upper(Str::random(8)),
            'name' => 'Finance Test Role',
            'description' => 'ADMIN-09 test role.',
            'is_system' => false,
            'is_active' => true,
        ]);

        $permissionIds = [];

        foreach ($codes as $code) {
            $permission = AdminPermission::query()
                ->updateOrCreate(
                    ['code' => $code],
                    [
                        'name' => $code,
                        'module' => 'FINANCE',
                        'description' => null,
                    ]
                );

            $permissionIds[] = $permission->id;
        }

        $role->permissions()->sync(
            $permissionIds
        );

        $admin->roles()->sync([
            $role->id,
        ]);

        return $admin->fresh('roles');
    }
}
