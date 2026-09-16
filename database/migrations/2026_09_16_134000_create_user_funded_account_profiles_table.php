<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_funded_account_profiles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('funded_account_partner_id')
                ->nullable()
                ->constrained('funded_account_partners')
                ->nullOnDelete();
            $table->string('program_name', 160)->nullable();
            $table->string('phase', 32)->default('FUNDED');
            $table->boolean('is_enabled')->default(true);
            $table->string('source', 32)->default('CUSTOMER_SELECTION');
            $table->timestamp('selected_at')->nullable();
            $table->timestamps();

            $table->index(
                ['funded_account_partner_id', 'phase', 'is_enabled'],
                'user_funded_account_profiles_partner_phase_enabled_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_funded_account_profiles');
    }
};
