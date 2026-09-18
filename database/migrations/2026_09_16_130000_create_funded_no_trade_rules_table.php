<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funded_no_trade_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('funded_account_partner_id')
                ->constrained('funded_account_partners')
                ->restrictOnDelete();
            $table->string('program_name', 160)->nullable();
            $table->string('phase', 32)->default('ALL');
            $table->string('policy', 32)->default('RESTRICTED');
            $table->json('impact_levels')->nullable();
            $table->unsignedSmallInteger('minutes_before')->default(2);
            $table->unsignedSmallInteger('minutes_after')->default(2);
            $table->boolean('affected_instruments_only')->default(true);
            $table->json('restricted_currencies')->nullable();
            $table->json('restricted_instruments')->nullable();
            $table->boolean('block_open_new_trades')->default(true);
            $table->boolean('block_close_trades')->default(false);
            $table->boolean('block_pending_orders')->default(true);
            $table->text('rule_summary')->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->string('source_reference', 255)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(100);
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->foreignId('updated_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['funded_account_partner_id', 'is_active', 'priority'],
                'funded_no_trade_rules_partner_active_priority_idx'
            );
            $table->index(['phase', 'is_active'], 'funded_no_trade_rules_phase_active_idx');
            $table->index(['verified_at', 'expires_at'], 'funded_no_trade_rules_verification_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funded_no_trade_rules');
    }
};
