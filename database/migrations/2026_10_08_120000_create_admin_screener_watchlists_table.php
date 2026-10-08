<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_screener_watchlists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_user_id')
                ->constrained('admin_users')
                ->cascadeOnDelete();
            $table->string('market', 20);
            $table->string('symbol', 30);
            $table->string('name', 120);
            $table->string('base_currency', 30)->nullable();
            $table->string('logo_url', 2048)->nullable();
            $table->timestamps();

            $table->unique(
                ['admin_user_id', 'market', 'symbol'],
                'admin_screener_watchlist_symbol_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_screener_watchlists');
    }
};
