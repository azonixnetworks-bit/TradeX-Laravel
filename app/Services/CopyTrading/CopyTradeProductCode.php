<?php

namespace App\Services\CopyTrading;

use DomainException;

class CopyTradeProductCode
{
    public static function monthly(
        string $marketCategory,
        int $accountSlot
    ): string {
        $market = self::market($marketCategory);
        self::assertSlot($accountSlot);

        return 'COPY_TRADING_'.$market.'_ACCOUNT_'.$accountSlot.'_MONTHLY';
    }

    public static function lifetimeFree(
        string $marketCategory,
        int $accountSlot
    ): string {
        $market = self::market($marketCategory);
        self::assertSlot($accountSlot);

        return 'COPY_TRADING_'.$market.'_ACCOUNT_'.$accountSlot.'_LIFETIME_FREE';
    }

    public static function thirtyDayFree(
        string $marketCategory,
        int $accountSlot
    ): string {
        $market = self::market($marketCategory);
        self::assertSlot($accountSlot);

        return 'COPY_TRADING_'.$market.'_ACCOUNT_'.$accountSlot.'_30_DAY_FREE';
    }

    private static function market(string $marketCategory): string
    {
        $market = strtoupper(trim($marketCategory));

        if (
            ! in_array(
                $market,
                (array) config(
                    'tradex_copy_trading.market_categories',
                    []
                ),
                true
            )
        ) {
            throw new DomainException(
                'Unsupported Copy Trade market category.'
            );
        }

        return $market;
    }

    private static function assertSlot(int $accountSlot): void
    {
        $max = (int) config(
            'tradex_copy_trading.max_accounts_per_market',
            5
        );

        if ($accountSlot < 1 || $accountSlot > $max) {
            throw new DomainException(
                'Invalid Copy Trade account slot.'
            );
        }
    }
}
