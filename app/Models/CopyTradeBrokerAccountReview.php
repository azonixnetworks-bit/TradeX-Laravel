<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CopyTradeBrokerAccountReview extends Model
{
    public const ACTION_VERIFY = 'VERIFY';
    public const ACTION_REJECT = 'REJECT';
    public const ACTION_REQUEST_RESUBMISSION = 'REQUEST_RESUBMISSION';
    public const ACTION_ESCALATE = 'ESCALATE';

    public $timestamps = false;

    protected $fillable = [
        'broker_account_id',
        'admin_user_id',
        'action',
        'old_status',
        'new_status',
        'reason',
        'partner_attribution_confirmed',
        'live_account_confirmed',
        'verified_currency',
        'portal_reference',
        'evidence_disk',
        'evidence_path',
        'evidence_mime_type',
        'evidence_size_bytes',
        'created_at',
    ];

    protected $hidden = [
        'reason',
        'portal_reference',
        'evidence_disk',
        'evidence_path',
    ];

    protected function casts(): array
    {
        return [
            'reason' => 'encrypted',
            'portal_reference' => 'encrypted',
            'partner_attribution_confirmed' => 'boolean',
            'live_account_confirmed' => 'boolean',
            'evidence_size_bytes' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function brokerAccount(): BelongsTo
    {
        return $this->belongsTo(
            CopyTradeBrokerAccount::class,
            'broker_account_id'
        );
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'admin_user_id'
        );
    }

    public function hasEvidenceFile(): bool
    {
        return filled($this->evidence_disk)
            && filled($this->evidence_path);
    }
}
