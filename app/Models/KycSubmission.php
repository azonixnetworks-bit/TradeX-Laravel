<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

class KycSubmission extends Model
{
    public const STATUS_PENDING_REVIEW = 'PENDING_REVIEW';
    public const STATUS_VERIFIED = 'VERIFIED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_NEEDS_RESUBMISSION = 'NEEDS_RESUBMISSION';

    public const ACTIVE_IDENTITY_STATUSES = [
        self::STATUS_PENDING_REVIEW,
        self::STATUS_VERIFIED,
    ];

    public const VALID_STATUSES = [
        self::STATUS_PENDING_REVIEW,
        self::STATUS_VERIFIED,
        self::STATUS_REJECTED,
        self::STATUS_NEEDS_RESUBMISSION,
    ];

    protected $fillable = [
        'uuid',
        'user_id',
        'country_code',
        'document_type',
        'identity_data',
        'identity_number_hash',
        'status',
        'rejection_reason',
        'internal_notes',
        'supersedes_submission_id',
        'submitted_at',
        'reviewed_at',
        'reviewed_by_admin_id',
    ];

    protected $hidden = [
        'identity_data',
        'identity_number_hash',
        'active_identity_hash',
        'internal_notes',
        'reviewed_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'identity_data' =>
                'encrypted:array',

            'rejection_reason' =>
                'encrypted',

            'internal_notes' =>
                'encrypted',

            'submitted_at' =>
                'datetime',

            'reviewed_at' =>
                'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(
            function (
                self $submission
            ): void {
                if (
                    blank(
                        $submission->uuid
                    )
                ) {
                    $submission->uuid =
                        (string) Str::uuid();
                }
            }
        );

        /*
        | active_identity_hash is a server-derived concurrency guard.
        | It is never accepted through mass assignment.
        |
        | Pending/verified submissions hold the unique claim. Rejected or
        | resubmission-required rows release it while preserving historical
        | identity_number_hash evidence.
        */
        static::saving(
            function (
                self $submission
            ): void {
                if (
                    ! in_array(
                        $submission->status,
                        self::VALID_STATUSES,
                        true
                    )
                ) {
                    throw new LogicException(
                        'Invalid KYC submission status.'
                    );
                }

                if (
                    in_array(
                        $submission->status,
                        self::ACTIVE_IDENTITY_STATUSES,
                        true
                    )
                ) {
                    if (
                        blank(
                            $submission
                                ->identity_number_hash
                        )
                    ) {
                        throw new LogicException(
                            'An active KYC submission requires an identity hash.'
                        );
                    }

                    $submission
                        ->active_identity_hash =
                            $submission
                                ->identity_number_hash;

                    return;
                }

                $submission
                    ->active_identity_hash =
                        null;
            }
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }

    public function documents(): HasMany
    {
        return $this->hasMany(
            KycDocument::class
        );
    }

    public function reviewedByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'reviewed_by_admin_id'
        );
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'supersedes_submission_id'
        );
    }

    public function supersededBy(): HasMany
    {
        return $this->hasMany(
            self::class,
            'supersedes_submission_id'
        );
    }

    public function isPendingReview(): bool
    {
        return $this->status
            === self::STATUS_PENDING_REVIEW;
    }

    public function isVerified(): bool
    {
        return $this->status
            === self::STATUS_VERIFIED;
    }

    public function isRejected(): bool
    {
        return $this->status
            === self::STATUS_REJECTED;
    }

    public function needsResubmission(): bool
    {
        return $this->status
            === self::STATUS_NEEDS_RESUBMISSION;
    }

    public function canBeResubmitted(): bool
    {
        return $this->isRejected()
            || $this->needsResubmission();
    }

    public function isTerminalForUserEditing(): bool
    {
        return $this->isPendingReview()
            || $this->isVerified();
    }
}
