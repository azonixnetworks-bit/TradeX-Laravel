<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_risk_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('investment_id')
                ->constrained('investments')
                ->restrictOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('disclosure_version', 50);
            $table->json('acceptance_payload');
            $table->timestamp('accepted_at');
            $table->string('accepted_ip', 45)->nullable();
            $table->text('accepted_user_agent')->nullable();
            $table->timestamps();

            $table->unique(
                ['investment_id', 'disclosure_version'],
                'investment_risk_acceptance_version_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_risk_acceptances');
    }
};
