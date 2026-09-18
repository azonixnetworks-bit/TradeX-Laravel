<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TradingFloorClientDocument extends Model
{
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_ARCHIVED = 'ARCHIVED';

    public const TYPE_NIC = 'NIC';
    public const TYPE_PASSPORT = 'PASSPORT';
    public const TYPE_AGREEMENT = 'AGREEMENT';
    public const TYPE_ACCESS = 'ACCESS';
    public const TYPE_OTHER = 'OTHER';

    protected $fillable = [
        'uuid',
        'trading_floor_client_id',
        'document_type',
        'title',
        'storage_path',
        'original_name',
        'mime_type',
        'file_size',
        'status',
        'archived_at',
        'archived_reason',
        'uploaded_by_admin_id',
        'archived_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $document): void {
            $document->uuid ??= (string) Str::uuid();
            $document->document_type = strtoupper(trim((string) $document->document_type));
            $document->status = strtoupper(trim((string) ($document->status ?: self::STATUS_ACTIVE)));
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(TradingFloorClient::class, 'trading_floor_client_id');
    }

    public function uploadedByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'uploaded_by_admin_id');
    }

    public function archivedByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'archived_by_admin_id');
    }
}
