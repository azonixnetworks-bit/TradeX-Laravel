<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_profiles', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->unique();

            $table->foreignId('current_stage_id')
                ->nullable();

            $table->unsignedInteger('qualified_sales_count')->default(0);
            $table->unsignedInteger('lifetime_cleared_sales_count')->default(0);
            $table->unsignedBigInteger('qualified_net_eligible_revenue_cents')->default(0);
            $table->unsignedBigInteger('lifetime_commission_earned_cents')->default(0);
            $table->bigInteger('commission_adjustment_cents')->default(0);
            $table->unsignedBigInteger('milestone_gifts_earned_cents')->default(0);

            $table->unsignedSmallInteger('special_commission_bps')->nullable();
            $table->timestamp('special_commission_approved_at')->nullable();

            $table->foreignId('special_commission_approved_by_admin_id')
                ->nullable();

            $table->string('special_commission_reason', 500)->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Explicit short MySQL-safe constraint names
            |--------------------------------------------------------------------------
            |
            | MySQL identifiers are limited to 64 characters. Laravel's default
            | generated name for special_commission_approved_by_admin_id exceeds
            | that limit, so AFF-01 uses explicit stable names.
            |
            */

            $table->foreign(
                'user_id',
                'aff_profiles_user_fk'
            )
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            $table->foreign(
                'current_stage_id',
                'aff_profiles_stage_fk'
            )
                ->references('id')
                ->on('affiliate_stages')
                ->nullOnDelete();

            $table->foreign(
                'special_commission_approved_by_admin_id',
                'aff_profiles_special_admin_fk'
            )
                ->references('id')
                ->on('admin_users')
                ->nullOnDelete();

            $table->index(
                'current_stage_id',
                'aff_profiles_stage_idx'
            );

            $table->index(
                'qualified_sales_count',
                'aff_profiles_sales_count_idx'
            );

            $table->index(
                'special_commission_approved_at',
                'aff_profiles_special_approved_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_profiles');
    }
};
