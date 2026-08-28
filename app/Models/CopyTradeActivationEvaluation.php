<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

class CopyTradeActivationEvaluation extends Model
{
    public const UPDATED_AT = null;

    public const STATUS_PASS = 'PASS';
    public const STATUS_FAIL = 'FAIL';

    protected $fillable = [
        'uuid',
        'broker_account_id',
        'overall_status',
        'trigger_source',
        'evaluated_by_admin_id',
        'evaluated_at',
        'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'evaluated_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $evaluation): void {
            if (blank($evaluation->uuid)) {
                $evaluation->uuid = (string) Str::uuid();
            }
        });

        static::updating(function (): void {
            throw new LogicException(
                'Copy Trade activation evaluations are immutable.'
            );
        });

        static::deleting(function (): void {
            throw new LogicException(
                'Copy Trade activation evaluations cannot be deleted through Eloquent.'
            );
        });
    }

    public function brokerAccount(): BelongsTo
    {
        return $this->belongsTo(
            CopyTradeBrokerAccount::class,
            'broker_account_id'
        );
    }

    public function evaluatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'evaluated_by_admin_id'
        );
    }

    public function gates(): HasMany
    {
        return $this->hasMany(
            CopyTradeActivationEvaluationGate::class,
            'activation_evaluation_id'
        );
    }
}
