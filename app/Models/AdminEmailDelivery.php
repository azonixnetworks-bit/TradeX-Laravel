<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminEmailDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'admin_user_id',
        'event_type',
        'recipient_email',
        'recipient_name',
        'subject',
        'mailer',
        'dispatch_status',
        'delivery_status',
        'attempt_count',
        'sent_at',
        'failed_at',
        'last_error_code',
        'last_error_message',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'attempt_count' => 'integer',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'context' => 'array',
        ];
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class);
    }
}
