<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradingRoutineDelivery extends Model
{
    public const CHANNEL_WHATSAPP = 'WHATSAPP';
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_ACCEPTED = 'ACCEPTED';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_SKIPPED = 'SKIPPED';

    protected $fillable = [
        'trading_routine_daily_plan_id',
        'channel',
        'destination_snapshot',
        'status',
        'attempts',
        'provider',
        'provider_message_id',
        'provider_media_id',
        'failure_code',
        'failure_message',
        'last_attempt_at',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'last_attempt_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(
            TradingRoutineDailyPlan::class,
            'trading_routine_daily_plan_id'
        );
    }
}
