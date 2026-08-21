<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_idea_targets', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('trade_idea_id')
                ->constrained('trade_ideas')
                ->cascadeOnDelete();

            $table->string('label', 20);
            $table->decimal('price', 24, 10);
            $table->unsignedTinyInteger('sort_order');

            $table->timestamp('hit_at')->nullable();
            $table->decimal('hit_price', 24, 10)->nullable();

            $table->foreignId('hit_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(['trade_idea_id', 'sort_order']);
            $table->index(['trade_idea_id', 'hit_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_idea_targets');
    }
};
