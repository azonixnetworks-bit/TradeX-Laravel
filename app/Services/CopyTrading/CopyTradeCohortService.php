<?php

namespace App\Services\CopyTrading;

use App\Models\PaymentOrder;
use App\Models\User;
use App\Models\UserEntitlement;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class CopyTradeCohortService
{
    public function qualification(User $user): array
    {
        $educationEntitlement = UserEntitlement::query()
            ->where('user_id', $user->getKey())
            ->where(
                'product_code',
                PaymentOrder::PRODUCT_EDUCATION_PREMIUM
            )
            ->whereNotNull('payment_id')
            ->whereNotNull('activated_at')
            ->first();

        if (! $educationEntitlement instanceof UserEntitlement) {
            return [
                'qualified' => false,
                'reason_code' => 'EDUCATION_PURCHASE_NOT_FOUND',
                'cohort_position' => null,
                'education_entitlement' => null,
            ];
        }

        if (
            $educationEntitlement->status
            !== UserEntitlement::STATUS_ACTIVE
        ) {
            return [
                'qualified' => false,
                'reason_code' => 'EDUCATION_ENTITLEMENT_NOT_ACTIVE',
                'cohort_position' => null,
                'education_entitlement' => $educationEntitlement,
            ];
        }

        $cohort = $this->cohortSubquery();

        $current = DB::query()
            ->fromSub(clone $cohort, 'education_cohort')
            ->where(
                'user_id',
                $user->getKey()
            )
            ->first();

        if (! $current) {
            return [
                'qualified' => false,
                'reason_code' => 'EDUCATION_COHORT_NOT_RESOLVED',
                'cohort_position' => null,
                'education_entitlement' => $educationEntitlement,
            ];
        }

        $position = DB::query()
            ->fromSub(clone $cohort, 'education_cohort')
            ->where(function ($query) use ($current): void {
                $query
                    ->where(
                        'first_activated_at',
                        '<',
                        $current->first_activated_at
                    )
                    ->orWhere(function ($tie) use ($current): void {
                        $tie
                            ->where(
                                'first_activated_at',
                                '=',
                                $current->first_activated_at
                            )
                            ->where(
                                'first_id',
                                '<=',
                                $current->first_id
                            );
                    });
            })
            ->count();

        return [
            'qualified' => true,
            'reason_code' => 'EDUCATION_PURCHASE_ELIGIBLE',
            'cohort_position' => max(1, (int) $position),
            'education_entitlement' => $educationEntitlement,
        ];
    }

    private function cohortSubquery(): Builder
    {
        return DB::table('user_entitlements')
            ->select('user_id')
            ->selectRaw(
                'MIN(activated_at) as first_activated_at'
            )
            ->selectRaw(
                'MIN(id) as first_id'
            )
            ->where(
                'product_code',
                PaymentOrder::PRODUCT_EDUCATION_PREMIUM
            )
            ->whereNotNull('payment_id')
            ->whereNotNull('activated_at')
            ->groupBy('user_id');
    }
}
