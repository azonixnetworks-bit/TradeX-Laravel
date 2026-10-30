<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RiskRewardSavedCalculation extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'calculator_type',
        'title',
        'input_payload',
        'result_payload',
    ];

    protected function casts(): array
    {
        return [
            'input_payload' => 'array',
            'result_payload' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->uuid ??= (string) Str::uuid();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
