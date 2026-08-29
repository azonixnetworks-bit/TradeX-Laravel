<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ProductServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
       Route::prefix('api')
    ->middleware('api')
    ->group(
        base_path(
            'routes/api_products.php'
        )
    );

        Route::middleware('web')
            ->group(
                base_path(
                    'routes/admin_products.php'
                )
            );
    }
}
