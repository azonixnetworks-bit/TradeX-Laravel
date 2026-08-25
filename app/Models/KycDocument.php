<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class KycDocument extends Model
{
    public const KIND_IDENTITY_FRONT = 'IDENTITY_FRONT';
    public const KIND_IDENTITY_BACK = 'IDENTITY_BACK';
    public const KIND_SELFIE = 'SELFIE';

    protected $fillable = [
        'uuid',
        'kyc_submission_id',
        'document_kind',
        'storage_disk',
        'storage_path',
        'original_name',
        'mime_type',
        'size_bytes',
        'sha256',
    ];

    protected $hidden = [
        'storage_disk',
        'storage_path',
        'original_name',
        'sha256',
    ];

    protected function casts(): array
    {
        return [
            'storage_path' => 'encrypted',
            'original_name' => 'encrypted',
            'size_bytes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $document): void {
            if (blank($document->uuid)) {
                $document->uuid = (string) Str::uuid();
            }
        });
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(
            KycSubmission::class,
            'kyc_submission_id'
        );
    }
}
