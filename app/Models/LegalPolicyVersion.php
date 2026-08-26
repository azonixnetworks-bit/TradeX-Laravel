<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class LegalPolicyVersion extends Model
{
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_PUBLISHED = 'PUBLISHED';

    protected $fillable = [
        'uuid',
        'policy_type',
        'version_number',
        'title',
        'summary',
        'content',
        'content_sha256',
        'status',
        'effective_at',
        'published_at',
        'revision_version',
        'created_by_admin_id',
        'updated_by_admin_id',
        'published_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'effective_at' => 'datetime',
            'published_at' => 'datetime',
            'revision_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(
            function (self $policy): void {
                if (blank($policy->uuid)) {
                    $policy->uuid =
                        Str::uuid()->toString();
                }
            }
        );

        static::updating(
            function (self $policy): void {
                if (
                    $policy->getOriginal('status')
                    === self::STATUS_PUBLISHED
                    || $policy->getOriginal(
                        'published_at'
                    ) !== null
                ) {
                    throw new LogicException(
                        'Published legal policy versions are immutable.'
                    );
                }
            }
        );

        static::deleting(
            function (): void {
                throw new LogicException(
                    'Legal policy versions cannot be deleted.'
                );
            }
        );
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'created_by_admin_id'
        );
    }

    public function updatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'updated_by_admin_id'
        );
    }

    public function publishedByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'published_by_admin_id'
        );
    }

    public function isDraft(): bool
    {
        return $this->status
            === self::STATUS_DRAFT;
    }

    public function isPublished(): bool
    {
        return $this->status
            === self::STATUS_PUBLISHED;
    }
}
