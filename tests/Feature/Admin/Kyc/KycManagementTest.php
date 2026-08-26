<?php

namespace Tests\Feature\Admin\Kyc;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\KycDocument;
use App\Models\KycSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class KycManagementTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        config()->set('tradex_kyc.storage.disk', 'private');
        config()->set('tradex_kyc.storage.directory', 'kyc');
    }

    public function test_guest_is_redirected_from_kyc_queue(): void
    {
        $this->get(route('admin.kyc.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_kyc_view_permission_is_required(): void
    {
        $admin = $this->adminWithPermissions([]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.kyc.index'))
            ->assertForbidden();
    }

    public function test_action_permission_is_required_before_approval_validation(): void
    {
        $admin = $this->adminWithPermissions(['kyc.view']);
        $submission = $this->pendingSubmission();

        $this->actingAs($admin, 'admin')
            ->patch(
                route('admin.kyc.approve', $submission->getKey()),
                ['internal_notes' => str_repeat('x', 2100)]
            )
            ->assertForbidden();

        $this->assertSame(
            KycSubmission::STATUS_PENDING_REVIEW,
            $submission->fresh()->status
        );
    }

    public function test_normal_review_masks_identity_and_storage_metadata(): void
    {
        $admin = $this->adminWithPermissions(['kyc.view']);
        $submission = $this->pendingSubmission();
        $document = $submission->documents()->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.kyc.show', $submission->getKey()))
            ->assertOk()
            ->assertSee('••••5678')
            ->assertDontSee('199512345678')
            ->assertDontSee($submission->identity_number_hash)
            ->assertDontSee($document->storage_path)
            ->assertDontSee($document->sha256);
    }

    public function test_sensitive_identity_requires_document_permission(): void
    {
        $admin = $this->adminWithPermissions(['kyc.view']);
        $submission = $this->pendingSubmission();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.kyc.identity', $submission->getKey()))
            ->assertForbidden();
    }

    public function test_sensitive_identity_reveal_is_no_store_and_audited(): void
    {
        $admin = $this->adminWithPermissions([
            'kyc.view',
            'kyc.view_documents',
        ]);
        $submission = $this->pendingSubmission();

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.kyc.identity', $submission->getKey()))
            ->assertOk()
            ->assertSee('199512345678')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control')
        );

        $audit = AuditLog::query()
            ->where('action', 'KYC_IDENTITY_ACCESSED')
            ->where('target_id', $submission->getKey())
            ->firstOrFail();

        $auditPayload = json_encode(
            $audit->new_values,
            JSON_THROW_ON_ERROR
        );

        $this->assertStringNotContainsString('199512345678', $auditPayload);
        $this->assertStringContainsString('identity_number', $auditPayload);
    }

    public function test_document_access_requires_document_permission(): void
    {
        $admin = $this->adminWithPermissions(['kyc.view']);
        $submission = $this->pendingSubmission();
        $document = $submission->documents()->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.kyc.document', [
                'kycSubmission' => $submission->getKey(),
                'kycDocument' => $document->getKey(),
            ]))
            ->assertForbidden();
    }

    public function test_document_access_verifies_integrity_and_is_audited(): void
    {
        $admin = $this->adminWithPermissions([
            'kyc.view',
            'kyc.view_documents',
        ]);
        $submission = $this->pendingSubmission();
        $document = $submission->documents()->firstOrFail();

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.kyc.document', [
                'kycSubmission' => $submission->getKey(),
                'kycDocument' => $document->getKey(),
            ]))
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control')
        );

        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'ADMIN',
            'actor_id' => $admin->getKey(),
            'action' => 'KYC_DOCUMENT_ACCESSED',
            'module' => 'KYC',
            'target_type' => 'KYC_DOCUMENT',
            'target_id' => $document->getKey(),
        ]);
    }

    public function test_cross_submission_document_idor_is_blocked(): void
    {
        $admin = $this->adminWithPermissions([
            'kyc.view',
            'kyc.view_documents',
        ]);
        $first = $this->pendingSubmission();
        $second = $this->pendingSubmission();
        $foreignDocument = $second->documents()->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.kyc.document', [
                'kycSubmission' => $first->getKey(),
                'kycDocument' => $foreignDocument->getKey(),
            ]))
            ->assertNotFound();
    }

    public function test_pending_submission_can_be_verified_with_integrity_checks_and_audit(): void
    {
        $admin = $this->adminWithPermissions([
            'kyc.view',
            'kyc.approve',
        ]);
        $submission = $this->pendingSubmission();
        $activeHash = $submission->active_identity_hash;

        $this->actingAs($admin, 'admin')
            ->patch(
                route('admin.kyc.approve', $submission->getKey()),
                ['internal_notes' => 'Evidence matched the submitted identity.']
            )
            ->assertRedirect(
                route('admin.kyc.show', $submission->getKey())
            );

        $submission->refresh();

        $this->assertSame(KycSubmission::STATUS_VERIFIED, $submission->status);
        $this->assertSame($activeHash, $submission->active_identity_hash);
        $this->assertSame($admin->getKey(), $submission->reviewed_by_admin_id);
        $this->assertNotNull($submission->reviewed_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'KYC_VERIFIED',
            'module' => 'KYC',
            'target_id' => $submission->getKey(),
        ]);
    }

    public function test_approval_is_blocked_when_required_file_is_missing(): void
    {
        $admin = $this->adminWithPermissions([
            'kyc.view',
            'kyc.approve',
        ]);
        $submission = $this->pendingSubmission();
        $document = $submission->documents()
            ->where('document_kind', KycDocument::KIND_IDENTITY_FRONT)
            ->firstOrFail();

        Storage::disk('private')->delete($document->storage_path);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.kyc.show', $submission->getKey()))
            ->patch(route('admin.kyc.approve', $submission->getKey()))
            ->assertRedirect(route('admin.kyc.show', $submission->getKey()))
            ->assertSessionHasErrors('kyc_review');

        $this->assertSame(
            KycSubmission::STATUS_PENDING_REVIEW,
            $submission->fresh()->status
        );
    }

    public function test_approval_is_blocked_when_required_file_hash_is_tampered(): void
    {
        $admin = $this->adminWithPermissions([
            'kyc.view',
            'kyc.approve',
        ]);
        $submission = $this->pendingSubmission();
        $document = $submission->documents()
            ->where('document_kind', KycDocument::KIND_SELFIE)
            ->firstOrFail();

        Storage::disk('private')->put(
            $document->storage_path,
            'tampered-evidence'
        );

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.kyc.approve', $submission->getKey()))
            ->assertSessionHasErrors('kyc_review');

        $this->assertSame(
            KycSubmission::STATUS_PENDING_REVIEW,
            $submission->fresh()->status
        );
    }

    public function test_rejection_releases_active_identity_even_if_evidence_is_missing(): void
    {
        $admin = $this->adminWithPermissions([
            'kyc.view',
            'kyc.reject',
        ]);
        $submission = $this->pendingSubmission();
        $document = $submission->documents()->firstOrFail();
        Storage::disk('private')->delete($document->storage_path);

        $reason = 'Identity document is not acceptable for verification.';

        $this->actingAs($admin, 'admin')
            ->patch(
                route('admin.kyc.reject', $submission->getKey()),
                ['reason' => $reason]
            )
            ->assertRedirect();

        $submission->refresh();

        $this->assertSame(KycSubmission::STATUS_REJECTED, $submission->status);
        $this->assertNull($submission->active_identity_hash);
        $this->assertSame($reason, $submission->rejection_reason);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'KYC_REJECTED',
            'module' => 'KYC',
            'target_id' => $submission->getKey(),
        ]);
    }

    public function test_resubmission_request_releases_active_identity_and_is_audited(): void
    {
        $admin = $this->adminWithPermissions([
            'kyc.view',
            'kyc.request_resubmission',
        ]);
        $submission = $this->pendingSubmission();

        $this->actingAs($admin, 'admin')
            ->patch(
                route('admin.kyc.request-resubmission', $submission->getKey()),
                ['reason' => 'Please upload a clearer identity document and selfie.']
            )
            ->assertRedirect();

        $submission->refresh();

        $this->assertSame(
            KycSubmission::STATUS_NEEDS_RESUBMISSION,
            $submission->status
        );
        $this->assertNull($submission->active_identity_hash);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'KYC_RESUBMISSION_REQUESTED',
            'module' => 'KYC',
            'target_id' => $submission->getKey(),
        ]);
    }

    public function test_review_reason_is_required_for_reject_and_resubmission(): void
    {
        $admin = $this->adminWithPermissions([
            'kyc.view',
            'kyc.reject',
            'kyc.request_resubmission',
        ]);
        $submission = $this->pendingSubmission();

        $this->actingAs($admin, 'admin')
            ->patch(
                route('admin.kyc.reject', $submission->getKey()),
                ['reason' => '']
            )
            ->assertSessionHasErrors('reason');

        $this->actingAs($admin, 'admin')
            ->patch(
                route('admin.kyc.request-resubmission', $submission->getKey()),
                ['reason' => '']
            )
            ->assertSessionHasErrors('reason');

        $this->assertSame(
            KycSubmission::STATUS_PENDING_REVIEW,
            $submission->fresh()->status
        );
    }

    public function test_review_is_single_decision_only(): void
    {
        $admin = $this->adminWithPermissions([
            'kyc.view',
            'kyc.approve',
            'kyc.reject',
        ]);
        $submission = $this->pendingSubmission();

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.kyc.approve', $submission->getKey()))
            ->assertRedirect();

        $this->actingAs($admin, 'admin')
            ->patch(
                route('admin.kyc.reject', $submission->getKey()),
                ['reason' => 'Attempted second decision.']
            )
            ->assertSessionHasErrors('kyc_review');

        $this->assertSame(
            KycSubmission::STATUS_VERIFIED,
            $submission->fresh()->status
        );
    }

    public function test_stale_submission_cannot_be_reviewed_when_newer_exists(): void
    {
        $admin = $this->adminWithPermissions([
            'kyc.view',
            'kyc.reject',
        ]);
        $user = $this->createApiUser(['country_code' => 'LK']);
        $older = $this->pendingSubmission($user, '199512345678');
        $newer = $this->pendingSubmission($user, '199598765432');

        $this->actingAs($admin, 'admin')
            ->patch(
                route('admin.kyc.reject', $older->getKey()),
                ['reason' => 'This old submission should not be reviewed.']
            )
            ->assertSessionHasErrors('kyc_review');

        $this->assertSame(
            KycSubmission::STATUS_PENDING_REVIEW,
            $older->fresh()->status
        );
        $this->assertSame(
            KycSubmission::STATUS_PENDING_REVIEW,
            $newer->fresh()->status
        );
    }

    private function pendingSubmission(
        ?User $user = null,
        string $identityNumber = '199512345678'
    ): KycSubmission {
        $user ??= $this->createApiUser(['country_code' => 'LK']);

        $identityHash = hash(
            'sha256',
            $user->getKey().'|'.$identityNumber.'|'.Str::uuid()
        );

        $submission = KycSubmission::query()->create([
            'user_id' => $user->getKey(),
            'country_code' => 'LK',
            'document_type' => 'NATIONAL_ID',
            'identity_data' => [
                'legal_name' => 'TradeX KYC User',
                'date_of_birth' => '1995-05-15',
                'identity_number' => $identityNumber,
                'address' => '10 Test Street, Colombo',
            ],
            'identity_number_hash' => $identityHash,
            'status' => KycSubmission::STATUS_PENDING_REVIEW,
            'submitted_at' => now(),
        ]);

        $contentByKind = [
            KycDocument::KIND_IDENTITY_FRONT => 'front-'.$submission->uuid,
            KycDocument::KIND_IDENTITY_BACK => 'back-'.$submission->uuid,
            KycDocument::KIND_SELFIE => 'selfie-'.$submission->uuid,
        ];

        foreach ($contentByKind as $kind => $content) {
            $path = 'kyc/'
                .$user->uuid
                .'/'.$submission->uuid
                .'/'.strtolower($kind)
                .'-'.Str::uuid()
                .'.png';

            Storage::disk('private')->put($path, $content);

            KycDocument::query()->create([
                'kyc_submission_id' => $submission->getKey(),
                'document_kind' => $kind,
                'storage_disk' => 'private',
                'storage_path' => $path,
                'original_name' => strtolower($kind).'.png',
                'mime_type' => 'image/png',
                'size_bytes' => strlen($content),
                'sha256' => hash('sha256', $content),
            ]);
        }

        return $submission->fresh([
            'documents',
            'user',
        ]);
    }

    private function adminWithPermissions(array $codes): AdminUser
    {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'ADMIN KYC Test',
            'email' => 'admin-kyc-'.Str::lower(Str::random(12)).'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
        ]);

        $role = AdminRole::query()->create([
            'code' => 'KYC_TEST_'.Str::upper(Str::random(8)),
            'name' => 'KYC Test Role',
            'description' => 'ADMIN-10 test role.',
            'is_system' => false,
            'is_active' => true,
        ]);

        $permissionIds = [];

        foreach ($codes as $code) {
            $permission = AdminPermission::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $code,
                    'module' => 'KYC',
                    'description' => null,
                ]
            );
            $permissionIds[] = $permission->getKey();
        }

        $role->permissions()->sync($permissionIds);
        $admin->roles()->sync([$role->getKey()]);

        return $admin->fresh('roles');
    }
}
