<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usdt_deposits', function (Blueprint $table): void {
            $table->string('proof_disk', 50)
                ->nullable()
                ->after('amount_micro');

            $table->text('proof_path')
                ->nullable()
                ->after('proof_disk');
        });
    }

    public function down(): void
    {
        Schema::table('usdt_deposits', function (Blueprint $table): void {
            $table->dropColumn(['proof_disk', 'proof_path']);
        });
    }
};
