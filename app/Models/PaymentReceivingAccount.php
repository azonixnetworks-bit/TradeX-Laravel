<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PaymentReceivingAccount extends Model
{
    public const TYPE_BANK = 'BANK';
    public const TYPE_CRYPTO = 'CRYPTO';

    protected $fillable = [
        'uuid',
        'type',
        'display_name',
        'country_code',
        'currency',
        'tender_amount_minor',
        'amount_scale',
        'bank_name',
        'bank_branch',
        'account_holder',
        'account_number',
        'coin_name',
        'coin_symbol',
        'network',
        'wallet_address',
        'qr_disk',
        'qr_path',
        'instructions',
        'is_active',
        'display_order',
        'created_by_admin_id',
        'updated_by_admin_id',
    ];

    protected $hidden = [
        'account_number',
        'wallet_address',
        'qr_disk',
        'qr_path',
        'created_by_admin_id',
        'updated_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'tender_amount_minor' =>
                'integer',
            'amount_scale' =>
                'integer',
            'account_number' =>
                'encrypted',
            'wallet_address' =>
                'encrypted',
            'qr_path' =>
                'encrypted',
            'is_active' =>
                'boolean',
            'display_order' =>
                'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(
            function (
                self $account
            ): void {
                if (
                    blank(
                        $account->uuid
                    )
                ) {
                    $account->uuid =
                        (string) Str::uuid();
                }
            }
        );
    }

    public function isBank(): bool
    {
        return $this->type
            === self::TYPE_BANK;
    }

    public function isCrypto(): bool
    {
        return $this->type
            === self::TYPE_CRYPTO;
    }

    public function isUsdtTrc20(): bool
    {
        return $this->isCrypto()
            && strtoupper(
                (string) $this
                    ->coin_symbol
            ) === 'USDT'
            && strtoupper(
                (string) $this
                    ->network
            ) === 'TRC20';
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
}
