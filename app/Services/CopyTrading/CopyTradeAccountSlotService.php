<?php

namespace App\Services\CopyTrading;

use App\Models\CopyTradeAccountBenefit;
use App\Models\CopyTradeBrokerAccount;
use DomainException;

class CopyTradeAccountSlotService
{
    public function resolve(
        CopyTradeBrokerAccount $account
    ): int {
        $account->loadMissing([
            'brokerMarket',
            'accountBenefit',
        ]);

        if ($account->accountBenefit instanceof CopyTradeAccountBenefit) {
            return (int) $account->accountBenefit->account_slot;
        }

        $market = strtoupper(
            (string) $account->brokerMarket?->market_category
        );

        if ($market === '') {
            throw new DomainException(
                'Copy Trade broker market is unavailable.'
            );
        }

        /*
         * A slot is occupied only by a benefit attached to a currently
         * connected account. DISCONNECTED/REJECTED accounts release the
         * logical slot, but their historical benefit row remains immutable.
         */
        $occupied = CopyTradeAccountBenefit::query()
            ->where(
                'user_id',
                $account->user_id
            )
            ->where(
                'market_category',
                $market
            )
            ->whereHas(
                'brokerAccount',
                function ($query) use ($account): void {
                    $query
                        ->whereKeyNot($account->getKey())
                        ->whereNotIn(
                            'status',
                            [
                                CopyTradeBrokerAccount::STATUS_DISCONNECTED,
                                CopyTradeBrokerAccount::STATUS_REJECTED,
                            ]
                        );
                }
            )
            ->pluck('account_slot')
            ->map(fn ($slot): int => (int) $slot)
            ->all();

        $max = (int) config(
            'tradex_copy_trading.max_accounts_per_market',
            5
        );

        for ($slot = 1; $slot <= $max; $slot++) {
            if (! in_array($slot, $occupied, true)) {
                return $slot;
            }
        }

        throw new DomainException(
            'No Copy Trade account slot is available for this market category.'
        );
    }
}
