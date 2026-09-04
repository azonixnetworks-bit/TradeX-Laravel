<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvestmentSettlementAsset extends Model
{
    public const WALLET_NOT_PROVIDED = 'NOT_PROVIDED';
    public const WALLET_PENDING = 'PENDING_VERIFICATION';
    public const WALLET_VERIFIED = 'VERIFIED';

    public const STATUS_AWAITING_WALLET = 'AWAITING_WALLET';
    public const STATUS_READY = 'READY';
    public const STATUS_PAYMENT_RECORDED = 'PAYMENT_RECORDED';
    public const STATUS_SETTLED = 'SETTLED';

    protected $fillable = [
        'uuid',
        'investment_settlement_id',
        'investment_id',
        'asset_identity_sha256',
        'asset_symbol',
        'asset_network',
        'contract_address',
        'gross_attributable_units',
        'return_bps',
        'customer_return_units',
        'early_exit_adjustment_units',
        'network_fee_asset_symbol',
        'network_fee_units',
        'network_fee_deducted_from_customer',
        'net_transfer_units',
        'allocation_snapshot',
        'payout_network',
        'destination_wallet',
        'destination_wallet_sha256',
        'wallet_reference',
        'wallet_status',
        'wallet_recorded_by_admin_id',
        'wallet_verified_by_admin_id',
        'wallet_verified_at',
        'status',
    ];

    protected $hidden = [
        'destination_wallet',
    ];

    protected function casts(): array
    {
        return [
            'gross_attributable_units' => 'decimal:18',
            'return_bps' => 'integer',
            'customer_return_units' => 'decimal:18',
            'early_exit_adjustment_units' => 'decimal:18',
            'network_fee_units' => 'decimal:18',
            'network_fee_deducted_from_customer' => 'boolean',
            'net_transfer_units' => 'decimal:18',
            'allocation_snapshot' => 'array',
            'destination_wallet' => 'encrypted',
            'wallet_verified_at' => 'datetime',
        ];
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(InvestmentSettlement::class, 'investment_settlement_id');
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InvestmentTransaction::class, 'investment_settlement_asset_id');
    }
}
