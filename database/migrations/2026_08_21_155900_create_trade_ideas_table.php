<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_ideas', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('title', 180);
            $table->string('summary', 500)->nullable();
            $table->text('analysis')->nullable();

            $table->string('category', 16);
            $table->string('market_region', 24)->nullable();

            $table->string('symbol', 40);
            $table->string('instrument_name', 120)->nullable();

            $table->string('direction', 8);
            $table->string('timeframe', 12);
            $table->decimal('entry_price_low', 24, 10)->nullable();
            $table->decimal('entry_price_high', 24, 10)->nullable();
            $table->decimal('stop_loss', 24, 10)->nullable();
            $table->decimal('current_stop_loss', 24, 10)->nullable();

            $table->timestamp('entry_triggered_at')->nullable();
            $table->decimal('entry_price_actual', 24, 10)->nullable();

            $table->string('status', 16)->default('DRAFT');
            $table->timestamp('published_at')->nullable();

            $table->timestamp('closed_at')->nullable();
            $table->string('close_outcome', 20)->nullable();
            $table->decimal('close_price', 24, 10)->nullable();
            $table->text('close_note')->nullable();

            $table->foreignId('created_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();

            $table->foreignId('updated_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['category', 'status']);
            $table->index(['symbol', 'status']);
            $table->index(['market_region', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_ideas');
    }
};
