<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_milestone_rewards', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('affiliate_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('affiliate_stage_id')
                ->constrained('affiliate_stages')
                ->restrictOnDelete();

            $table->unsignedInteger('milestone_sales');
            $table->unsignedBigInteger('gift_amount_cents');
            $table->char('currency', 3)->default('USD');
            $table->string('status', 20)->default('EARNED');
            $table->timestamp('earned_at');
            $table->timestamps();

            $table->unique(
                ['affiliate_user_id', 'milestone_sales'],
                'affiliate_milestone_rewards_user_milestone_unique'
            );
            $table->index(['affiliate_user_id', 'earned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_milestone_rewards');
    }
};
