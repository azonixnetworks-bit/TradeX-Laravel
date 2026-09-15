<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FundedAdvisorReport extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'customer_name',
        'customer_email',
        'prop_firm_name',
        'partner_id',
        'partner_name_snapshot',
        'affiliate_url_snapshot',
        'promo_code_snapshot',
        'partner_offer_snapshot',
        'affiliate_disclosure_snapshot',
        'inputs_json',
        'output_json',
        'generated_by_admin_id',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'inputs_json' => 'array',
            'output_json' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $report): void {
            if (blank($report->uuid)) {
                $report->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(FundedAccountPartner::class, 'partner_id');
    }

    public function generatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'generated_by_admin_id');
    }
}
