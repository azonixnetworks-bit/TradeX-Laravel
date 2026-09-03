<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_profit_periods', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('investment_id')
                ->constrained('investments')
                ->restrictOnDelete();

            $table->unsignedInteger('sequence');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 30)->default('UPCOMING')->index();

            $table->decimal('opening_equity_usdt', 28, 8)->default(0);
            $table->decimal('closing_equity_usdt', 28, 8)->default(0);
            $table->decimal('gross_realized_profit_usdt', 28, 8)->default(0);
            $table->decimal('realized_losses_usdt', 28, 8)->default(0);
            $table->decimal('approved_costs_usdt', 28, 8)->default(0);

            $table->decimal('loss_carryforward_open_usdt', 28, 8)->default(0);
            $table->decimal('loss_carryforward_close_usdt', 28, 8)->default(0);
            $table->decimal('high_water_mark_open_usdt', 28, 8)->default(0);
            $table->decimal('high_water_mark_close_usdt', 28, 8)->default(0);

            $table->decimal('eligible_net_profit_usdt', 28, 8)->default(0);
            $table->decimal('customer_profit_share_usdt', 28, 8)->default(0);
            $table->decimal('company_profit_share_usdt', 28, 8)->default(0);

            $table->foreignId('prepared_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();

            $table->foreignId('approved_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();

            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['investment_id', 'sequence'],
                'investment_profit_period_sequence_unique'
            );
            $table->index(['period_end', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_profit_periods');
    }
};
