<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CopyTradeActivationEvaluationGate extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'activation_evaluation_id',
        'gate_code',
        'passed',
        'reason_code',
        'evidence',
    ];

    protected function casts(): array
    {
        return [
            'passed' => 'boolean',
            'evidence' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException(
                'Copy Trade activation evaluation gates are immutable.'
            );
        });

        static::deleting(function (): void {
            throw new LogicException(
                'Copy Trade activation evaluation gates cannot be deleted through Eloquent.'
            );
        });
    }

    public function activationEvaluation(): BelongsTo
    {
        return $this->belongsTo(
            CopyTradeActivationEvaluation::class,
            'activation_evaluation_id'
        );
    }
}
