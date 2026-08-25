<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_submissions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->char('country_code', 2);
            $table->string('document_type', 40);

            $table->longText('identity_data');
            $table->char('identity_number_hash', 64)->nullable();

            $table->string('status', 32)
                ->default('PENDING_REVIEW');

            $table->text('rejection_reason')->nullable();
            $table->longText('internal_notes')->nullable();

            $table->foreignId('supersedes_submission_id')
                ->nullable()
                ->constrained('kyc_submissions')
                ->restrictOnDelete();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->foreignId('reviewed_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(
                ['user_id', 'status'],
                'kyc_submissions_user_status_idx'
            );

            $table->index(
                ['country_code', 'status'],
                'kyc_submissions_country_status_idx'
            );

            $table->index(
                'identity_number_hash',
                'kyc_submissions_identity_hash_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_submissions');
    }
};
