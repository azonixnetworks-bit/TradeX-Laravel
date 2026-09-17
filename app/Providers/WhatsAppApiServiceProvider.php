<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class WhatsAppApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Constructor injection is sufficient for WhatsApp API services.
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(
            base_path('routes/api_whatsapp.php')
        );
    }
}
