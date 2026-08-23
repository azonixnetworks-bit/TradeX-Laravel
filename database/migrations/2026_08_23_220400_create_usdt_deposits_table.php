<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'usdt_deposits',
            function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();

                $table->foreignId(
                    'payment_order_id'
                )->unique();

                $table->foreignId('user_id');

                $table->string(
                    'network',
                    20
                )->default('TRC20');

                $table->char(
                    'tx_hash',
                    64
                )->unique();

                $table->text(
                    'sender_address'
                )->nullable();

                $table->unsignedBigInteger(
                    'amount_micro'
                );

                $table->string(
                    'status',
                    30
                )->default('SUBMITTED');

                $table->timestamp(
                    'submitted_at'
                );

                $table->foreignId(
                    'reviewed_by_admin_id'
                )->nullable();

                $table->timestamp(
                    'reviewed_at'
                )->nullable();

                $table->text(
                    'review_reason'
                )->nullable();

                $table->timestamps();

                $table->foreign(
                    'payment_order_id',
                    'usdt_dep_order_fk'
                )
                    ->references('id')
                    ->on('payment_orders')
                    ->restrictOnDelete();

                $table->foreign(
                    'user_id',
                    'usdt_dep_user_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->restrictOnDelete();

                $table->foreign(
                    'reviewed_by_admin_id',
                    'usdt_dep_admin_fk'
                )
                    ->references('id')
                    ->on('admin_users')
                    ->nullOnDelete();

                $table->index(
                    [
                        'status',
                        'submitted_at',
                    ],
                    'usdt_dep_review_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'usdt_deposits'
        );
    }
};
