<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_trade_idea_reactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trade_idea_id')
                ->constrained('trade_ideas')
                ->cascadeOnDelete();
            $table->string('reaction', 10);
            $table->timestamps();
            $table->unique(
                ['user_id', 'trade_idea_id'],
                'user_trade_idea_reaction_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_trade_idea_reactions');
    }
};
