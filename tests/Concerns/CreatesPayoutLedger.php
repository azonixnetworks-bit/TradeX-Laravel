<?php

namespace Tests\Concerns;

use App\Models\AffiliateCommission;
use App\Models\AffiliateCommissionAdjustment;
use App\Models\AffiliateQualifiedSale;
use App\Models\User;
use Illuminate\Support\Str;

trait CreatesPayoutLedger
{
    protected function configurePayoutProfile(User $user): void
    {
        $user->profile()->updateOrCreate([], [
            'paypal_email'=>'payout@example.test','binance_email'=>'binance@example.test','usdt_trc20_address'=>'TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE',
            'bank_name'=>'TradeX Test Bank','bank_branch'=>'Colombo','bank_account_holder'=>'Payout Test User','bank_account_number'=>'1234567890','financial_updated_at'=>now(),
        ]);
    }

    protected function seedPayoutCommission(User $affiliate,int $commissionCents,int $sequence=1): AffiliateCommission
    {
        $referred=$this->createApiUser();
        $sale=AffiliateQualifiedSale::query()->create([
            'affiliate_user_id'=>$affiliate->getKey(),'referred_user_id'=>$referred->getKey(),'source_provider'=>AffiliateQualifiedSale::PROVIDER_PAYPAL,
            'source_reference'=>'payout-test-'.Str::uuid(),'currency'=>'USD','ledger_sequence'=>$sequence,'qualified_ordinal'=>$sequence,
            'net_eligible_revenue_cents'=>max(7900,$commissionCents),'status'=>AffiliateQualifiedSale::STATUS_CLEARED,'qualified_at'=>now(),
        ]);
        return AffiliateCommission::query()->create([
            'affiliate_qualified_sale_id'=>$sale->getKey(),'affiliate_user_id'=>$affiliate->getKey(),'qualified_ordinal'=>$sequence,'rate_bps'=>1000,
            'rate_source'=>AffiliateCommission::RATE_SOURCE_STANDARD,'calculation_base_cents'=>max(7900,$commissionCents),'commission_amount_cents'=>$commissionCents,
            'currency'=>'USD','status'=>AffiliateCommission::STATUS_EARNED,'earned_at'=>now(),
        ]);
    }

    protected function seedPayoutAdjustment(AffiliateCommission $commission,User $affiliate,int $amountCents): AffiliateCommissionAdjustment
    {
        return AffiliateCommissionAdjustment::query()->create([
            'affiliate_commission_id'=>$commission->getKey(),'affiliate_qualified_sale_id'=>$commission->affiliate_qualified_sale_id,'affiliate_user_id'=>$affiliate->getKey(),
            'type'=>AffiliateCommissionAdjustment::TYPE_PAYMENT_REVERSAL,'amount_cents'=>$amountCents,'currency'=>'USD','reason'=>'API-14 test adjustment.',
        ]);
    }
}
