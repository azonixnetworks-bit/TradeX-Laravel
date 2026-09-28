<?php

namespace Tests\Feature\Admin\Staff;

use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\StaffKycDocument;
use App\Models\StaffLeaveRequest;
use App\Models\StaffMember;
use App\Models\StaffPayoutChangeRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffManagementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_an_operational_staff_profile(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin, 'admin')->post(route('admin.staff.store'), [
            'full_name' => 'Floor Security Officer',
            'email' => 'security@tradex.test',
            'whatsapp_number' => '+94770000001',
            'job_role' => 'Security Officer',
            'department' => 'Operations',
            'joined_on' => '2026-09-28',
            'password' => 'StaffPassword123!',
            'password_confirmation' => 'StaffPassword123!',
        ])->assertRedirect(route('admin.staff.index'));

        $this->assertDatabaseHas('staff_members', [
            'email' => 'security@tradex.test',
            'employment_status' => 'PENDING',
            'job_role' => 'Security Officer',
        ]);
    }

    public function test_super_admin_kyc_review_updates_documents_and_staff_state(): void
    {
        $admin = $this->superAdmin();
        $staff = $this->staff();
        StaffKycDocument::query()->create([
            'staff_member_id' => $staff->id,
            'document_type' => 'ID_FRONT',
            'storage_disk' => 'private',
            'document_path' => 'staff/kyc/id-front.pdf',
            'status' => 'PENDING',
        ]);

        $this->actingAs($admin, 'admin')->patch(route('admin.staff.kyc.review', $staff), [
            'status' => 'APPROVED',
            'note' => 'Identity confirmed.',
        ])->assertRedirect();

        $this->assertNotNull($staff->fresh()->kyc_verified_at);
        $this->assertDatabaseHas('staff_kyc_documents', [
            'staff_member_id' => $staff->id,
            'status' => 'APPROVED',
            'reviewed_by_admin_id' => $admin->id,
        ]);
    }

    public function test_staff_payout_change_is_applied_only_after_super_admin_approval(): void
    {
        $admin = $this->superAdmin();
        $staff = $this->staff([
            'bank_account_holder' => 'Old Name',
            'bank_name' => 'Old Bank',
            'bank_account_number_encrypted' => '111111',
            'usdt_network' => 'TRC20',
            'usdt_address_encrypted' => 'TOldAddress',
            'payout_details_locked_at' => now(),
        ]);
        $change = StaffPayoutChangeRequest::query()->create([
            'staff_member_id' => $staff->id,
            'requested_details' => [
                'bank_account_holder' => 'New Name',
                'bank_name' => 'New Bank',
                'bank_account_number' => '222222',
                'usdt_network' => 'ERC20',
                'usdt_address' => '0xNewAddress',
            ],
            'reason' => 'Bank account changed',
        ]);

        $this->actingAs($admin, 'admin')->patch(route('admin.staff.payout-change.review', $change), [
            'status' => 'APPROVED',
            'review_note' => 'Verified with staff.',
        ])->assertRedirect();

        $staff->refresh();
        $this->assertSame('New Name', $staff->bank_account_holder);
        $this->assertSame('New Bank', $staff->bank_name);
        $this->assertSame('222222', $staff->bank_account_number_encrypted);
        $this->assertSame('0xNewAddress', $staff->usdt_address_encrypted);
        $this->assertSame('APPROVED', $change->fresh()->status);
    }

    public function test_super_admin_or_support_admin_can_review_leave_once(): void
    {
        $support = $this->adminWithRole('SUPPORT_ADMIN');
        $staff = $this->staff();
        $leave = StaffLeaveRequest::query()->create([
            'staff_member_id' => $staff->id,
            'starts_on' => now()->addWeek()->toDateString(),
            'ends_on' => now()->addWeeks(2)->toDateString(),
            'leave_type' => 'ANNUAL',
            'reason' => 'Family commitment',
        ]);

        $this->actingAs($support, 'admin')->patch(route('admin.staff.leave.review', $leave), [
            'status' => 'APPROVED',
            'review_note' => 'Approved by support.',
        ])->assertRedirect();

        $this->assertDatabaseHas('staff_leave_requests', [
            'id' => $leave->id,
            'status' => 'APPROVED',
            'reviewed_by_admin_id' => $support->id,
        ]);

        $this->actingAs($support, 'admin')->patch(route('admin.staff.leave.review', $leave), [
            'status' => 'REJECTED',
        ])->assertStatus(422);
    }

    public function test_super_admin_can_record_a_paid_salary_entry(): void
    {
        $admin = $this->superAdmin();
        $staff = $this->staff();

        $this->actingAs($admin, 'admin')->post(route('admin.staff.salary.store', $staff), [
            'period' => '2026-09',
            'gross_amount' => '1000.00',
            'deduction_amount' => '125.50',
            'currency' => 'USD',
            'paid_at' => '2026-09-28T10:00',
            'reference' => 'PAY-1001',
        ])->assertRedirect();

        $this->assertDatabaseHas('staff_salary_payments', [
            'staff_member_id' => $staff->id,
            'period' => '2026-09',
            'net_amount' => '874.50',
            'status' => 'PAID',
            'recorded_by_admin_id' => $admin->id,
        ]);
    }

    public function test_termination_requires_staff_acknowledgement_before_account_is_terminated(): void
    {
        $admin = $this->superAdmin();
        $staff = $this->staff(['employment_agreement_accepted_at' => now(), 'employment_agreement_version' => 'v1']);

        $this->actingAs($admin, 'admin')->post(route('admin.staff.termination.start', $staff), [
            'content' => 'Your employment with TradeX ends on the agreed date.',
        ])->assertRedirect();
        $this->assertSame('TERMINATION_PENDING', $staff->fresh()->employment_status);

        $this->actingAs($staff->fresh(), 'staff')->get(route('staff.termination.show'))->assertOk();
        $this->actingAs($staff->fresh(), 'staff')->post(route('staff.termination.accept'), ['accept' => true])
            ->assertRedirect(route('staff.login'));

        $this->assertSame('TERMINATED', $staff->fresh()->employment_status);
        $this->assertNotNull($staff->fresh()->termination_agreement_accepted_at);
    }
    public function test_staff_must_accept_employment_agreement_before_dashboard_access(): void
    {
        $staff = $this->staff(['employment_status' => 'PENDING']);

        $this->actingAs($staff, 'staff')->get(route('staff.dashboard'))
            ->assertRedirect(route('staff.agreement.show'));

        $this->actingAs($staff, 'staff')->get(route('staff.agreement.show'))->assertOk();
        $this->actingAs($staff, 'staff')->post(route('staff.agreement.accept'), ['accept' => true])
            ->assertRedirect(route('staff.dashboard'));

        $this->assertNotNull($staff->fresh()->employment_agreement_accepted_at);
    }

    private function superAdmin(): AdminUser
    {
        return $this->adminWithRole('SUPER_ADMIN');
    }

    private function adminWithRole(string $code): AdminUser
    {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => $code.' Admin',
            'email' => Str::lower($code).'-'.Str::random(8).'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'AdminPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
        ]);
        $role = AdminRole::query()->create([
            'code' => $code,
            'name' => $code,
            'is_system' => true,
            'is_active' => true,
        ]);
        $admin->roles()->attach($role->id);
        return $admin->fresh();
    }

    private function staff(array $overrides = []): StaffMember
    {
        return StaffMember::query()->create(array_merge([
            'uuid' => Str::uuid()->toString(),
            'staff_number' => 'STF-2026-'.Str::random(5),
            'full_name' => 'Test Staff',
            'email' => Str::lower(Str::random(10)).'@staff.test',
            'job_role' => 'Operations',
            'employment_status' => 'PENDING',
            'password' => 'StaffPassword123!',
        ], $overrides));
    }
}

