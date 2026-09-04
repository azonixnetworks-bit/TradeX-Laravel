<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investment_evidences', function (Blueprint $table): void {
            $table->foreignId('investment_settlement_id')
                ->nullable()
                ->after('investment_profit_period_id')
                ->constrained('investment_settlements')
                ->restrictOnDelete();

            $table->index(
                [
                    'investment_settlement_id',
                    'evidence_type',
                    'status',
                ],
                'inv_evidence_settlement_type_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('investment_evidences', function (Blueprint $table): void {
            $table->dropIndex(
                'inv_evidence_settlement_type_status_idx'
            );
            $table->dropForeign([
                'investment_settlement_id',
            ]);
            $table->dropColumn(
                'investment_settlement_id'
            );
        });
    }
};
