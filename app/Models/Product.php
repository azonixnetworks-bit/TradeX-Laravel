<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class Product extends Model
{
    public const CODE_EDUCATION_PREMIUM = 'EDUCATION_PREMIUM';

    public const BILLING_ONE_TIME_LIFETIME = 'ONE_TIME_LIFETIME';

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'description',
        'billing_type',
        'currency',
        'amount_minor',
        'is_active',
        'created_by_admin_id',
        'updated_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $product): void {
            if (blank($product->uuid)) {
                $product->uuid = (string) Str::uuid();
            }

            $product->code = strtoupper(
                trim((string) $product->code)
            );

            $product->billing_type = strtoupper(
                trim((string) $product->billing_type)
            );

            $product->currency = strtoupper(
                trim((string) $product->currency)
            );
        });

        static::updating(function (self $product): void {
            foreach ([
                'code',
                'billing_type',
                'currency',
            ] as $immutable) {
                if ($product->isDirty($immutable)) {
                    throw new LogicException(
                        'Product '.$immutable.' is immutable after creation.'
                    );
                }
            }
        });

        static::deleting(function (): void {
            throw new LogicException(
                'Product catalog rows are retained for billing and audit history.'
            );
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'created_by_admin_id'
        );
    }

    public function updatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'updated_by_admin_id'
        );
    }

    public function isEducationPremium(): bool
    {
        return $this->code
            === self::CODE_EDUCATION_PREMIUM;
    }
}
