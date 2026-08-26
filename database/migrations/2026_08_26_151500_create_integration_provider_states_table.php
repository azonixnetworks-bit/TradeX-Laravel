<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'integration_provider_states',
            function (Blueprint $table): void {
                $table->id();
                $table->string('provider_code', 40)->unique();
                $table->boolean('is_enabled')->default(false);
                $table->string('environment', 20)->nullable();
                $table->json('countries')->nullable();
                $table->boolean('eligibility_confirmed')->default(false);
                $table->unsignedBigInteger('version')->default(0);
                $table->foreignId('updated_by_admin_id')->nullable();
                $table->timestamps();

                $table->foreign(
                    'updated_by_admin_id',
                    'int_provider_admin_fk'
                )
                    ->references('id')
                    ->on('admin_users')
                    ->nullOnDelete();

                $table->index(
                    ['is_enabled', 'provider_code'],
                    'int_provider_enabled_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'integration_provider_states'
        );
    }
};
