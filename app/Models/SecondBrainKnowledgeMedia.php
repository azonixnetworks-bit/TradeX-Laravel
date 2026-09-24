<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SecondBrainKnowledgeMedia extends Model
{
    public const TYPE_IMAGE = 'IMAGE';
    public const TYPE_PDF = 'PDF';
    public const TYPE_VIDEO = 'VIDEO';

    protected $table = 'second_brain_knowledge_media';

    protected $fillable = [
        'uuid',
        'knowledge_item_id',
        'media_type',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'extension',
        'size_bytes',
        'sha256',
        'metadata',
        'uploaded_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $media): void {
            if (blank($media->uuid)) {
                $media->uuid = (string) Str::uuid();
            }

            $media->media_type = strtoupper(trim((string) $media->media_type));
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function knowledgeItem(): BelongsTo
    {
        return $this->belongsTo(SecondBrainKnowledgeItem::class, 'knowledge_item_id');
    }

    public function uploadedByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'uploaded_by_admin_id');
    }
}
