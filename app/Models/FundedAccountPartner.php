<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class FundedAccountPartner extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'website_url',
        'affiliate_url',
        'promo_code',
        'offer_text',
        'disclosure',
        'supported_countries',
        'account_sizes',
        'challenge_types',
        'logo_disk',
        'logo_path',
        'logo_mime_type',
        'logo_size_bytes',
        'is_featured',
        'is_active',
        'priority',
        'created_by_admin_id',
        'updated_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'supported_countries' => 'array',
            'account_sizes' => 'array',
            'challenge_types' => 'array',
            'logo_size_bytes' => 'integer',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $partner): void {
            if (blank($partner->uuid)) {
                $partner->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function reports(): HasMany
    {
        return $this->hasMany(FundedAdvisorReport::class, 'partner_id');
    }

    public function noTradeRules(): HasMany
    {
        return $this->hasMany(FundedNoTradeRule::class, 'funded_account_partner_id');
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'created_by_admin_id');
    }

    public function updatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'updated_by_admin_id');
    }

    public function hasLogo(): bool
    {
        return filled($this->logo_disk) && filled($this->logo_path);
    }
}
