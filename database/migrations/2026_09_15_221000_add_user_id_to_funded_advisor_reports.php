<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funded_advisor_reports', function (Blueprint $table): void {
            $table->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['user_id', 'generated_at']);
        });
    }

    public function down(): void
    {
        Schema::table('funded_advisor_reports', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'generated_at']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
