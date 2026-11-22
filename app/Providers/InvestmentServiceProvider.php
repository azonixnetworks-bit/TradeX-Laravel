<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class InvestmentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('api')
            ->group(
                base_path('routes/api_investments.php')
            );

        Route::middleware('web')
            ->group(
                base_path('routes/admin_investments.php')
            );
    }
}
