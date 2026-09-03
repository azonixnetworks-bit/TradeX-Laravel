<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'investment_risk_acceptances',
            function (Blueprint $table): void {
                $table->string('evidence_source', 40)
                    ->default('ADMIN_RECORDED')
                    ->after('disclosure_version');

                $table->string('acceptance_reference', 191)
                    ->nullable()
                    ->after('evidence_source');

                $table->foreignId('recorded_by_admin_id')
                    ->nullable()
                    ->after('accepted_user_agent')
                    ->constrained('admin_users')
                    ->nullOnDelete();
            }
        );

        Schema::table(
            'investment_agreements',
            function (Blueprint $table): void {
                $table->string('template_version', 80)
                    ->nullable()
                    ->after('status');

                $table->string('template_status', 80)
                    ->nullable()
                    ->after('template_version');

                $table->string('risk_disclosure_version', 80)
                    ->nullable()
                    ->after('template_status');

                $table->json('terms_snapshot')
                    ->nullable()
                    ->after('risk_disclosure_version');
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'investment_agreements',
            function (Blueprint $table): void {
                $table->dropColumn([
                    'template_version',
                    'template_status',
                    'risk_disclosure_version',
                    'terms_snapshot',
                ]);
            }
        );

        Schema::table(
            'investment_risk_acceptances',
            function (Blueprint $table): void {
                $table->dropForeign([
                    'recorded_by_admin_id',
                ]);

                $table->dropColumn([
                    'evidence_source',
                    'acceptance_reference',
                    'recorded_by_admin_id',
                ]);
            }
        );
    }
};
