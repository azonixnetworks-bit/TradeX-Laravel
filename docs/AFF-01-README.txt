TradeX Laravel — AFF-01 Affiliate Commercial Foundation (NO-REFUND REVISION)
Baseline: main @ 9fdcab3 (Merge API-07 Our Channel Mobile API)
Branch: feature/aff-01-commercial-foundation

COMMERCIAL SOURCE + OWNER POLICY OVERRIDE
-----------------------------------------
The stage/commission schedule comes from the user-approved "TradeX Affiliate Commercial Reference" supplied on 2026-08-23.
The owner's explicit policy correction on 2026-08-23 is authoritative for refund handling: TradeX has NO voluntary refund option.

COMMERCIAL SCHEDULE
-------------------
- Starter: 1-30 qualified sales, 15%, $75 milestone gift at 30.
- Growth: 31-80, 17.5%, $125 gift at 80.
- Pro: 81-150, 20%, $200 gift at 150.
- Elite: 151-300, 20%, $400 gift at 300.
- Diamond: 301-500, 20%, $600 gift at 500.
- Ambassador: 501-750, 20%, $800 gift at 750.
- Business Partner: 751+, 20%, $2,200 gift at 1,500.
- Cumulative standard milestone gifts through 1,500 = $4,400.
- Commission is progressive and not retroactive.
- Band A 1-30 = 15%.
- Band B 31-80 = 17.5%.
- Band C 81+ = 20% standard maximum.
- Approved strategic/influencer exception may exceed 20% up to 24%.
- Special exceptions do not receive milestone gifts while the special rate is applied.
- Calculation base is PayPal Net Eligible Revenue.
- A sale enters the affiliate ledger only after successful/cleared payment.
- Milestone gifts are one-time rewards.

NO-REFUND POLICY
----------------
- TradeX has no user refund action.
- TradeX has no admin voluntary-refund action in AFF-01.
- AFF-01 does not create a refund endpoint, refund button, refund request model, or REFUNDED status.
- A cleared payment can be invalidated only by an approved external/mandatory payment exception, such as:
  * PayPal chargeback
  * PayPal/payment-processor reversal
  * duplicate-payment correction
  * unauthorized-payment determination
  * confirmed TradeX billing error
  * legally required reversal
- These exceptions are accounting/payment-integrity events, not a TradeX refund feature.
- They are recorded as a separate negative AffiliateCommissionAdjustment so the original commission ledger remains auditable.

FOUNDATION IMPLEMENTATION
-------------------------
1. affiliate_stages
   Seeded commercial configuration for the seven approved stages.

2. affiliate_profiles
   One row per affiliate user, current qualified sales/stage, commercial totals, and approved special-rate metadata.

3. affiliate_qualified_sales
   Auditable direct-sale ledger. PayPal source references are idempotent and unique.

4. affiliate_commissions
   Original earned commission ledger. The original rate/base/amount is preserved.

5. affiliate_commission_adjustments
   Used only for approved forced payment-reversal exceptions; not for voluntary refunds.

6. affiliate_milestone_rewards
   One-time gift ledger with a unique affiliate + milestone constraint.

7. AffiliateCommercialService
   - validates direct referral attribution
   - records cleared PayPal sales
   - applies progressive standard rates
   - applies management-approved special rates up to 24%
   - creates one-time standard milestone gifts
   - records only approved forced payment-reversal exceptions
   - recalculates current stage after a forced payment reversal
   - writes audit-log events

SECURITY / FINANCIAL INVARIANTS
-------------------------------
- Affiliate cannot qualify their own sale.
- Referred user's referred_by_user_id must match the direct affiliate.
- PayPal source reference cannot double-credit commission.
- Same source reference with conflicting payload is rejected.
- Special rate must be >20% and <=24%, must have an approving admin and reason.
- No upline/downline/team/generation/override commission logic exists.
- No voluntary-refund operation exists.
- Original commission amount/rate is never silently overwritten.

MILESTONE GIFT EXCEPTION HANDLING
--------------------------------
The commercial reference does not define an automatic clawback rule for a gift already released if a later external payment reversal reduces the qualifying count. AFF-01 therefore does NOT automatically claw back an already-earned milestone gift. A separate approved policy is required before any gift clawback automation is added.

NOT INCLUDED IN AFF-01
----------------------
- Mobile dashboard API (API-08 remains on hold)
- Affiliate admin UI
- Payout workflow
- KYC workflow
- PayPal webhook/controller integration
- Gift payment/disbursement workflow
- Any voluntary refund workflow

INSTALL / TEST
--------------
1. Extract into the Laravel project root and replace included placeholder/full-merged files.
2. Run migrations.
3. Seed the commercial stage table.
4. Run targeted tests, then full regression.

Commands:
php artisan optimize:clear
php artisan migrate
php artisan db:seed --class=AffiliateStageSeeder
php artisan test --filter=AffiliateCommercialFoundationTest
php artisan test
git diff --check
git status
