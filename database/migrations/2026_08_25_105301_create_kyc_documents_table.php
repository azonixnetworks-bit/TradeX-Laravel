<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('kyc_submission_id')
                ->constrained('kyc_submissions')
                ->restrictOnDelete();

            $table->string('document_kind', 40);

            $table->string('storage_disk', 40)
                ->default('private');

            /*
             * Stored encrypted at the model layer.
             * TEXT avoids ciphertext-length problems that can occur with
             * a fixed VARCHAR when encrypted casts are used.
             */
            $table->text('storage_path');

            $table->text('original_name')->nullable();

            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);

            $table->timestamps();

            $table->unique(
                ['kyc_submission_id', 'document_kind'],
                'kyc_documents_submission_kind_unique'
            );

            $table->index(
                'sha256',
                'kyc_documents_sha256_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_documents');
    }
};
