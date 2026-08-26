<?php

namespace Tests\Feature\Admin\Payouts;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\PayoutAllocation;
use App\Models\PayoutRequest;
use App\Services\Payouts\PayoutBalanceService;
use App\Services\Payouts\PayoutDestinationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesPayoutLedger;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class PayoutManagementTest extends TestCase
{
    use CreatesPayoutLedger;
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_guest_is_redirected_from_payout_admin(): void
    {
        $this->get(route('admin.payouts.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_payout_view_permission_is_required(): void
    {
        $admin = $this->adminWithPermissions([]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.payouts.index'))
            ->assertForbidden();
    }

    public function test_view_only_admin_cannot_reach_approve_validation(): void
    {
        $admin = $this->adminWithPermissions(['payouts.view']);
        [$payout] = $this->pendingPayout();

        $this->actingAs($admin, 'admin')
            ->patch(
                route('admin.payouts.approve', $payout),
                ['reason' => '']
            )
            ->assertForbidden();

        $this->assertSame(
            PayoutRequest::STATUS_PENDING_REVIEW,
            $payout->fresh()->status
        );
    }

    public function test_pending_payout_can_be_approved_without_mutating_contract(): void
    {
        $admin = $this->adminWithPermissions([
            'payouts.view',
            'payouts.approve',
        ]);

        [$payout] = $this->pendingPayout();

        $amount = $payout->amount_cents;
        $method = $payout->method;
        $fingerprint = $payout->destination_fingerprint;
        $allocationTotal = (int) $payout->allocations()->sum('amount_cents');

        $this->actingAs($admin, 'admin')
            ->patch(
                route('admin.payouts.approve', $payout),
                [
                    'reason' => 'Affiliate payout ledger and destination were verified.',
                ]
            )
            ->assertRedirect(route('admin.payouts.show', $payout));

        $payout->refresh();

        $this->assertSame(PayoutRequest::STATUS_APPROVED, $payout->status);
        $this->assertSame($admin->id, $payout->reviewed_by_admin_id);
        $this->assertNotNull($payout->approved_at);
        $this->assertSame($amount, $payout->amount_cents);
        $this->assertSame($method, $payout->method);
        $this->assertSame($fingerprint, $payout->destination_fingerprint);
        $this->assertSame(
            $allocationTotal,
            (int) $payout->allocations()->sum('amount_cents')
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'PAYOUT_APPROVED',
            'module' => 'PAYOUTS',
            'target_id' => $payout->id,
        ]);
    }

    public function test_payout_cannot_be_approved_twice(): void
    {
        $admin = $this->adminWithPermissions([
            'payouts.view',
            'payouts.approve',
        ]);

        [$payout] = $this->pendingPayout();

        $payload = [
            'reason' => 'Affiliate payout ledger and destination were verified.',
        ];

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.payouts.approve', $payout), $payload)
            ->assertRedirect(route('admin.payouts.show', $payout));

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.payouts.approve', $payout), $payload)
            ->assertRedirect(route('admin.payouts.show', $payout))
            ->assertSessionHasErrors('payout_review');

        $this->assertSame(
            PayoutRequest::STATUS_APPROVED,
            $payout->fresh()->status
        );
    }

    public function test_rejection_releases_locked_balance(): void
    {
        $admin = $this->adminWithPermissions([
            'payouts.view',
            'payouts.reject',
        ]);

        [$payout] = $this->pendingPayout(
            amountCents: 1500,
            commissionCents: 3000
        );

        $balances = app(PayoutBalanceService::class);

        $this->assertSame(
            1500,
            $balances->availableCents($payout->user)
        );

        $this->actingAs($admin, 'admin')
            ->patch(
                route('admin.payouts.reject', $payout),
                [
                    'reason' => 'The submitted payout destination requires correction.',
                ]
            )
            ->assertRedirect(route('admin.payouts.show', $payout));

        $payout->refresh();

        $this->assertSame(PayoutRequest::STATUS_REJECTED, $payout->status);
        $this->assertNotNull($payout->rejected_at);
        $this->assertSame(
            3000,
            $balances->availableCents($payout->user)
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'PAYOUT_REJECTED',
            'module' => 'PAYOUTS',
            'target_id' => $payout->id,
        ]);
    }

    public function test_approved_payout_can_be_marked_paid_with_encrypted_reference(): void
    {
        $admin = $this->adminWithPermissions([
            'payouts.view',
            'payouts.approve',
            'payouts.mark_paid',
        ]);

        [$payout] = $this->pendingPayout(
            amountCents: 1500,
            commissionCents: 3000
        );

        $this->actingAs($admin, 'admin')
            ->patch(
                route('admin.payouts.approve', $payout),
                [
                    'reason' => 'Affiliate payout ledger and destination were verified.',
                ]
            )
            ->assertRedirect(route('admin.payouts.show', $payout));

        $reference = 'PAYPAL-PAYOUT-REFERENCE-001';

        $this->actingAs($admin, 'admin')
            ->patch(
                route('admin.payouts.mark-paid', $payout),
                [
                    'provider_reference' => $reference,
                ]
            )
            ->assertRedirect(route('admin.payouts.show', $payout));

        $payout->refresh();

        $this->assertSame(PayoutRequest::STATUS_PAID, $payout->status);
        $this->assertNotNull($payout->paid_at);
        $this->assertSame($reference, $payout->provider_reference);

        $rawReference = (string) DB::table('payout_requests')
            ->where('id', $payout->id)
            ->value('provider_reference');

        $this->assertStringNotContainsString(
            $reference,
            $rawReference
        );

        $summary = app(PayoutBalanceService::class)
            ->summaryCents($payout->user);

        $this->assertSame(1500, $summary['paid_cents']);
        $this->assertSame(1500, $summary['available_cents']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'PAYOUT_MARKED_PAID',
            'module' => 'PAYOUTS',
            'target_id' => $payout->id,
        ]);
    }

    public function test_pending_payout_cannot_be_marked_paid_directly(): void
    {
        $admin = $this->adminWithPermissions([
            'payouts.view',
            'payouts.mark_paid',
        ]);

        [$payout] = $this->pendingPayout();

        $this->actingAs($admin, 'admin')
            ->patch(
                route('admin.payouts.mark-paid', $payout),
                [
                    'provider_reference' => 'BANK-SETTLEMENT-001',
                ]
            )
            ->assertRedirect(route('admin.payouts.show', $payout))
            ->assertSessionHasErrors('payout_review');

        $this->assertSame(
            PayoutRequest::STATUS_PENDING_REVIEW,
            $payout->fresh()->status
        );
    }

    public function test_ledger_reversal_after_request_blocks_approval(): void
    {
        $admin = $this->adminWithPermissions([
            'payouts.view',
            'payouts.approve',
        ]);

        [$payout, $commission] = $this->pendingPayout(
            amountCents: 1500,
            commissionCents: 2000
        );

        $this->seedPayoutAdjustment(
            $commission,
            $payout->user,
            -1000
        );

        $this->actingAs($admin, 'admin')
            ->patch(
                route('admin.payouts.approve', $payout),
                [
                    'reason' => 'Affiliate payout ledger and destination were verified.',
                ]
            )
            ->assertRedirect(route('admin.payouts.show', $payout))
            ->assertSessionHasErrors('payout_review');

        $this->assertSame(
            PayoutRequest::STATUS_PENDING_REVIEW,
            $payout->fresh()->status
        );
    }

    public function test_normal_detail_masks_destination_and_view_only_admin_cannot_reveal(): void
    {
        $admin = $this->adminWithPermissions(['payouts.view']);

        [$payout] = $this->pendingPayout();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.payouts.show', $payout))
            ->assertOk()
            ->assertSee('pa***@example.test')
            ->assertDontSee('payout@example.test');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.payouts.destination', $payout))
            ->assertForbidden();
    }

    public function test_authorized_operator_can_reveal_audited_no_store_destination(): void
    {
        $admin = $this->adminWithPermissions([
            'payouts.view',
            'payouts.approve',
        ]);

        [$payout] = $this->pendingPayout();

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.payouts.destination', $payout));

        $response
            ->assertOk()
            ->assertSee('payout@example.test');

        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control')
        );

        $this->assertSame(
            'nosniff',
            $response->headers->get('X-Content-Type-Options')
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'PAYOUT_DESTINATION_ACCESSED',
            'module' => 'PAYOUTS',
            'target_id' => $payout->id,
        ]);
    }

    public function test_destination_tampering_blocks_sensitive_reveal_and_approval(): void
    {
        $admin = $this->adminWithPermissions([
            'payouts.view',
            'payouts.approve',
        ]);

        [$payout] = $this->pendingPayout();

        $payout->forceFill([
            'destination_fingerprint' => str_repeat('0', 64),
        ])->save();

        $this->expectException(\DomainException::class);

        app(\App\Services\Admin\Payouts\AdminPayoutService::class)
            ->sensitiveDestination(
                $payout->fresh(),
                $admin,
                request()
            );
    }

    private function pendingPayout(
        int $amountCents = 1500,
        int $commissionCents = 3000,
        string $method = PayoutRequest::METHOD_PAYPAL
    ): array {
        $user = $this->createApiUser([
            'account_type' => 'PREMIUM',
        ]);

        $this->configurePayoutProfile($user);

        $commission = $this->seedPayoutCommission(
            $user,
            $commissionCents
        );

        $destinations = app(PayoutDestinationService::class);

        $snapshot = $destinations->snapshotFor(
            $user,
            $method
        );

        $payout = PayoutRequest::query()->create([
            'user_id' => $user->id,
            'method' => $method,
            'status' => PayoutRequest::STATUS_PENDING_REVIEW,
            'amount_cents' => $amountCents,
            'currency' => 'USD',
            'destination_snapshot' => $snapshot,
            'destination_fingerprint' => $destinations->fingerprint($snapshot),
            'idempotency_key_hash' => hash(
                'sha256',
                'admin-payout-'.Str::uuid()
            ),
            'requested_at' => now(),
        ]);

        PayoutAllocation::query()->create([
            'payout_request_id' => $payout->id,
            'affiliate_commission_id' => $commission->id,
            'amount_cents' => $amountCents,
        ]);

        return [
            $payout->fresh([
                'user',
                'allocations',
            ]),
            $commission,
        ];
    }

    private function adminWithPermissions(
        array $codes
    ): AdminUser {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'ADMIN Payout Test',
            'email' => 'admin-payout-'
                .Str::lower(Str::random(12))
                .'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
        ]);

        $role = AdminRole::query()->create([
            'code' => 'PAYOUT_TEST_'.Str::upper(Str::random(8)),
            'name' => 'Payout Test Role',
            'description' => 'ADMIN-08 test role.',
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
                        'module' => 'PAYOUTS',
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
