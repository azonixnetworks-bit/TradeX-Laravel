<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvestmentProduct extends Model
{
    public const CODE_CRYPTO = 'CRYPTO';
    public const CODE_INDIAN_STOCKS = 'INDIAN_STOCKS';
    public const CODE_US_STOCKS = 'US_STOCKS';

    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_COMING_SOON = 'COMING_SOON';

    protected $fillable = [
        'code',
        'name',
        'status',
        'deposit_currency',
        'deposit_network',
        'customer_profit_share_bps',
        'company_profit_share_bps',
        'early_exit_return_bps',
        'risk_disclosure_required',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'customer_profit_share_bps' => 'integer',
            'company_profit_share_bps' => 'integer',
            'early_exit_return_bps' => 'integer',
            'risk_disclosure_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
