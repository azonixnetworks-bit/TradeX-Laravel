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
        'tender_amount_decimal',
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

                /*
                 * Backward-compatible invariant for legacy/test/job callers
                 * that still create an account with the minor-unit value only.
                 * New admin writes persist the exact decimal explicitly.
                 */
                if (
                    blank(
                        $account
                            ->tender_amount_decimal
                    )
                    && $account
                        ->tender_amount_minor
                        !== null
                ) {
                    $scale =
                        (int) (
                            $account
                                ->amount_scale
                            ?? 0
                        );

                    if (
                        $scale >= 0
                        && $scale <= 18
                    ) {
                        $minor =
                            ltrim(
                                (string) $account
                                    ->tender_amount_minor,
                                '0'
                            );

                        if (
                            $minor === ''
                        ) {
                            $minor = '0';
                        }

                        if (
                            $scale === 0
                        ) {
                            $account
                                ->tender_amount_decimal =
                                $minor;
                        } else {
                            $digits =
                                str_pad(
                                    $minor,
                                    $scale + 1,
                                    '0',
                                    STR_PAD_LEFT
                                );

                            $account
                                ->tender_amount_decimal =
                                substr(
                                    $digits,
                                    0,
                                    -$scale
                                )
                                .'.'
                                .substr(
                                    $digits,
                                    -$scale
                                );
                        }
                    }
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
