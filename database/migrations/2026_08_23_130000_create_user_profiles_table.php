<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'user_profiles',
            function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger(
                    'user_id'
                )->unique();

                $table->text(
                    'telegram_username'
                )->nullable();

                $table->text(
                    'paypal_email'
                )->nullable();

                $table->text(
                    'binance_email'
                )->nullable();

                $table->text(
                    'usdt_trc20_address'
                )->nullable();

                $table->text(
                    'bank_name'
                )->nullable();

                $table->text(
                    'bank_branch'
                )->nullable();

                $table->text(
                    'bank_account_holder'
                )->nullable();

                $table->text(
                    'bank_account_number'
                )->nullable();

                $table->timestamp(
                    'financial_updated_at'
                )->nullable();

                $table->timestamps();

                $table->foreign(
                    'user_id',
                    'user_profiles_user_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();

                $table->index(
                    'financial_updated_at',
                    'user_profiles_fin_updated_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'user_profiles'
        );
    }
};
