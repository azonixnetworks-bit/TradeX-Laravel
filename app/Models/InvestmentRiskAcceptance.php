<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentRiskAcceptance extends Model
{
    public const SOURCE_ADMIN_RECORDED = 'ADMIN_RECORDED';
    public const SOURCE_CUSTOMER_SELF_SERVICE = 'CUSTOMER_SELF_SERVICE';

    protected $fillable = [
        'uuid',
        'investment_id',
        'user_id',
        'disclosure_version',
        'evidence_source',
        'acceptance_reference',
        'acceptance_payload',
        'accepted_at',
        'accepted_ip',
        'accepted_user_agent',
        'recorded_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'acceptance_payload' => 'array',
            'accepted_at' => 'datetime',
        ];
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recordedByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'recorded_by_admin_id'
        );
    }
}
