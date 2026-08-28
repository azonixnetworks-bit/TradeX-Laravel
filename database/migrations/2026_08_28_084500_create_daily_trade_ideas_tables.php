<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_trade_ideas', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('title', 180);
            $table->string('summary', 500)->nullable();
            $table->text('analysis')->nullable();

            $table->string('tradingview_url', 2048)->nullable();
            $table->string('chart_image_disk', 40)->nullable();
            $table->string('chart_image_path', 500)->nullable();
            $table->string('chart_image_mime_type', 100)->nullable();
            $table->unsignedBigInteger('chart_image_size_bytes')->nullable();

            $table->string('category', 20)->index();
            $table->string('market_region', 30)->nullable()->index();
            $table->string('symbol', 40)->index();
            $table->string('instrument_name', 120)->nullable();
            $table->string('direction', 10);
            $table->string('timeframe', 10);

            $table->decimal('entry_price_low', 24, 10)->nullable();
            $table->decimal('entry_price_high', 24, 10)->nullable();
            $table->decimal('stop_loss', 24, 10)->nullable();

            $table->string('status', 20)->default('DRAFT')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('archived_at')->nullable()->index();

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
        });

        Schema::create('daily_trade_idea_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('daily_trade_idea_id')
                ->constrained('daily_trade_ideas')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('sort_order');
            $table->decimal('price', 24, 10);
            $table->timestamps();

            $table->unique(
                ['daily_trade_idea_id', 'sort_order'],
                'daily_trade_idea_target_sort_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_trade_idea_targets');
        Schema::dropIfExists('daily_trade_ideas');
    }
};
