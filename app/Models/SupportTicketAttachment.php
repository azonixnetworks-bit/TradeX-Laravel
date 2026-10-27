<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketAttachment extends Model
{
    use HasUuids;

    protected $fillable = ['ticket_id', 'message_id', 'uploader_type', 'uploader_user_id', 'uploader_admin_id', 'disk', 'path', 'original_name', 'mime_type', 'byte_size', 'content_hash', 'client_idempotency_key', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }
}
