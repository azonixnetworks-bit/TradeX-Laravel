<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_commission_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('affiliate_commission_id');
            $table->foreignId('affiliate_qualified_sale_id');
            $table->foreignId('affiliate_user_id');

            $table->string('type', 30);
            $table->bigInteger('amount_cents');
            $table->char('currency', 3)->default('USD');
            $table->string('reason', 500);

            $table->foreignId('created_by_admin_id')
                ->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Explicit short MySQL-safe constraint/index names
            |--------------------------------------------------------------------------
            |
            | Several default Laravel-generated identifiers for this long table
            | name exceed MySQL's 64-character identifier limit.
            |
            */

            $table->foreign(
                'affiliate_commission_id',
                'aff_adj_commission_fk'
            )
                ->references('id')
                ->on('affiliate_commissions')
                ->restrictOnDelete();

            $table->foreign(
                'affiliate_qualified_sale_id',
                'aff_adj_sale_fk'
            )
                ->references('id')
                ->on('affiliate_qualified_sales')
                ->restrictOnDelete();

            $table->foreign(
                'affiliate_user_id',
                'aff_adj_user_fk'
            )
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            $table->foreign(
                'created_by_admin_id',
                'aff_adj_admin_fk'
            )
                ->references('id')
                ->on('admin_users')
                ->nullOnDelete();

            $table->index(
                ['affiliate_user_id', 'created_at'],
                'aff_adj_user_created_idx'
            );

            $table->index(
                ['affiliate_qualified_sale_id', 'type'],
                'aff_adj_sale_type_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_commission_adjustments');
    }
};
