<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funded_no_trade_rules', function (Blueprint $table): void {
            $table->string('enforcement_type', 40)
                ->default('BLOCKED')
                ->after('policy');
            $table->json('event_keywords')
                ->nullable()
                ->after('impact_levels');

            $table->index(
                ['enforcement_type', 'is_active'],
                'funded_no_trade_rules_enforcement_active_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('funded_no_trade_rules', function (Blueprint $table): void {
            $table->dropIndex('funded_no_trade_rules_enforcement_active_idx');
            $table->dropColumn(['enforcement_type', 'event_keywords']);
        });
    }
};
