<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentRiskAcceptance extends Model
{
    protected $fillable = [
        'uuid',
        'investment_id',
        'user_id',
        'disclosure_version',
        'acceptance_payload',
        'accepted_at',
        'accepted_ip',
        'accepted_user_agent',
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
}
