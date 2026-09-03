<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentAgreement extends Model
{
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_GENERATED = 'GENERATED';
    public const STATUS_SENT = 'SENT';
    public const STATUS_SIGNED = 'SIGNED';
    public const STATUS_VERIFIED = 'VERIFIED';
    public const STATUS_DECLINED = 'DECLINED';
    public const STATUS_EXPIRED = 'EXPIRED';

    protected $fillable = [
        'uuid',
        'investment_id',
        'version',
        'status',
        'template_version',
        'template_status',
        'risk_disclosure_version',
        'terms_snapshot',
        'document_disk',
        'document_path',
        'document_mime_type',
        'document_size_bytes',
        'document_sha256',
        'signed_document_disk',
        'signed_document_path',
        'signed_document_mime_type',
        'signed_document_size_bytes',
        'signed_document_sha256',
        'generated_at',
        'sent_at',
        'signed_at',
        'verified_at',
        'generated_by_admin_id',
        'verified_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'terms_snapshot' => 'array',
            'document_size_bytes' => 'integer',
            'signed_document_size_bytes' => 'integer',
            'generated_at' => 'datetime',
            'sent_at' => 'datetime',
            'signed_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }

    public function generatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'generated_by_admin_id'
        );
    }

    public function verifiedByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'verified_by_admin_id'
        );
    }
}
