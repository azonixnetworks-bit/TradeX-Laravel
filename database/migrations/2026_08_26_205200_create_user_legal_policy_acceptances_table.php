<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'user_legal_policy_acceptances',
            function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();

                $table->foreignId('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table->foreignId('legal_policy_version_id')
                    ->constrained('legal_policy_versions')
                    ->restrictOnDelete();

                $table->string('policy_type', 60);
                $table->unsignedInteger('policy_version_number');
                $table->char('content_sha256', 64);
                $table->timestamp('accepted_at');

                $table->string('accepted_ip', 45)->nullable();
                $table->string('accepted_user_agent', 1024)->nullable();
                $table->uuid('device_uuid')->nullable();
                $table->string('device_platform', 30)->nullable();

                $table->timestamps();

                $table->unique(
                    ['user_id', 'legal_policy_version_id'],
                    'legal_accept_user_version_uq'
                );

                $table->index(
                    ['user_id', 'policy_type'],
                    'legal_accept_user_type_idx'
                );

                $table->index(
                    ['policy_type', 'policy_version_number'],
                    'legal_accept_type_version_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('user_legal_policy_acceptances');
    }
};
