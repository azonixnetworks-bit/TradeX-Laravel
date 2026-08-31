<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_receiving_accounts', function (Blueprint $table): void {
            $table->unsignedBigInteger('tender_amount_minor')
                ->nullable()
                ->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('payment_receiving_accounts', function (Blueprint $table): void {
            $table->dropColumn('tender_amount_minor');
        });
    }
};
