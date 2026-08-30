<?php

namespace Tests\Feature\Admin\Reporting;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\PaymentOrder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class AdminReportDownloadTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('tradex_finance.timezone', 'Asia/Colombo');
        config()->set('tradex_reporting.audit_timezone', 'Asia/Colombo');
        config()->set('tradex_reporting.max_range_days', 366);
    }

    public function test_audit_csv_export_redacts_sensitive_payload_and_records_download_event(): void
    {
        $admin = $this->adminWithPermissions([
            'audit.view',
        ]);

        AuditLog::record(
            actorType: 'SYSTEM',
            actorId: null,
            action: 'AUDIT_REPORT_TEST_EVENT',
            module: 'TEST',
            targetType: 'TEST_RECORD',
            targetId: 99,
            newValues: [
                'password' => 'AUDIT-SECRET-MUST-NOT-EXPORT',
                'safe_note' => 'Visible audit note',
            ],
            ipAddress: '127.0.0.1',
            userAgent: '=HYPERLINK("https://example.test")'
        );

        $today = CarbonImmutable::now('Asia/Colombo')
            ->toDateString();

        $response = $this->actingAs($admin, 'admin')
            ->get(
                route(
                    'admin.audit.export',
                    [
                        'from' => $today,
                        'to' => $today,
                    ]
                )
            )
            ->assertOk();

        $content = $response->streamedContent();

        $this->assertStringContainsString(
            'AUDIT_REPORT_TEST_EVENT',
            $content
        );

        $this->assertStringContainsString(
            '[REDACTED]',
            $content
        );

        $this->assertStringNotContainsString(
            'AUDIT-SECRET-MUST-NOT-EXPORT',
            $content
        );

        $this->assertStringContainsString(
            "'=HYPERLINK",
            $content
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_type' => 'ADMIN',
                'actor_id' => $admin->id,
                'action' => 'AUDIT_REPORT_EXPORTED',
                'module' => 'AUDIT',
            ]
        );
    }

    public function test_sales_report_supports_custom_period_and_csv_export(): void
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

        $this->actingAs($admin, 'admin')
            ->get(
                route(
                    'admin.finance.sales-report',
                    [
                        'from' => '2026-08-25',
                        'to' => '2026-08-27',
                    ]
                )
            )
            ->assertOk()
            ->assertSee('3 day(s)')
            ->assertSee('USD 79.00');

        $response = $this->actingAs($admin, 'admin')
            ->get(
                route(
                    'admin.finance.sales-report.export',
                    [
                        'from' => '2026-08-25',
                        'to' => '2026-08-27',
                    ]
                )
            )
            ->assertOk();

        $content = $response->streamedContent();

        $this->assertStringContainsString(
            (string) $payment->uuid,
            $content
        );

        $this->assertStringContainsString(
            'PAYPAL',
            $content
        );

        $this->assertStringNotContainsString(
            (string) $payment->provider_capture_reference,
            $content
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_type' => 'ADMIN',
                'actor_id' => $admin->id,
                'action' => 'FINANCE_SALES_REPORT_EXPORTED',
                'module' => 'FINANCE',
            ]
        );
    }

    public function test_report_permissions_are_enforced(): void
    {
        $admin = $this->adminWithPermissions([]);
        $today = CarbonImmutable::now('Asia/Colombo')->toDateString();

        $this->actingAs($admin, 'admin')
            ->get(
                route(
                    'admin.audit.export',
                    ['from' => $today, 'to' => $today]
                )
            )
            ->assertForbidden();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.finance.sales-report'))
            ->assertForbidden();
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
            'cleared_at' => $this->dbTime($localDateTime),
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
                'report-'.Str::uuid()
            ),
            'provider_capture_reference' =>
                'REPORT-SENSITIVE-'.Str::uuid(),
            'status' => Payment::STATUS_CLEARED,
            'affiliate_processing_status' =>
                Payment::AFFILIATE_NOT_APPLICABLE,
            'cleared_at' => $this->dbTime($localDateTime),
        ]);
    }

    private function dbTime(string $localDateTime): CarbonImmutable
    {
        return CarbonImmutable::parse(
            $localDateTime,
            'Asia/Colombo'
        )->timezone(
            (string) config('app.timezone', 'UTC')
        );
    }

    private function adminWithPermissions(array $codes): AdminUser
    {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Admin Reporting Test',
            'email' => 'admin-reporting-'
                .Str::lower(Str::random(12))
                .'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
        ]);

        $role = AdminRole::query()->create([
            'code' => 'REPORTING_TEST_'.Str::upper(Str::random(8)),
            'name' => 'Reporting Test Role',
            'description' => null,
            'is_system' => false,
            'is_active' => true,
        ]);

        $permissionIds = [];

        foreach ($codes as $code) {
            $permission = AdminPermission::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $code,
                    'module' => 'REPORTING',
                    'description' => null,
                ]
            );

            $permissionIds[] = $permission->id;
        }

        $role->permissions()->sync($permissionIds);
        $admin->roles()->sync([$role->id]);

        return $admin->fresh('roles');
    }
}
