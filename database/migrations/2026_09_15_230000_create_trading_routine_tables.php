<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trading_routine_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->string('timezone', 64)->default('Asia/Colombo');
            $table->time('delivery_time_local')->default('06:00:00');
            $table->decimal('planning_balance', 18, 2)->default(10000);
            $table->decimal('risk_per_trade_percent', 8, 4)->default(0.5);
            $table->decimal('daily_risk_cap_percent', 8, 4)->default(1.0);
            $table->unsignedTinyInteger('max_trades')->default(3);
            $table->unsignedTinyInteger('max_consecutive_losses')->default(2);
            $table->decimal('minimum_rr', 8, 4)->default(2.0);
            $table->json('markets_json')->nullable();
            $table->json('sessions_json')->nullable();
            $table->json('pre_market_checklist_json')->nullable();
            $table->json('no_trade_rules_json')->nullable();
            $table->string('focus_statement', 500)->nullable();
            $table->boolean('whatsapp_pdf_enabled')->default(false);
            $table->timestamp('whatsapp_opted_in_at')->nullable();
            $table->timestamps();

            $table->index(['enabled', 'whatsapp_pdf_enabled']);
        });

        Schema::create('trading_routine_daily_plans', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('plan_date');
            $table->string('customer_name_snapshot', 160);
            $table->string('customer_email_snapshot', 255)->nullable();
            $table->string('whatsapp_number_snapshot', 40)->nullable();
            $table->string('timezone_snapshot', 64);
            $table->json('preference_snapshot_json');
            $table->json('plan_json');
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique(['user_id', 'plan_date']);
            $table->index(['plan_date', 'generated_at']);
        });

        Schema::create('trading_routine_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trading_routine_daily_plan_id')
                ->constrained('trading_routine_daily_plans')
                ->cascadeOnDelete();
            $table->string('channel', 30)->default('WHATSAPP');
            $table->string('destination_snapshot', 40)->nullable();
            $table->string('status', 40)->default('PENDING');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('provider', 40)->nullable();
            $table->string('provider_message_id', 255)->nullable();
            $table->string('provider_media_id', 255)->nullable();
            $table->string('failure_code', 120)->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['trading_routine_daily_plan_id', 'channel'], 'routine_delivery_plan_channel_unique');
            $table->index(['status', 'last_attempt_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_routine_deliveries');
        Schema::dropIfExists('trading_routine_daily_plans');
        Schema::dropIfExists('trading_routine_preferences');
    }
};
