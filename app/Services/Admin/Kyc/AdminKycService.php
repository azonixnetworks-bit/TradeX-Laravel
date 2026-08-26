<?php

namespace App\Services\Admin\Kyc;

use App\Exceptions\KycApiException;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\KycDocument;
use App\Models\KycSubmission;
use App\Models\User;
use App\Services\Kyc\KycCountryPolicy;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminKycService
{
    public function __construct(
        private readonly KycCountryPolicy $policy
    ) {
    }

    public function dashboard(array $filters): array
    {
        $query = KycSubmission::query()
            ->with([
                'user:id,uuid,name,email,country_code,account_type,status',
                'reviewedByAdmin:id,name,email',
            ])
            ->withCount('documents');

        $this->applyFilters($query, $filters);

        return [
            'submissions' => $query
                ->orderByRaw(
                    "CASE WHEN status = 'PENDING_REVIEW' THEN 0 ELSE 1 END"
                )
                ->orderByDesc('submitted_at')
                ->orderByDesc('id')
                ->paginate($this->perPage($filters))
                ->withQueryString(),
            'stats' => [
                'total' => KycSubmission::query()->count(),
                'pending' => KycSubmission::query()
                    ->where('status', KycSubmission::STATUS_PENDING_REVIEW)
                    ->count(),
                'verified' => KycSubmission::query()
                    ->where('status', KycSubmission::STATUS_VERIFIED)
                    ->count(),
                'rejected' => KycSubmission::query()
                    ->where('status', KycSubmission::STATUS_REJECTED)
                    ->count(),
                'needs_resubmission' => KycSubmission::query()
                    ->where('status', KycSubmission::STATUS_NEEDS_RESUBMISSION)
                    ->count(),
            ],
            'statuses' => KycSubmission::VALID_STATUSES,
            'countries' => $this->countryOptions(),
            'document_types' => $this->documentTypeOptions(),
        ];
    }

    public function detail(KycSubmission $submission): array
    {
        $submission->load([
            'user:id,uuid,name,email,whatsapp_number,country_code,account_type,status',
            'documents:id,uuid,kyc_submission_id,document_kind,mime_type,size_bytes,created_at',
            'reviewedByAdmin:id,name,email',
            'supersedes:id,uuid,status,submitted_at,reviewed_at',
            'supersededBy:id,uuid,supersedes_submission_id,status,submitted_at,reviewed_at',
        ]);

        $identity = is_array($submission->identity_data)
            ? $submission->identity_data
            : [];

        return [
            'submission' => $submission,
            'identity_summary' => [
                'legal_name' => $this->safeText(
                    $identity['legal_name'] ?? null
                ),
                'date_of_birth' => $this->safeText(
                    $identity['date_of_birth'] ?? null
                ),
                'identity_number' => $this->maskIdentityNumber(
                    $identity['identity_number'] ?? null
                ),
                'address' => $this->safeText(
                    $identity['address'] ?? null
                ),
            ],
            'country_label' => $this->countryLabel(
                (string) $submission->country_code
            ),
            'document_type_label' => $this->documentTypeLabel(
                (string) $submission->country_code,
                (string) $submission->document_type
            ),
            'required_documents' => $this->requiredDocumentsSafe(
                (string) $submission->country_code,
                (string) $submission->document_type
            ),
        ];
    }

    public function sensitiveIdentity(
        KycSubmission $submission,
        AdminUser $admin,
        Request $request
    ): array {
        $submission->loadMissing([
            'user:id,uuid,name,email',
        ]);

        $identity = is_array($submission->identity_data)
            ? $submission->identity_data
            : [];

        if ($identity === []) {
            throw new DomainException(
                'Sensitive KYC identity data is unavailable.'
            );
        }

        $sanitized = [];

        foreach ($identity as $field => $value) {
            if (! is_string($field) || ! is_scalar($value)) {
                continue;
            }

            $sanitized[$field] = trim((string) $value);
        }

        if ($sanitized === []) {
            throw new DomainException(
                'Sensitive KYC identity data is unavailable.'
            );
        }

        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->getKey(),
            action: 'KYC_IDENTITY_ACCESSED',
            module: 'KYC',
            targetType: 'KYC_SUBMISSION',
            targetId: $submission->getKey(),
            newValues: [
                'submission_uuid' => $submission->uuid,
                'user_id' => $submission->user_id,
                'field_names' => array_keys($sanitized),
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return [
            'submission' => $submission,
            'identity_data' => $sanitized,
        ];
    }

    public function documentEvidence(
        KycSubmission $submission,
        KycDocument $document,
        AdminUser $admin,
        Request $request
    ): array {
        $submission->loadMissing([
            'user:id,uuid',
        ]);

        $evidence = $this->evidenceFile(
            $submission,
            $document,
            true
        );

        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->getKey(),
            action: 'KYC_DOCUMENT_ACCESSED',
            module: 'KYC',
            targetType: 'KYC_DOCUMENT',
            targetId: $document->getKey(),
            newValues: [
                'submission_uuid' => $submission->uuid,
                'document_uuid' => $document->uuid,
                'document_kind' => $document->document_kind,
                'mime_type' => $document->mime_type,
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return $evidence;
    }

    public function approve(
        KycSubmission $submission,
        AdminUser $admin,
        ?string $internalNotes,
        Request $request
    ): KycSubmission {
        /*
         * Verify private object bytes before the transaction so remote storage
         * reads do not hold user/submission database row locks.
         */
        $this->assertApprovalIntegrity($submission);

        return DB::transaction(function () use (
            $submission,
            $admin,
            $internalNotes,
            $request
        ): KycSubmission {
            [$user, $locked] = $this->lockLatestPending(
                $submission
            );

            $this->assertRequiredEvidenceRows($locked);

            $oldStatus = $locked->status;

            $locked->forceFill([
                'status' => KycSubmission::STATUS_VERIFIED,
                'rejection_reason' => null,
                'internal_notes' => $this->reviewNotes(
                    $locked,
                    $internalNotes
                ),
                'reviewed_at' => now(),
                'reviewed_by_admin_id' => $admin->getKey(),
            ])->save();

            $this->auditDecision(
                $admin,
                $locked,
                $user,
                'KYC_VERIFIED',
                $oldStatus,
                KycSubmission::STATUS_VERIFIED,
                $request
            );

            return $locked->fresh([
                'documents',
                'reviewedByAdmin',
            ]);
        }, 3);
    }

    public function reject(
        KycSubmission $submission,
        AdminUser $admin,
        string $reason,
        ?string $internalNotes,
        Request $request
    ): KycSubmission {
        return $this->reviewWithReason(
            $submission,
            $admin,
            KycSubmission::STATUS_REJECTED,
            'KYC_REJECTED',
            $reason,
            $internalNotes,
            $request
        );
    }

    public function requestResubmission(
        KycSubmission $submission,
        AdminUser $admin,
        string $reason,
        ?string $internalNotes,
        Request $request
    ): KycSubmission {
        return $this->reviewWithReason(
            $submission,
            $admin,
            KycSubmission::STATUS_NEEDS_RESUBMISSION,
            'KYC_RESUBMISSION_REQUESTED',
            $reason,
            $internalNotes,
            $request
        );
    }

    private function reviewWithReason(
        KycSubmission $submission,
        AdminUser $admin,
        string $newStatus,
        string $action,
        string $reason,
        ?string $internalNotes,
        Request $request
    ): KycSubmission {
        $reason = trim($reason);

        if ($reason === '') {
            throw new DomainException(
                'A user-visible KYC review reason is required.'
            );
        }

        return DB::transaction(function () use (
            $submission,
            $admin,
            $newStatus,
            $action,
            $reason,
            $internalNotes,
            $request
        ): KycSubmission {
            [$user, $locked] = $this->lockLatestPending(
                $submission
            );

            $oldStatus = $locked->status;

            $locked->forceFill([
                'status' => $newStatus,
                'rejection_reason' => $reason,
                'internal_notes' => $this->reviewNotes(
                    $locked,
                    $internalNotes
                ),
                'reviewed_at' => now(),
                'reviewed_by_admin_id' => $admin->getKey(),
            ])->save();

            /*
             * Frozen KycSubmission::saving releases active_identity_hash for
             * REJECTED / NEEDS_RESUBMISSION, preserving history while allowing
             * a corrected user resubmission.
             */
            $this->auditDecision(
                $admin,
                $locked,
                $user,
                $action,
                $oldStatus,
                $newStatus,
                $request
            );

            return $locked->fresh([
                'documents',
                'reviewedByAdmin',
            ]);
        }, 3);
    }

    private function lockLatestPending(
        KycSubmission $submission
    ): array {
        $user = User::query()
            ->whereKey($submission->user_id)
            ->lockForUpdate()
            ->first();

        if (! $user instanceof User) {
            throw new DomainException(
                'The KYC user account could not be found.'
            );
        }

        $latest = KycSubmission::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if (! $latest instanceof KycSubmission) {
            throw new DomainException(
                'The KYC submission could not be found.'
            );
        }

        if ((int) $latest->getKey() !== (int) $submission->getKey()) {
            throw new DomainException(
                'This is not the user\'s latest KYC submission. Review the newest submission instead.'
            );
        }

        if (! $latest->isPendingReview()) {
            throw new DomainException(
                'This KYC submission has already been reviewed or is no longer pending.'
            );
        }

        return [$user, $latest];
    }

    private function assertApprovalIntegrity(
        KycSubmission $submission
    ): void {
        $submission->load([
            'user:id,uuid',
            'documents',
        ]);

        $requiredFields = $this->requiredIdentityFieldsStrict(
            (string) $submission->country_code
        );

        $identity = is_array($submission->identity_data)
            ? $submission->identity_data
            : [];

        foreach ($requiredFields as $field) {
            $value = $identity[$field] ?? null;

            if (! is_string($value) || trim($value) === '') {
                throw new DomainException(
                    'KYC approval blocked because required identity data is incomplete.'
                );
            }
        }

        if (
            ! is_string($submission->identity_number_hash)
            || preg_match(
                '/^[a-f0-9]{64}$/i',
                $submission->identity_number_hash
            ) !== 1
        ) {
            throw new DomainException(
                'KYC approval blocked because the identity integrity record is invalid.'
            );
        }

        $requiredDocuments = $this->requiredDocumentsStrict(
            (string) $submission->country_code,
            (string) $submission->document_type
        );

        $documents = $submission->documents->keyBy(
            'document_kind'
        );

        foreach ($requiredDocuments as $kind) {
            $document = $documents->get($kind);

            if (! $document instanceof KycDocument) {
                throw new DomainException(
                    'KYC approval blocked because required evidence is missing.'
                );
            }

            $this->evidenceFile(
                $submission,
                $document,
                true
            );
        }
    }

    private function assertRequiredEvidenceRows(
        KycSubmission $submission
    ): void {
        $requiredDocuments = $this->requiredDocumentsStrict(
            (string) $submission->country_code,
            (string) $submission->document_type
        );

        $documents = KycDocument::query()
            ->where('kyc_submission_id', $submission->getKey())
            ->lockForUpdate()
            ->get([
                'id',
                'kyc_submission_id',
                'document_kind',
            ])
            ->keyBy('document_kind');

        foreach ($requiredDocuments as $kind) {
            if (! $documents->has($kind)) {
                throw new DomainException(
                    'KYC approval blocked because required evidence changed during review.'
                );
            }
        }
    }

    private function evidenceFile(
        KycSubmission $submission,
        KycDocument $document,
        bool $verifyHash
    ): array {
        if (
            (int) $document->kyc_submission_id
            !== (int) $submission->getKey()
        ) {
            throw new DomainException(
                'Protected KYC evidence ownership check failed.'
            );
        }

        $submission->loadMissing([
            'user:id,uuid',
        ]);

        if (! $submission->user instanceof User) {
            throw new DomainException(
                'Protected KYC evidence owner is unavailable.'
            );
        }

        $disk = trim((string) $document->storage_disk);
        $configuredDisks = array_keys(
            (array) config('filesystems.disks', [])
        );

        if (
            $disk === ''
            || strtolower($disk) === 'public'
            || ! in_array($disk, $configuredDisks, true)
        ) {
            throw new DomainException(
                'Protected KYC evidence storage is unavailable.'
            );
        }

        $path = str_replace(
            '\\',
            '/',
            trim((string) $document->storage_path)
        );

        if (
            $path === ''
            || str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:\//', $path) === 1
            || str_contains($path, '../')
            || str_contains($path, '/..')
        ) {
            throw new DomainException(
                'Protected KYC evidence path is invalid.'
            );
        }

        $directory = trim(
            (string) config('tradex_kyc.storage.directory', 'kyc'),
            "/\\ \t\n\r\0\x0B"
        );

        if ($directory === '') {
            $directory = 'kyc';
        }

        $userSegment = filled($submission->user->uuid)
            ? (string) $submission->user->uuid
            : (string) $submission->user->getKey();

        $expectedPrefix = $directory
            .'/'.$userSegment
            .'/'.$submission->uuid
            .'/';

        if (! str_starts_with($path, $expectedPrefix)) {
            throw new DomainException(
                'Protected KYC evidence ownership check failed.'
            );
        }

        $storage = Storage::disk($disk);

        if (! $storage->exists($path)) {
            throw new DomainException(
                'Protected KYC evidence file was not found.'
            );
        }

        if ($verifyHash) {
            $expectedHash = strtolower(
                trim((string) $document->sha256)
            );

            if (preg_match('/^[a-f0-9]{64}$/', $expectedHash) !== 1) {
                throw new DomainException(
                    'Protected KYC evidence integrity record is invalid.'
                );
            }

            $stream = $storage->readStream($path);

            if (! is_resource($stream)) {
                throw new DomainException(
                    'Protected KYC evidence could not be read.'
                );
            }

            try {
                $context = hash_init('sha256');
                hash_update_stream($context, $stream);
                $actualHash = hash_final($context);
            } finally {
                fclose($stream);
            }

            if (! hash_equals($expectedHash, strtolower($actualHash))) {
                throw new DomainException(
                    'Protected KYC evidence failed integrity verification.'
                );
            }
        }

        return [
            'disk' => $disk,
            'path' => $path,
            'name' => $this->evidenceName($document),
            'mime_type' => $this->safeMimeType(
                (string) $document->mime_type
            ),
        ];
    }

    private function evidenceName(KycDocument $document): string
    {
        $extension = match (strtolower(trim((string) $document->mime_type))) {
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            'image/webp' => '.webp',
            'application/pdf' => '.pdf',
            default => '',
        };

        return 'kyc-'
            .strtolower(
                preg_replace(
                    '/[^A-Z0-9_]/i',
                    '',
                    (string) $document->document_kind
                ) ?: 'document'
            )
            .'-'.$document->uuid
            .$extension;
    }

    private function safeMimeType(string $mimeType): string
    {
        $mimeType = strtolower(trim($mimeType));

        return in_array(
            $mimeType,
            [
                'image/jpeg',
                'image/png',
                'image/webp',
                'application/pdf',
            ],
            true
        )
            ? $mimeType
            : 'application/octet-stream';
    }

    private function auditDecision(
        AdminUser $admin,
        KycSubmission $submission,
        User $user,
        string $action,
        string $oldStatus,
        string $newStatus,
        Request $request
    ): void {
        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->getKey(),
            action: $action,
            module: 'KYC',
            targetType: 'KYC_SUBMISSION',
            targetId: $submission->getKey(),
            oldValues: [
                'status' => $oldStatus,
            ],
            newValues: [
                'submission_uuid' => $submission->uuid,
                'user_id' => $user->getKey(),
                'country_code' => $submission->country_code,
                'document_type' => $submission->document_type,
                'status' => $newStatus,
                'reviewed_by_admin_id' => $admin->getKey(),
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );
    }

    private function reviewNotes(
        KycSubmission $submission,
        ?string $internalNotes
    ): ?string {
        if (! is_string($internalNotes) || trim($internalNotes) === '') {
            return $submission->internal_notes;
        }

        return trim($internalNotes);
    }

    private function requiredIdentityFieldsStrict(
        string $countryCode
    ): array {
        try {
            return $this->policy->requiredIdentityFields($countryCode);
        } catch (KycApiException) {
            throw new DomainException(
                'KYC approval blocked because the country compliance profile is unavailable.'
            );
        }
    }

    private function requiredDocumentsStrict(
        string $countryCode,
        string $documentType
    ): array {
        try {
            $documents = $this->policy->requiredDocuments(
                $countryCode,
                $documentType
            );
        } catch (KycApiException) {
            throw new DomainException(
                'KYC approval blocked because the document compliance profile is unavailable.'
            );
        }

        if ($documents === []) {
            throw new DomainException(
                'KYC approval blocked because no required evidence policy is configured.'
            );
        }

        return $documents;
    }

    private function requiredDocumentsSafe(
        string $countryCode,
        string $documentType
    ): array {
        try {
            return $this->policy->requiredDocuments(
                $countryCode,
                $documentType
            );
        } catch (KycApiException) {
            return [];
        }
    }

    private function applyFilters(
        Builder $query,
        array $filters
    ): void {
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('uuid', 'like', '%'.$search.'%')
                    ->orWhereHas('user', function (Builder $user) use ($search): void {
                        $user
                            ->where('uuid', 'like', '%'.$search.'%')
                            ->orWhere('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%');
                    });
            });
        }

        $status = strtoupper(trim((string) ($filters['status'] ?? '')));
        if (in_array($status, KycSubmission::VALID_STATUSES, true)) {
            $query->where('status', $status);
        }

        $country = strtoupper(trim((string) ($filters['country'] ?? '')));
        if (preg_match('/^[A-Z]{2}$/', $country) === 1) {
            $query->where('country_code', $country);
        }

        $documentType = strtoupper(
            trim((string) ($filters['document_type'] ?? ''))
        );
        if ($documentType !== '' && mb_strlen($documentType) <= 40) {
            $query->where('document_type', $documentType);
        }

        $from = trim((string) ($filters['from'] ?? ''));
        if ($from !== '') {
            $query->whereDate('submitted_at', '>=', $from);
        }

        $to = trim((string) ($filters['to'] ?? ''));
        if ($to !== '') {
            $query->whereDate('submitted_at', '<=', $to);
        }
    }

    private function perPage(array $filters): int
    {
        $requested = (int) ($filters['per_page'] ?? 20);

        return in_array($requested, [10, 20, 50, 100], true)
            ? $requested
            : 20;
    }

    private function countryOptions(): array
    {
        $countries = [];

        foreach ((array) config('tradex_kyc.countries', []) as $code => $profile) {
            if (! is_string($code) || ! is_array($profile)) {
                continue;
            }

            $countries[strtoupper($code)] = (string) (
                $profile['name'] ?? strtoupper($code)
            );
        }

        ksort($countries);

        return $countries;
    }

    private function documentTypeOptions(): array
    {
        $types = [];

        foreach ((array) config('tradex_kyc.countries', []) as $country) {
            if (! is_array($country)) {
                continue;
            }

            foreach ((array) ($country['document_types'] ?? []) as $type => $profile) {
                if (! is_string($type) || ! is_array($profile)) {
                    continue;
                }

                $types[strtoupper($type)] = (string) (
                    $profile['label'] ?? strtoupper($type)
                );
            }
        }

        ksort($types);

        return $types;
    }

    private function countryLabel(string $countryCode): string
    {
        return (string) data_get(
            config('tradex_kyc.countries', []),
            strtoupper(trim($countryCode)).'.name',
            strtoupper(trim($countryCode))
        );
    }

    private function documentTypeLabel(
        string $countryCode,
        string $documentType
    ): string {
        return (string) data_get(
            config('tradex_kyc.countries', []),
            strtoupper(trim($countryCode))
                .'.document_types.'
                .strtoupper(trim($documentType))
                .'.label',
            strtoupper(trim($documentType))
        );
    }

    private function maskIdentityNumber(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '—';
        }

        $number = trim((string) $value);
        if ($number === '') {
            return '—';
        }

        return '••••'.mb_substr($number, -4);
    }

    private function safeText(mixed $value): string
    {
        return is_scalar($value) && trim((string) $value) !== ''
            ? trim((string) $value)
            : '—';
    }
}
