<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_reward_saved_calculations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('calculator_type', 32);
            $table->string('title', 120);
            $table->json('input_payload');
            $table->json('result_payload');
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_reward_saved_calculations');
    }
};
