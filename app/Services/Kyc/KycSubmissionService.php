<?php

namespace App\Services\Kyc;

use App\Exceptions\KycApiException;
use App\Models\AuditLog;
use App\Models\KycDocument;
use App\Models\KycSubmission;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class KycSubmissionService
{
    public function __construct(
        private readonly KycCountryPolicy
            $policy,
        private readonly KycIdentityHasher
            $identityHasher,
        private readonly KycDocumentStorageService
            $storage
    ) {
    }

    public function submit(
        User $user,
        string $documentType,
        array $identityData,
        array $files,
        Request $request
    ): KycSubmission {
        $countryCode = strtoupper(
            trim(
                (string) $user
                    ->country_code
            )
        );

        $documentType = strtoupper(
            trim(
                $documentType
            )
        );

        $requiredFields =
            $this->policy
                ->requiredIdentityFields(
                    $countryCode
                );

        $requiredDocuments =
            $this->policy
                ->requiredDocuments(
                    $countryCode,
                    $documentType
                );

        $identityData =
            $this->normalizeIdentityData(
                $identityData,
                $requiredFields
            );

        $identityHash =
            $this->identityHasher
                ->hash(
                    (string) $identityData[
                        'identity_number'
                    ],
                    $countryCode,
                    $documentType
                );

        $this->assertFilesPresent(
            $files,
            $requiredDocuments
        );

        /*
        | Cheap pre-flight checks avoid unnecessary private-storage writes.
        | Database row locks + the unique active_identity_hash constraint are
        | the authoritative concurrency protection inside the transaction.
        */
        $this->assertUserMaySubmit(
            $user,
            false
        );

        $this->assertIdentityAvailable(
            $user,
            $identityHash,
            false
        );

        $submissionUuid =
            (string) Str::uuid();

        $storedDocuments = [];

        try {
            foreach (
                $files
                as $kind => $file
            ) {
                if (
                    ! $file
                    instanceof UploadedFile
                ) {
                    throw new KycApiException(
                        422,
                        'KYC_DOCUMENT_INVALID',
                        'One of the KYC documents is invalid.'
                    );
                }

                $storedDocuments[] =
                    $this->storage
                        ->store(
                            $user,
                            $submissionUuid,
                            (string) $kind,
                            $file
                        );
            }

            $submission = DB::transaction(
                function () use (
                    $user,
                    $countryCode,
                    $documentType,
                    $identityData,
                    $identityHash,
                    $submissionUuid,
                    $storedDocuments,
                    $request
                ): KycSubmission {
                    $lockedUser =
                        User::query()
                            ->whereKey(
                                $user->getKey()
                            )
                            ->lockForUpdate()
                            ->first();

                    if (
                        ! $lockedUser
                        instanceof User
                    ) {
                        throw new KycApiException(
                            404,
                            'KYC_USER_NOT_FOUND',
                            'The user account could not be found.'
                        );
                    }

                    $previous =
                        $this->assertUserMaySubmit(
                            $lockedUser,
                            true
                        );

                    $this->assertIdentityAvailable(
                        $lockedUser,
                        $identityHash,
                        true
                    );

                    $submission =
                        KycSubmission::query()
                            ->create([
                                'uuid' =>
                                    $submissionUuid,

                                'user_id' =>
                                    $lockedUser
                                        ->getKey(),

                                'country_code' =>
                                    $countryCode,

                                'document_type' =>
                                    $documentType,

                                'identity_data' =>
                                    $identityData,

                                'identity_number_hash' =>
                                    $identityHash,

                                'status' =>
                                    KycSubmission::STATUS_PENDING_REVIEW,

                                'supersedes_submission_id' =>
                                    $previous
                                    instanceof KycSubmission
                                        ? $previous
                                            ->getKey()
                                        : null,

                                'submitted_at' =>
                                    now(),
                            ]);

                    foreach (
                        $storedDocuments
                        as $stored
                    ) {
                        KycDocument::query()
                            ->create([
                                'kyc_submission_id' =>
                                    $submission
                                        ->getKey(),

                                'document_kind' =>
                                    $stored[
                                        'document_kind'
                                    ],

                                'storage_disk' =>
                                    $stored[
                                        'storage_disk'
                                    ],

                                'storage_path' =>
                                    $stored[
                                        'storage_path'
                                    ],

                                'original_name' =>
                                    $stored[
                                        'original_name'
                                    ],

                                'mime_type' =>
                                    $stored[
                                        'mime_type'
                                    ],

                                'size_bytes' =>
                                    $stored[
                                        'size_bytes'
                                    ],

                                'sha256' =>
                                    $stored[
                                        'sha256'
                                    ],
                            ]);
                    }

                    AuditLog::record(
                        actorType: 'USER',
                        actorId:
                            $lockedUser
                                ->getKey(),
                        action:
                            'KYC_SUBMISSION_CREATED',
                        module: 'KYC',
                        targetType:
                            'KYC_SUBMISSION',
                        targetId:
                            $submission
                                ->getKey(),
                        newValues: [
                            'submission_uuid' =>
                                $submission->uuid,

                            'country_code' =>
                                $countryCode,

                            'document_type' =>
                                $documentType,

                            'status' =>
                                KycSubmission::STATUS_PENDING_REVIEW,

                            'document_kinds' =>
                                array_values(
                                    array_map(
                                        static fn (
                                            array $document
                                        ): string =>
                                            (string) $document[
                                                'document_kind'
                                            ],
                                        $storedDocuments
                                    )
                                ),

                            'supersedes_submission_uuid' =>
                                $previous
                                instanceof KycSubmission
                                    ? $previous->uuid
                                    : null,
                        ],
                        ipAddress:
                            $request->ip(),
                        userAgent:
                            $request
                                ->userAgent()
                    );

                    return $submission;
                },
                3
            );
        } catch (
            Throwable $exception
        ) {
            $this->storage
                ->cleanup(
                    $storedDocuments
                );

            if (
                $exception
                instanceof QueryException
                && $this
                    ->isActiveIdentityUniqueViolation(
                        $exception
                    )
            ) {
                throw new KycApiException(
                    409,
                    'KYC_IDENTITY_CONFLICT',
                    'This identity cannot be used for the current KYC submission.'
                );
            }

            throw $exception;
        }

        return $submission->fresh([
            'documents',
        ]);
    }

    private function assertUserMaySubmit(
        User $user,
        bool $lock
    ): ?KycSubmission {
        $query =
            KycSubmission::query()
                ->where(
                    'user_id',
                    $user->getKey()
                )
                ->latest(
                    'id'
                );

        if ($lock) {
            $query->lockForUpdate();
        }

        $latest = $query->first();

        if (
            ! $latest
            instanceof KycSubmission
        ) {
            return null;
        }

        if (
            $latest
                ->isPendingReview()
        ) {
            throw new KycApiException(
                409,
                'KYC_SUBMISSION_ALREADY_PENDING',
                'A KYC submission is already pending review.'
            );
        }

        if (
            $latest
                ->isVerified()
        ) {
            throw new KycApiException(
                409,
                'KYC_ALREADY_VERIFIED',
                'This account is already KYC verified.'
            );
        }

        if (
            ! $latest
                ->canBeResubmitted()
        ) {
            throw new KycApiException(
                409,
                'KYC_RESUBMISSION_NOT_ALLOWED',
                'A new KYC submission is not allowed from the current KYC state.'
            );
        }

        return $latest;
    }

    private function assertIdentityAvailable(
        User $user,
        string $identityHash,
        bool $lock
    ): void {
        $query =
            KycSubmission::query()
                ->where(
                    'active_identity_hash',
                    $identityHash
                )
                ->where(
                    'user_id',
                    '!=',
                    $user->getKey()
                );

        if ($lock) {
            $query->lockForUpdate();
        }

        if (
            $query->exists()
        ) {
            throw new KycApiException(
                409,
                'KYC_IDENTITY_CONFLICT',
                'This identity cannot be used for the current KYC submission.'
            );
        }
    }

    private function normalizeIdentityData(
        array $identityData,
        array $requiredFields
    ): array {
        $normalized = [];

        foreach (
            $requiredFields
            as $field
        ) {
            $value =
                $identityData[
                    $field
                ] ?? null;

            if (
                ! is_string(
                    $value
                )
                || trim(
                    $value
                ) === ''
            ) {
                throw new KycApiException(
                    422,
                    'KYC_IDENTITY_DATA_INCOMPLETE',
                    'Required KYC identity information is missing.'
                );
            }

            $normalized[
                $field
            ] = trim(
                $value
            );
        }

        /*
        | Preserve only configured identity fields. Arbitrary client-supplied
        | fields are never silently persisted inside the encrypted payload.
        */
        return $normalized;
    }

    private function assertFilesPresent(
        array $files,
        array $requiredDocuments
    ): void {
        foreach (
            $requiredDocuments
            as $kind
        ) {
            if (
                ! (
                    $files[
                        $kind
                    ]
                    ?? null
                )
                instanceof UploadedFile
            ) {
                throw new KycApiException(
                    422,
                    'KYC_REQUIRED_DOCUMENT_MISSING',
                    'A required KYC document is missing.'
                );
            }
        }
    }

    private function isActiveIdentityUniqueViolation(
        QueryException $exception
    ): bool {
        $message = strtolower(
            $exception->getMessage()
        );

        return str_contains(
            $message,
            'kyc_submissions_active_identity_unique'
        )
            || str_contains(
                $message,
                'kyc_submissions.active_identity_hash'
            )
            || (
                str_contains(
                    $message,
                    'active_identity_hash'
                )
                && (
                    str_contains(
                        $message,
                        'unique'
                    )
                    || str_contains(
                        $message,
                        'duplicate'
                    )
                )
            );
    }
}
