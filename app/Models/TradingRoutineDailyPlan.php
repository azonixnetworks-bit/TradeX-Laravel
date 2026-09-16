<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class TradingRoutineDailyPlan extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'plan_date',
        'customer_name_snapshot',
        'customer_email_snapshot',
        'whatsapp_number_snapshot',
        'timezone_snapshot',
        'preference_snapshot_json',
        'plan_json',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'plan_date' => 'date:Y-m-d',
            'preference_snapshot_json' => 'array',
            'plan_json' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $plan): void {
            if (blank($plan->uuid)) {
                $plan->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(TradingRoutineDelivery::class);
    }
}
