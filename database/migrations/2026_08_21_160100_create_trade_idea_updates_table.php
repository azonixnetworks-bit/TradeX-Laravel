<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_idea_updates', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('trade_idea_id')
                ->constrained('trade_ideas')
                ->cascadeOnDelete();

            $table->foreignId('trade_idea_target_id')
                ->nullable()
                ->constrained('trade_idea_targets')
                ->nullOnDelete();

            $table->string('type', 24);
            $table->text('message')->nullable();
            $table->decimal('price', 24, 10)->nullable();

            $table->foreignId('created_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['trade_idea_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_idea_updates');
    }
};
