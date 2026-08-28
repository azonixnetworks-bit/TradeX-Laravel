<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CopyTradingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->group(
                base_path('routes/api_copy_trading.php')
            );

        Route::middleware('web')
            ->group(
                base_path('routes/admin_copy_trading.php')
            );
    }
}
