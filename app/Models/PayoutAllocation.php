<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutAllocation extends Model
{
    protected $fillable = ['payout_request_id','affiliate_commission_id','amount_cents'];
    protected function casts(): array { return ['amount_cents'=>'integer']; }
    public function payoutRequest(): BelongsTo { return $this->belongsTo(PayoutRequest::class); }
    public function commission(): BelongsTo { return $this->belongsTo(AffiliateCommission::class, 'affiliate_commission_id'); }
}
