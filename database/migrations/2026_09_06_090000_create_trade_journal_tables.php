<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_journal_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('market', 24)->index();
            $table->string('stock_region', 32)->nullable();
            $table->string('symbol', 64)->index();
            $table->string('direction', 8);
            $table->string('status', 16)->index();
            $table->timestamp('trade_at')->index();
            $table->string('user_timezone', 96);
            $table->string('session_label', 160)->nullable()->index();
            $table->json('session_keys')->nullable();
            $table->string('session_source', 16)->default('auto');
            $table->string('broker', 120)->nullable();
            $table->string('account_label', 120)->nullable();
            $table->string('setup_strategy', 160)->nullable()->index();
            $table->json('timeframes')->nullable();
            $table->text('entry_reason');
            $table->text('notes_before')->nullable();
            $table->string('before_screenshot_path', 500)->nullable();
            $table->string('before_screenshot_mime', 96)->nullable();

            $table->decimal('planned_entry', 30, 12)->nullable();
            $table->decimal('actual_entry', 30, 12)->nullable();
            $table->decimal('planned_sl', 30, 12)->nullable();
            $table->decimal('current_sl', 30, 12)->nullable();
            $table->decimal('planned_tp', 30, 12)->nullable();
            $table->decimal('current_tp', 30, 12)->nullable();
            $table->decimal('position_size', 30, 12)->nullable();
            $table->decimal('risk_amount', 24, 8)->nullable();
            $table->string('risk_currency', 16)->nullable();
            $table->decimal('risk_percent', 8, 4)->nullable();
            $table->decimal('planned_rr', 24, 8)->nullable();

            $table->string('emotion_before', 40)->nullable()->index();
            $table->unsignedTinyInteger('confidence_before')->nullable();
            $table->text('mental_state_note')->nullable();
            $table->json('checklist')->nullable();

            $table->timestamp('opened_at')->nullable()->index();
            $table->text('execution_note')->nullable();
            $table->decimal('exit_price', 30, 12)->nullable();
            $table->timestamp('exit_at')->nullable()->index();
            $table->string('result', 16)->nullable()->index();
            $table->decimal('gross_pnl', 24, 8)->nullable();
            $table->decimal('fees', 24, 8)->nullable();
            $table->decimal('net_pnl', 24, 8)->nullable();
            $table->string('pnl_currency', 16)->nullable();
            $table->decimal('pnl_percent', 12, 6)->nullable();
            $table->decimal('realized_r', 24, 8)->nullable();

            $table->string('after_screenshot_path', 500)->nullable();
            $table->string('after_screenshot_mime', 96)->nullable();
            $table->string('emotion_after', 40)->nullable();
            $table->string('followed_plan_after', 16)->nullable()->index();
            $table->json('mistake_tags')->nullable();
            $table->text('went_well')->nullable();
            $table->text('went_wrong')->nullable();
            $table->text('lesson_learned')->nullable();
            $table->text('improvement_next_trade')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();

            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('closed_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'trade_at']);
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'symbol']);
            $table->index(['user_id', 'result']);
        });

        Schema::create('trade_journal_reviews', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('period_type', 16);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('user_timezone', 96);
            $table->json('summary_snapshot')->nullable();
            $table->text('positive_reflection')->nullable();
            $table->text('negative_reflection')->nullable();
            $table->text('biggest_lesson')->nullable();
            $table->text('stop_doing')->nullable();
            $table->text('continue_doing')->nullable();
            $table->text('next_focus')->nullable();
            $table->text('biggest_achievement')->nullable();
            $table->text('biggest_weakness')->nullable();
            $table->text('habit_to_remove')->nullable();
            $table->text('habit_to_continue')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'period_type', 'period_start'], 'trade_journal_reviews_period_unique');
            $table->index(['user_id', 'period_type', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_journal_reviews');
        Schema::dropIfExists('trade_journal_entries');
    }
};
