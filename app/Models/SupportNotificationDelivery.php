<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SupportNotificationDelivery extends Model
{
    use HasUuids;

    protected $fillable = ['ticket_id', 'user_id', 'admin_user_id', 'channel', 'template', 'deduplication_key', 'payload', 'status', 'attempts', 'last_error', 'sent_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'sent_at' => 'datetime'];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }
}
